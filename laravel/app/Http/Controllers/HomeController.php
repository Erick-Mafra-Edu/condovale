<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * A raiz da API: apenas identifica o serviço.
 *
 * Existe como controller, e não como closure na rota, porque `route:cache` não
 * consegue serializar closure — e sem o cache de rotas o framework monta a
 * tabela de rotas a cada requisição, custo que pesa na hospedagem compartilhada
 * (UC-T01 / RNF01).
 *
 * A resposta não passa pelo MessageService de propósito: não é um recurso da
 * API, é o cartão de visita de quem abriu o domínio no navegador.
 */
class HomeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'name' => config('app.name'),
            'api' => url('/api'),
            'health' => url('/up'),
        ]);
    }
}
