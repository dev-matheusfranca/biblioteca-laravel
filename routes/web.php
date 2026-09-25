<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutorController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CirculationPolicyController;
use App\Http\Controllers\CommunicationOperationsController;
use App\Http\Controllers\EquipeController;
use App\Http\Controllers\ExemplarController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeitorController;
use App\Http\Controllers\LivroController;
use App\Http\Controllers\LocacaoController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PersonalAccessTokenController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PortalNoticeController;
use App\Http\Controllers\ReconciliacaoLivroController;
use App\Http\Controllers\ReservaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('catalogo', [CatalogoController::class, 'index'])->name('catalogo.index');
Route::get('catalogo/{livro}', [CatalogoController::class, 'show'])->name('catalogo.show');

Route::get('login', [AuthController::class, 'showLogin'])->middleware('request.context')->name('login');
Route::post('login', [AuthController::class, 'login'])->middleware(['throttle:login', 'request.context'])->name('login.post');
Route::get('register', [AuthController::class, 'showRegister'])->middleware('request.context')->name('register');
Route::post('register', [AuthController::class, 'register'])->middleware(['throttle:registration', 'request.context'])->name('register.post');
Route::post('logout', [AuthController::class, 'logout'])->middleware(['auth', 'request.context'])->name('logout');

Route::get('esqueci-a-senha', [PasswordResetController::class, 'create'])->middleware('request.context')->name('password.request');
Route::post('esqueci-a-senha', [PasswordResetController::class, 'store'])->middleware(['throttle:5,1', 'request.context'])->name('password.email');
Route::get('redefinir-senha/{token}', [PasswordResetController::class, 'edit'])->middleware('request.context')->name('password.reset');
Route::post('redefinir-senha', [PasswordResetController::class, 'update'])->middleware(['throttle:5,1', 'request.context'])->name('password.update');

Route::middleware(['auth', 'active', 'request.context'])->group(function () {
    Route::get('minha-conta', [PortalController::class, 'index'])->name('portal.index');
    Route::get('minha-conta/emprestimos/{locacao}', [PortalController::class, 'show'])->name('portal.emprestimos.show');
    Route::post('minha-conta/emprestimos/{locacao}/renovar', [PortalController::class, 'renovar'])->name('portal.emprestimos.renovar');
    Route::post('catalogo/{livro}/reservas', [ReservaController::class, 'store'])->name('reservas.store');
    Route::delete('minha-conta/reservas/{reserva}', [ReservaController::class, 'destroy'])->name('reservas.destroy');
    Route::get('minha-conta/avisos', [PortalNoticeController::class, 'index'])->name('avisos.index');
    Route::patch('minha-conta/avisos/{aviso}/ler', [PortalNoticeController::class, 'read'])->name('avisos.read');
    Route::get('minha-conta/tokens', [PersonalAccessTokenController::class, 'index'])->name('tokens.index');
    Route::post('minha-conta/tokens', [PersonalAccessTokenController::class, 'store'])->middleware('throttle:5,1')->name('tokens.store');
    Route::delete('minha-conta/tokens/{token}', [PersonalAccessTokenController::class, 'destroy'])->name('tokens.destroy');

    Route::middleware('can:manage-library')->group(function () {
        Route::resource('autores', AutorController::class)->parameters(['autores' => 'autor']);
        Route::resource('categorias', CategoriaController::class)->parameters(['categorias' => 'categoria']);
        Route::resource('livros', LivroController::class)->parameters(['livros' => 'livro']);
        Route::resource('locacoes', LocacaoController::class)
            ->parameters(['locacoes' => 'locacao'])
            ->except(['edit', 'update', 'destroy']);
        Route::post('locacoes/{locacao}/devolver', [LocacaoController::class, 'devolver'])->name('locacoes.devolver');
        Route::post('locacoes/{locacao}/encerrar-por-perda', [LocacaoController::class, 'encerrarPorPerda'])->name('locacoes.encerrar-perda');
        Route::post('locacoes/{locacao}/renovar', [LocacaoController::class, 'renovar'])->name('locacoes.renovar');
        Route::get('reservas', [ReservaController::class, 'index'])->name('reservas.index');
        Route::delete('reservas/{reserva}', [ReservaController::class, 'cancelar'])->name('reservas.cancelar');
        Route::get('livros/{livro}/exemplares', [ExemplarController::class, 'index'])->name('exemplares.index');
        Route::get('livros/{livro}/reconciliacao', [ReconciliacaoLivroController::class, 'edit'])->name('livros.reconciliacao.edit');
        Route::post('livros/{livro}/reconciliacao', [ReconciliacaoLivroController::class, 'store'])->name('livros.reconciliacao.store');
        Route::post('livros/{livro}/exemplares', [ExemplarController::class, 'store'])->name('exemplares.store');
        Route::patch('exemplares/{exemplar}/condicao', [ExemplarController::class, 'updateCondition'])->name('exemplares.condicao.update');
        Route::resource('leitores', LeitorController::class)
            ->parameters(['leitores' => 'leitor'])
            ->only(['index', 'create', 'store', 'edit', 'update']);
    });

    Route::middleware('can:manage-team')->group(function () {
        Route::get('equipe', [EquipeController::class, 'index'])->name('equipe.index');
        Route::patch('equipe/{user}', [EquipeController::class, 'update'])->name('equipe.update');
        Route::get('configuracoes/circulacao', [CirculationPolicyController::class, 'edit'])->name('configuracoes.circulacao.edit');
        Route::patch('configuracoes/circulacao', [CirculationPolicyController::class, 'update'])->name('configuracoes.circulacao.update');
        Route::get('operacao/comunicacoes', [CommunicationOperationsController::class, 'index'])->name('operacao.comunicacoes.index');
        Route::post('operacao/comunicacoes/{outbox}/reprocessar', [CommunicationOperationsController::class, 'reprocessar'])->name('operacao.comunicacoes.reprocessar');
    });
});

require __DIR__.'/bibliography.php';
require __DIR__.'/reports.php';
require __DIR__.'/operations.php';
