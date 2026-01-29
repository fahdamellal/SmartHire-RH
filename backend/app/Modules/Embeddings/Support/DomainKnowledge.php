<?php

namespace App\Modules\Embeddings\Support;

class DomainKnowledge
{
    /**
     * 🆕 Dictionnaire de synonymes par domaine
     * Permet de gérer les cas particuliers de chaque métier
     */
    public function expandTerm(string $term): array
    {
        $term = mb_strtolower(trim($term));
        
        // IT / Tech
        $techMap = [
            'react' => ['react', 'reactjs', 'react.js', 'react js'],
            'reactjs' => ['react', 'reactjs', 'react.js'],
            'angular' => ['angular', 'angularjs', 'angular.js'],
            'vue' => ['vue', 'vuejs', 'vue.js'],
            'node' => ['node', 'nodejs', 'node.js'],
            'javascript' => ['javascript', 'js', 'ecmascript'],
            'typescript' => ['typescript', 'ts'],
            'spring' => ['spring', 'spring boot', 'springboot'],
            'laravel' => ['laravel', 'php laravel'],
            'django' => ['django', 'python django'],
            'postgresql' => ['postgresql', 'postgres', 'psql'],
            'mongodb' => ['mongodb', 'mongo'],
            'kubernetes' => ['kubernetes', 'k8s'],
            'docker' => ['docker', 'conteneurisation', 'containerization'],
            'développeur' => ['développeur', 'developpeur', 'developer', 'dev'],
            'fullstack' => ['fullstack', 'full stack', 'full-stack'],
            'backend' => ['backend', 'back-end', 'back end'],
            'frontend' => ['frontend', 'front-end', 'front end'],
            'devops' => ['devops', 'dev ops'],
            'java' => ['java', 'jdk', 'jvm'],
            'python' => ['python', 'py'],
            'php' => ['php', 'hypertext preprocessor'],
        ];

        // Bâtiment / Artisanat
        $batimentMap = [
            'plombier' => ['plombier', 'plomberie', 'installateur sanitaire', 'plombier chauffagiste'],
            'électricien' => ['électricien', 'electricien', 'électricité', 'installation électrique', 'electricite'],
            'maçon' => ['maçon', 'macon', 'maçonnerie', 'maconnerie'],
            'peintre' => ['peintre', 'peinture', 'peintre en bâtiment', 'peintre batiment'],
            'menuisier' => ['menuisier', 'menuiserie', 'ébéniste', 'ebeniste'],
            'chauffagiste' => ['chauffagiste', 'chauffage', 'climatisation', 'cvc'],
            'carreleur' => ['carreleur', 'carrelage'],
            'couvreur' => ['couvreur', 'couverture', 'toiture'],
        ];

        // Santé
        $santeMap = [
            'médecin' => ['médecin', 'medecin', 'docteur', 'praticien'],
            'infirmier' => ['infirmier', 'infirmière', 'infirmiere', 'ide'],
            'chirurgien' => ['chirurgien', 'chirurgienne', 'chirurgie'],
            'pharmacien' => ['pharmacien', 'pharmacienne', 'pharmacie'],
            'kinésithérapeute' => ['kinésithérapeute', 'kinesitherapeute', 'kiné', 'kine', 'physiothérapeute', 'physio'],
            'dentiste' => ['dentiste', 'chirurgien-dentiste', 'odontologie'],
            'aide-soignant' => ['aide-soignant', 'aide soignant', 'as'],
            'sage-femme' => ['sage-femme', 'sage femme', 'maïeuticien', 'maieuticien'],
        ];

        // Commerce / Vente
        $commerceMap = [
            'commercial' => ['commercial', 'commerciale', 'vendeur', 'vendeuse', 'vente'],
            'directeur commercial' => ['directeur commercial', 'directrice commerciale', 'responsable commercial', 'dir com'],
            'chargé clientèle' => ['chargé de clientèle', 'chargée de clientèle', 'relation client', 'charge clientele'],
            'télévendeur' => ['télévendeur', 'televendeur', 'télévente', 'vente téléphonique', 'televente'],
            'technico-commercial' => ['technico-commercial', 'technico commercial', 'ingénieur commercial', 'ingenieur commercial'],
        ];

        // Marketing / Communication
        $marketingMap = [
            'community manager' => ['community manager', 'cm', 'gestionnaire communauté', 'social media manager', 'smm'],
            'graphiste' => ['graphiste', 'designer graphique', 'infographiste', 'da', 'directeur artistique'],
            'rédacteur' => ['rédacteur', 'redacteur', 'content writer', 'copywriter', 'rédacteur web'],
            'chef de projet' => ['chef de projet', 'chargé de projet', 'charge de projet', 'project manager', 'pm'],
            'traffic manager' => ['traffic manager', 'responsable acquisition', 'media buyer'],
        ];

        // Transport / Logistique
        $transportMap = [
            'chauffeur' => ['chauffeur', 'conducteur', 'driver'],
            'livreur' => ['livreur', 'livreur coursier', 'delivery', 'coursier'],
            'magasinier' => ['magasinier', 'magasinière', 'gestionnaire stock', 'préparateur commandes', 'preparateur'],
            'logisticien' => ['logisticien', 'responsable logistique', 'supply chain'],
        ];

        // RH / Administration
        $rhMap = [
            'rh' => ['rh', 'ressources humaines', 'human resources', 'hr', 'drh'],
            'assistant' => ['assistant', 'assistante', 'secrétaire', 'secretaire', 'assistant administratif'],
            'comptable' => ['comptable', 'comptabilité', 'comptabilite', 'aide-comptable'],
            'contrôleur de gestion' => ['contrôleur de gestion', 'controleur de gestion', 'contrôle de gestion', 'controle de gestion'],
        ];

        // Éducation
        $educationMap = [
            'enseignant' => ['enseignant', 'enseignante', 'professeur', 'prof', 'formateur', 'formatrice'],
            'éducateur' => ['éducateur', 'educateur', 'éducatrice', 'educatrice', 'éducateur spécialisé'],
            'formateur' => ['formateur', 'formatrice', 'coach', 'trainer'],
        ];

        // Restauration / Hôtellerie
        $restoMap = [
            'cuisinier' => ['cuisinier', 'cuisinière', 'cuisiniere', 'chef cuisinier', 'chef'],
            'serveur' => ['serveur', 'serveuse', 'serveur restaurant'],
            'réceptionniste' => ['réceptionniste', 'receptionniste', 'réception', 'reception'],
            'commis de cuisine' => ['commis de cuisine', 'commis', 'aide-cuisinier'],
        ];

        // Fusionner tous les domaines
        $allMaps = array_merge(
            $techMap,
            $batimentMap,
            $santeMap,
            $commerceMap,
            $marketingMap,
            $transportMap,
            $rhMap,
            $educationMap,
            $restoMap
        );

        // Chercher le terme et retourner les synonymes
        if (isset($allMaps[$term])) {
            return $allMaps[$term];
        }

        // Chercher si le terme est dans une liste de synonymes
        foreach ($allMaps as $mainTerm => $synonyms) {
            if (in_array($term, $synonyms, true)) {
                return $synonyms;
            }
        }

        // Si pas trouvé, retourner le terme seul
        return [$term];
    }

    /**
     * 🆕 Détecte automatiquement le domaine d'une demande
     */
    public function detectDomain(string $message): ?string
    {
        $m = mb_strtolower($message);

        // IT
        $itKeywords = ['développeur', 'developpeur', 'developer', 'programmeur', 'software', 'web', 'mobile', 'react', 'java', 'python', 'php', 'node', 'angular', 'vue', 'laravel', 'django', 'fullstack', 'backend', 'frontend', 'devops', 'data', 'cloud'];
        foreach ($itKeywords as $kw) {
            if (str_contains($m, $kw)) return 'IT';
        }

        // Santé
        $santeKeywords = ['médecin', 'medecin', 'infirmier', 'chirurgien', 'pharmacien', 'kiné', 'kine', 'dentiste', 'docteur', 'santé', 'sante', 'hôpital', 'hopital', 'clinique', 'aide-soignant'];
        foreach ($santeKeywords as $kw) {
            if (str_contains($m, $kw)) return 'Santé';
        }

        // Bâtiment
        $batimentKeywords = ['plombier', 'électricien', 'electricien', 'maçon', 'macon', 'peintre', 'menuisier', 'chauffagiste', 'bâtiment', 'batiment', 'construction', 'rénovation', 'renovation', 'carreleur', 'couvreur'];
        foreach ($batimentKeywords as $kw) {
            if (str_contains($m, $kw)) return 'Bâtiment';
        }

        // Commerce
        $commerceKeywords = ['commercial', 'vente', 'vendeur', 'vendeuse', 'b2b', 'b2c', 'prospection', 'chiffre affaires', 'ventes'];
        foreach ($commerceKeywords as $kw) {
            if (str_contains($m, $kw)) return 'Commerce';
        }

        // Marketing
        $marketingKeywords = ['marketing', 'communication', 'community manager', 'graphiste', 'designer', 'publicité', 'pub', 'réseaux sociaux', 'social media'];
        foreach ($marketingKeywords as $kw) {
            if (str_contains($m, $kw)) return 'Marketing';
        }

        // Transport/Logistique
        $transportKeywords = ['chauffeur', 'livreur', 'transport', 'logistique', 'magasinier', 'préparateur', 'preparateur'];
        foreach ($transportKeywords as $kw) {
            if (str_contains($m, $kw)) return 'Transport';
        }

        // Restauration
        $restoKeywords = ['cuisinier', 'serveur', 'restaurant', 'hôtel', 'hotel', 'réception', 'reception', 'commis'];
        foreach ($restoKeywords as $kw) {
            if (str_contains($m, $kw)) return 'Restauration';
        }

        return null; // Domaine inconnu → reste générique
    }
}
