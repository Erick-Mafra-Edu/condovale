<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Implantação remota
    |--------------------------------------------------------------------------
    |
    | O plano gratuito do InfinityFree não dá acesso SSH, então não existe onde
    | digitar `php artisan migrate`. A rota POST /api/deploy/migrate executa a
    | carga inicial pelo próprio HTTP, protegida pelo token abaixo.
    |
    | A operação é destrutiva: derruba todas as tabelas antes de recriá-las.
    | Deixe DEPLOY_ENABLED=false fora da janela de implantação — com a chave
    | desligada a rota responde 404 e nem revela que existe.
    |
    */

    'enabled' => (bool) env('DEPLOY_ENABLED', false),

    'token' => env('DEPLOY_TOKEN'),

    // Abaixo deste tamanho o token é curto demais para resistir a tentativa em
    // massa, e a rota se recusa a funcionar em vez de dar falsa sensação de
    // proteção.
    'token_min_length' => 32,

    // Folga pedida ao PHP para o migrate:fresh --seed. O host compartilhado
    // pode impor um limite menor e ignorar este valor.
    'timeout' => (int) env('DEPLOY_TIMEOUT', 300),

];
