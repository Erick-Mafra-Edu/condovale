<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;

class MessageService
{
    /**
     * Method that returns the formatted error
     *
     * A chave `message` acompanha a `error` porque é dela que o cliente tira o
     * texto exibido — o `throwable()` já devolvia as duas, e sem essa a recusa
     * de uma regra de negócio chegava à interface como erro genérico. O
     * CLAUDE.md manda unificar os dois formatos antes do primeiro controller,
     * que é onde este projeto está; a `error` continua para não quebrar quem
     * já a lia.
     */
    public static function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'error' => $message,
        ], $status);
    }

    /**
     * Method that returns the formatted success
     */
    public static function success(string $message, mixed $model = [], bool $pagination = false): JsonResponse
    {
        $infos = ['status' => true];

        if ($pagination) {
            $infos['amount'] = count($model);
            $infos['total'] = $model->total();
            $infos['data'] = $model->items();
        } elseif ($model) {
            $infos['message'] = $message;
            $infos['data'] = $model;
        }

        return response()->json($infos, 201);
    }

    public static function throwable(\Throwable $th): JsonResponse
    {
        $statusCode = ((int) $th->getCode() >= 400 && (int) $th->getCode() < 600) ? (int) $th->getCode() : 500;
        $message = $statusCode === 500 ? 'Erro ao salvar as informações na base de dados.' : $th->getMessage();

        return response()->json([
            'status' => false,
            'message' => $message,
            'code' => $statusCode,
            'error' => $th->getMessage(),
        ], $statusCode);
    }
}
