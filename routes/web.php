<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutorController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LivroController;
use App\Http\Controllers\LocacaoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login'])->name('login.post');
Route::get('register', [AuthController::class, 'showRegister'])->name('register');
Route::post('register', [AuthController::class, 'register'])->name('register.post');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::resource('autores', AutorController::class)->parameters(['autores' => 'autor']);
    Route::resource('categorias', CategoriaController::class)->parameters(['categorias' => 'categoria']);
    Route::resource('livros', LivroController::class)->parameters(['livros' => 'livro']);
    Route::resource('locacoes', LocacaoController::class)
        ->parameters(['locacoes' => 'locacao'])
        ->except(['edit', 'update', 'destroy']);
    Route::post('locacoes/{locacao}/devolver', [LocacaoController::class, 'devolver'])->name('locacoes.devolver');
});
