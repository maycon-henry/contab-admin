<?php

use App\Http\Controllers\ContatoComercialController;
use App\Http\Controllers\ConviteController;
use Illuminate\Support\Facades\Route;

// regras.md RN-001 / RN-002 — página pública
Route::view('/', 'landing')->name('landing');

// RN-004 — pedido de demonstração
Route::post('/demonstracao', [ContatoComercialController::class, 'store'])->name('demonstracao')->middleware('throttle:5,1');

// RN-304 — tela de login única (do painel do escritório); o redirecionamento depende do tipo de usuário
Route::redirect('/login', '/admin/login')->name('login');

// RN-231 — aceite de convite
Route::get('/convite/{token}', [ConviteController::class, 'mostrar'])->name('convite.mostrar');
Route::post('/convite/{token}', [ConviteController::class, 'aceitar'])->name('convite.aceitar')->middleware('throttle:10,1');
