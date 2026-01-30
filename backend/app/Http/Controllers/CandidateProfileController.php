<?php

namespace App\Http\Controllers;

use App\Services\CandidateProfileService;
use Illuminate\Http\JsonResponse;

class CandidateProfileController extends Controller
{
    public function show(int $id_file, CandidateProfileService $svc): JsonResponse
    {
        $payload = $svc->getProfile($id_file);

        if (!($payload['ok'] ?? false)) {
            return response()->json($payload, $payload['status'] ?? 500);
        }

        return response()->json($payload);
    }
}
