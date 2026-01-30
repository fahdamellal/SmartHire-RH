<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CandidateProfileService
{
    public function getProfile(int $id_file): array
    {
        $file = DB::selectOne("
            SELECT id_file, nom, prenom, email, phone, cv_text, skills, skills_flat
            FROM cv_files
            WHERE id_file = ?
        ", [$id_file]);

        if (!$file) {
            return ['ok' => false, 'status' => 404, 'note' => 'cv_file not found'];
        }

        $chunks = DB::select("
            SELECT chunk_index, chunk_text, section
            FROM cv_chunks
            WHERE id_file = ?
            ORDER BY chunk_index ASC
        ", [$id_file]);

        $skills = $this->extractSkills($file);
        $summary = $this->extractSummary($file, $chunks);
        $experiences = $this->extractExperiences($file, $chunks);

        return [
            'ok' => true,
            'id_file' => (int)$file->id_file,
            'nom' => $file->nom,
            'prenom' => $file->prenom,
            'email' => $file->email,
            'phone' => $file->phone,
            'summary' => $summary,
            'experiences' => $experiences,
            'skills' => $skills,
        ];
    }

    private function extractSkills(object $file): array
    {
        // skills jsonb
        if (!empty($file->skills)) {
            $decoded = is_string($file->skills) ? json_decode($file->skills, true) : $file->skills;
            if (is_array($decoded)) {
                $flat = [];
                $this->flattenSkillsArray($decoded, $flat);
                $flat = array_values(array_unique(array_filter(array_map([$this, 'cleanSkill'], $flat))));
                sort($flat);
                return array_slice($flat, 0, 40);
            }
        }

        // skills_flat
        if (!empty($file->skills_flat) && is_string($file->skills_flat)) {
            $parts = preg_split('/[,;|\n]+/u', $file->skills_flat);
            $parts = array_values(array_unique(array_filter(array_map([$this, 'cleanSkill'], $parts))));
            sort($parts);
            return array_slice($parts, 0, 40);
        }

        return [];
    }

    private function flattenSkillsArray(array $arr, array &$out): void
    {
        foreach ($arr as $k => $v) {
            if (is_string($v)) $out[] = $v;
            elseif (is_string($k) && (is_bool($v) || is_numeric($v))) $out[] = $k; // {"Laravel": true}
            elseif (is_array($v)) $this->flattenSkillsArray($v, $out);
        }
    }

    private function cleanSkill(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return mb_substr($s, 0, 32);
    }

    private function extractSummary(object $file, array $chunks): string
    {
        $wanted = ['summary', 'résumé', 'resume', 'profil', 'profile', 'about'];

        foreach ($chunks as $c) {
            $sec = mb_strtolower(trim((string)($c->section ?? '')));
            if ($sec !== '') {
                foreach ($wanted as $w) {
                    if (str_contains($sec, $w)) {
                        $txt = trim((string)$c->chunk_text);
                        if (mb_strlen($txt) >= 40) return $this->limit($txt, 650);
                    }
                }
            }
        }

        $text = trim((string)($file->cv_text ?? ''));
        if ($text !== '') return $this->limit($text, 650);

        $acc = [];
        foreach (array_slice($chunks, 0, 3) as $c) {
            $t = trim((string)$c->chunk_text);
            if ($t) $acc[] = $t;
        }
        return $this->limit(implode("\n\n", $acc), 650);
    }

    private function extractExperiences(object $file, array $chunks): array
    {
        $expChunks = [];
        foreach ($chunks as $c) {
            $sec = mb_strtolower(trim((string)($c->section ?? '')));
            if ($sec !== '' && (str_contains($sec, 'exp') || str_contains($sec, 'experience'))) {
                $txt = trim((string)$c->chunk_text);
                if (mb_strlen($txt) > 30) $expChunks[] = $txt;
            }
        }

        $text = $expChunks ? implode("\n\n", $expChunks) : (string)($file->cv_text ?? '');
        $text = trim($text);
        if ($text === '') return [];

        $lines = preg_split("/\R/u", $text);
        $items = [];
        $current = '';

        foreach ($lines as $ln) {
            $ln = trim($ln);
            if ($ln === '') continue;

            if ($this->looksLikePeriod($ln) && $current !== '') {
                $items[] = $current;
                $current = $ln;
            } else {
                $current = $current ? ($current . "\n" . $ln) : $ln;
            }
        }
        if ($current) $items[] = $current;

        $out = [];
        foreach (array_slice($items, 0, 10) as $raw) {
            $out[] = $this->experienceFromBlock($raw);
        }

        return array_values(array_filter($out, fn($e) => trim($e['title'] . $e['company'] . $e['description']) !== ''));
    }

    private function looksLikePeriod(string $s): bool
    {
        $s2 = mb_strtolower($s);
        if (preg_match('/\b(19|20)\d{2}\b/u', $s2) && (str_contains($s2, '-') || str_contains($s2, '–') || str_contains($s2, 'à'))) {
            return true;
        }
        if (str_contains($s2, 'présent') || str_contains($s2, 'present')) return true;
        return false;
    }

    private function experienceFromBlock(string $block): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split("/\R/u", $block))));
        $head = $lines[0] ?? '';
        $rest = implode("\n", array_slice($lines, 1));

        $title = $head;
        $company = '';
        $period = '';

        if (preg_match('/((19|20)\d{2}.*)$/u', $head, $m)) {
            $period = trim($m[1]);
            $title = trim(str_replace($m[1], '', $head));
        }

        $parts = preg_split('/\s[—-]\s/u', $title);
        if (count($parts) >= 2) {
            $title = trim($parts[0]);
            $company = trim($parts[1]);
        }

        return [
            'title' => $this->limit($title, 80),
            'company' => $this->limit($company, 80),
            'period' => $this->limit($period, 40),
            'description' => $this->limit(trim($rest), 700),
        ];
    }

    private function limit(string $s, int $max): string
    {
        $s = trim($s);
        if (mb_strlen($s) <= $max) return $s;
        return mb_substr($s, 0, $max - 1) . '…';
    }
}
