<?php

use App\Http\Controllers\Api\MemberApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['api.key', 'throttle:60,1'])
    ->group(function () {
        Route::get('/members', [MemberApiController::class, 'index']);
        Route::get('/members/verify/{member_no}', [MemberApiController::class, 'verify']);
        Route::get('/members/{identifier}', [MemberApiController::class, 'show']);
        Route::post('/members', [MemberApiController::class, 'store']);
    });
