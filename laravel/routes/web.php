<?php

use Illuminate\Support\Facades\Route;

/*
| O CondoVale é uma API: a interface é servida pelo frontend Nuxt.
| A raiz apenas identifica o serviço; a saúde da aplicação fica em /up.
*/
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api'),
    'health' => url('/up'),
]));
