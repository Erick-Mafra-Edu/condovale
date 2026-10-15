<?php

namespace App\Http\Controllers;

use App\Http\Actions\CheckHashingSupportAction;
use App\Http\Actions\CheckOutboundMailAction;
use App\Http\Actions\CheckUploadSupportAction;
use App\Http\Actions\OptimizeApplicationAction;
use App\Http\Actions\RunFreshMigrationAction;
use App\Http\Requests\DeployMigrateRequest;
use App\Http\Requests\DeployOptimizeRequest;
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

    /**
     * Gera os caches de inicialização, o que no servidor com SSH seria
     * `php artisan optimize`.
     *
     * Vale a pena por medição, não por hábito: a inicialização do framework
     * responde por cerca de um quarto do tempo de resposta na hospedagem, e as
     * consultas das listagens, por menos de cinco por cento.
     *
     * Precisa ser chamada depois de cada publicação: o publicador apaga estes
     * caches ao enviar arquivos, porque cache de configuração velho faz a
     * aplicação rodar com o `.env` anterior sem dar erro nenhum.
     */
    public function optimize(DeployOptimizeRequest $request)
    {
        $item = (object) $request->validated();

        try {
            $saida = ($item->clear ?? false)
                ? OptimizeApplicationAction::clear()
                : OptimizeApplicationAction::execute();

            return MessageService::success('Caches de inicialização atualizados.', $saida);
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }
    }

    public function hashing()
    {
        return MessageService::success('Diagnóstico de hashing.', CheckHashingSupportAction::execute());
    }

    /**
     * Reports whether the host can send mail at all, and through which port.
     *
     * A hospedagem restringe a função mail() e bloqueia boa parte das portas de
     * saída. Saber quais estão abertas decide se o envio da senha temporária
     * tem como existir — e isso só se descobre de dentro do servidor.
     */
    public function mail()
    {
        return MessageService::success('Diagnóstico de envio de e-mail.', CheckOutboundMailAction::execute());
    }

    /**
     * Reports what the host offers for receiving images.
     *
     * Os limites do InfinityFree só são conhecidos por relato de fórum, que
     * diverge; o teto de upload, a presença do GD e o disco livre só se sabem
     * de dentro do servidor.
     */
    public function uploads()
    {
        return MessageService::success('Diagnóstico de upload de imagens.', CheckUploadSupportAction::execute());
    }

    public function migrate(DeployMigrateRequest $request)
    {
        try {
            if (! $request->boolean('fresh', true)) {
                return MessageService::success('Migrations pendentes aplicadas.', RunFreshMigrationAction::pending());
            }

            $output = RunFreshMigrationAction::execute();

            return MessageService::success('Base de dados recriada e populada.', $output);
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }
    }
}
