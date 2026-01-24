<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatSearchController;
use App\Http\Controllers\CvController;
use App\Http\Controllers\DemanderController;

Route::post('/chat/search', [ChatSearchController::class, 'search']);
Route::get('/cv/{id_file}', [CvController::class, 'show']);
Route::post('/demander/{id_demande}/{id_file}/viewed', [DemanderController::class, 'markViewed']);
Route::post('/demander/{id_demande}/{id_file}/interview', [DemanderController::class, 'markInterview']);
Route::get('/demander/{id_demande}', [DemanderController::class, 'listByDemande']);


