<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
| O CondoVale é uma API: a interface é servida pelo frontend Nuxt.
| A raiz apenas identifica o serviço; a saúde da aplicação fica em /up.
|
| Nenhuma rota aqui ou em api.php pode ser uma closure: `route:cache` não
| serializa closure, e é o cache de rotas que tira do caminho de cada
| requisição a montagem da tabela de rotas.
*/
Route::get('/', [HomeController::class, 'index']);
