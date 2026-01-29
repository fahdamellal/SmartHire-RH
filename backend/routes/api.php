<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatSearchController;
use App\Http\Controllers\CvController;
use App\Http\Controllers\DemanderController;
use App\Http\Controllers\LinkedInPostController;


Route::post('/chat/search', [ChatSearchController::class, 'search']);
Route::get('/cv/{id_file}', [CvController::class, 'show']);
Route::post('/demander/{id_demande}/{id_file}/viewed', [DemanderController::class, 'markViewed']);
Route::post('/demander/{id_demande}/{id_file}/interview', [DemanderController::class, 'markInterview']);
Route::get('/demander/{id_demande}', [DemanderController::class, 'listByDemande']);


Route::post('/linkedin/{id_post}/revise', [LinkedInPostController::class, 'revise']);


Route::post('/linkedin/revise', [LinkedInPostController::class, 'reviseByDemande']);
Route::get('demandes/{id_demande}/match', [MatchingController::class, 'matchByDemande']);
Route::post('matching/run', [MatchingController::class, 'run']);
