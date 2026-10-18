<?php

namespace App\Http\Actions;

use App\Http\Utils\ImageUtil;
use App\Http\Utils\PhpLimitUtil;
use Illuminate\Support\Facades\Storage;

/**
 * Diz o que o servidor de fato oferece para receber imagens.
 *
 * Os limites do InfinityFree não estão documentados oficialmente: o que se
 * sabe vem de relatos de fórum, que divergem entre si e às vezes dizem que o
 * painel mostra uma configuração e o PHP roda com outra. Esta é a única forma
 * de saber, de dentro do servidor, qual é o teto de upload, se há GD para
 * regravar as fotos e quanto disco ainda resta.
 */
class CheckUploadSupportAction
{
    /**
     * @return array<string, mixed>
     */
    public static function execute(): array
    {
        $config = config('uploads.occurrence_attachments');
        $disco = Storage::disk($config['disk']);
        $raiz = $disco->path('');

        return [
            'php' => [
                'file_uploads' => (bool) ini_get('file_uploads'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_file_uploads' => ini_get('max_file_uploads'),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'limite_efetivo_kb' => PhpLimitUtil::uploadMaxKilobytes((int) $config['max_kilobytes']),
            'extensoes' => [
                'gd' => extension_loaded('gd'),
                'exif' => extension_loaded('exif'),
                'fileinfo' => extension_loaded('fileinfo'),
            ],
            'regrava' => [
                'jpeg' => ImageUtil::canReencode(IMAGETYPE_JPEG),
                'png' => ImageUtil::canReencode(IMAGETYPE_PNG),
                'webp' => ImageUtil::canReencode(IMAGETYPE_WEBP),
            ],
            'disco' => [
                'gravavel' => is_dir($raiz) ? is_writable($raiz) : is_writable(dirname($raiz)),
                // disk_free_space costuma estar desligada em hospedagem
                // compartilhada; nulo aqui quer dizer "o host não informa".
                'livre_mb' => self::espacoLivre($raiz),
                'anexos_gravados' => is_dir($raiz) ? count($disco->allFiles()) : 0,
            ],
        ];
    }

    private static function espacoLivre(string $raiz): ?float
    {
        if (! function_exists('disk_free_space')) {
            return null;
        }

        $livre = @disk_free_space(is_dir($raiz) ? $raiz : dirname($raiz));

        return $livre === false ? null : round($livre / 1024 / 1024, 1);
    }
}
