<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PhoneController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DeviceController;

// DEVICE
Route::post('/device/register', [DeviceController::class, 'register']);
Route::post('/device/user', [DeviceController::class, 'createUser']);

// NOTIFICATION
Route::post('/notification/respond/{notification:id_transaction}', [NotificationController::class, 'respond']);

// PHONE
Route::post('/phone/register', [PhoneController::class, 'register']);
Route::post('/phone/notify', [PhoneController::class, 'notify']);
Route::post('/phone/export', [PhoneController::class, 'export']);
Route::post('/phone/import', [PhoneController::class, 'import']);

// VERSION
Route::get('/version/get', [VersionController::class, 'get']);
