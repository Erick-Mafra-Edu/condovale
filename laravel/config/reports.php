<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Relatórios operacionais — RF06
    |--------------------------------------------------------------------------
    |
    | A exportação devolve todas as ocorrências do período numa resposta só,
    | para o PDF e o Excel saírem completos (RN12). Sem teto, um período de
    | vários anos montaria milhares de linhas na memória de uma hospedagem que
    | corta o script em 60 segundos. Acima do teto, a API pede um período
    | menor em vez de arriscar uma resposta que morre no meio.
    |
    */

    'occurrences' => [
        'max_export_rows' => 5000,
    ],

    'reservations' => [
        'max_export_rows' => 5000,
    ],

    // RNF04 / RN16 — a trilha cresce a cada operação, mais rápido que qualquer
    // outra tabela; o mesmo teto vale para exportar a auditoria.
    'audit' => [
        'max_export_rows' => 5000,
    ],

];
