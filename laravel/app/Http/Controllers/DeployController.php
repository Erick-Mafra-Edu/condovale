<?php

namespace App\Http\Controllers;

use App\Http\Actions\CheckHashingSupportAction;
use App\Http\Actions\RunFreshMigrationAction;
use App\Http\Requests\DeployMigrateRequest;
use App\Services\MessageService;

class DeployController extends Controller
{
    /**
     * Recreates the whole schema and loads the seed data.
     *
     * Não há CreateLogAction aqui, e é de propósito: `logs` é uma das tabelas
     * que o migrate:fresh derruba, então o registro seria apagado pela própria
     * operação que ele documenta. A saída do artisan volta na resposta e é
     * essa a evidência da execução.
     *
     * Também não há transação: DDL não é transacional no MySQL — cada CREATE
     * TABLE faz commit implícito — e um rollback aqui daria falsa garantia.
     */
    /**
     * Reports whether the host's PHP meets RNF02.
     *
     * Fica atrás do token de implantação porque descreve a configuração do
     * servidor: versão do PHP, limite de memória e algoritmos disponíveis são
     * exatamente o que alguém sondando o domínio gostaria de saber.
     */
    public function hashing()
    {
        return MessageService::success('Diagnóstico de hashing.', CheckHashingSupportAction::execute());
    }

    public function migrate(DeployMigrateRequest $request)
    {
        try {
            $output = RunFreshMigrationAction::execute();

            return MessageService::success('Base de dados recriada e populada.', $output);
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }
    }
}
