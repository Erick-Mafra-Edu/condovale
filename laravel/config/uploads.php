<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Anexos de ocorrência — RF04
    |--------------------------------------------------------------------------
    |
    | Os números foram escolhidos pelo InfinityFree, onde o sistema roda:
    |
    | - O tamanho real aceito é o MENOR entre `max_kilobytes` e o que o php.ini
    |   do host permite (upload_max_filesize e post_max_size). No plano gratuito
    |   esse teto fica no php.ini mestre e não pode ser alterado; passar dele faz
    |   o PHP descartar o arquivo antes de a aplicação vê-lo.
    | - `max_per_occurrence` existe por causa do limite de inodes da conta, por
    |   volta de 30 mil arquivos no plano gratuito. Cada imagem é um arquivo, e
    |   ao chegar a 100% o servidor para de aceitar arquivo novo de qualquer
    |   tipo — inclusive o cache e os logs da aplicação.
    | - `max_dimension` reduz a foto de celular, de alguns MB, para algumas
    |   centenas de KB: é o que cabe em 5 GB de disco e ainda se lê na tela.
    | - `max_megapixels` protege a memória: decodificar uma foto ocupa cerca de
    |   4 bytes por pixel, e o processo não pode estourar o memory_limit.
    |
    */

    'occurrence_attachments' => [
        'disk' => 'occurrence_attachments',
        'max_kilobytes' => (int) env('OCCURRENCE_ATTACHMENT_MAX_KB', 5120),
        'max_per_occurrence' => (int) env('OCCURRENCE_ATTACHMENT_MAX_PER_OCCURRENCE', 5),
        'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_dimension' => (int) env('OCCURRENCE_ATTACHMENT_MAX_DIMENSION', 1920),
        'max_megapixels' => (int) env('OCCURRENCE_ATTACHMENT_MAX_MEGAPIXELS', 16),
        'jpeg_quality' => 82,
        // Desligar faz a aplicação guardar o original — já conferido pelos
        // bytes — em vez de regravar. É o mesmo caminho que ela toma sozinha
        // num servidor sem GD; existe para quem opera o servidor poder
        // escolhê-lo se o GD de lá se comportar mal.
        'reencode' => (bool) env('OCCURRENCE_ATTACHMENT_REENCODE', true),
    ],

];
