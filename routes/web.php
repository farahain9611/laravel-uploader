<?php

use App\Http\Controllers\CsvUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CsvUploadController::class, 'index'])->name('home');
Route::post('/upload', [CsvUploadController::class, 'store'])->name('upload.store');
Route::get('/status', [CsvUploadController::class, 'status'])->name('upload.status');
