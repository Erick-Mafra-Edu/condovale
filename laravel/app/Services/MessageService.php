<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;

class MessageService
{
    /**
     * Method that returns the formatted error
     */
    public static function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['status' => false, 'error' => $message], $status);
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
