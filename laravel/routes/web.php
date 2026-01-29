<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\cv\CvController;
use App\Http\Controllers\exam\TempStudentController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::post('/users/profile-picture', [AuthController::class, 'updateProfilePicture'])
    ->name('users.profile.picture.update');
Route::get('/users/profile-picture', function () {
    return view('update-picture');
})->name('users.picture.form');
Route::get(
    '/external_exam/temp-student/{id}/profile-picture',
    [TempStudentController::class, 'profilePicture']
);

Route::get('/cvs/profile_picture/{cv}', [CvController::class, 'showProfilePicture'])->name('cvs.profile-picture')->middleware('auth:sanctum', 'role:Admin');