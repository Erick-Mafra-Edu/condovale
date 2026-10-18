<?php

namespace App\Http\Actions;

use App\Enums\OccurrenceHistoryType;
use App\Enums\TypeLogEnum;
use App\Exceptions\BusinessRuleException;
use App\Http\Utils\ImageUtil;
use App\Models\Occurrence;
use App\Models\OccurrenceAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * RF04 — anexar imagem ao chamado.
 *
 * Quem anexa é quem enxerga o chamado: o morador no próprio, o funcionário no
 * que atende — uma foto do conserto feito —, o síndico e a administração em
 * qualquer um. Vale enquanto o chamado está aberto; Concluído e Cancelado não
 * recebem mais nada, porque o que estava em discussão já foi decidido.
 *
 * O arquivo é conferido pelos bytes, não pela extensão, e regravado como
 * JPEG quando o servidor tem GD: ver ImageUtil para o porquê.
 */
class StoreOccurrenceAttachmentAction
{
    public static function execute(Occurrence $occurrence, User $author, UploadedFile $file): OccurrenceAttachment
    {
        CheckOccurrenceAccessAction::execute($occurrence, $author);

        if ($occurrence->status->isFinal()) {
            throw BusinessRuleException::conflict(
                "Não é possível anexar imagens a um chamado {$occurrence->status->label()}."
            );
        }

        $config = config('uploads.occurrence_attachments');

        self::checkLimite($occurrence, (int) $config['max_per_occurrence']);

        $imagem = self::preparar($file, $config);

        $nome = self::nomeParaExibir($file->getClientOriginalName(), $imagem['extension']);
        $path = $occurrence->id.'/'.Str::uuid().'.'.$imagem['extension'];
        $disco = Storage::disk($config['disk']);

        $disco->put($path, $imagem['contents']);

        // O arquivo já está no disco, e o rollback do banco não o apaga. Se
        // qualquer coisa daqui em diante falhar, ele sai junto — senão ficaria
        // um arquivo sem registro, ocupando um inode para sempre.
        try {
            $attachment = OccurrenceAttachment::create([
                'occurrence_id' => $occurrence->id,
                'uploaded_by' => $author->id,
                'path' => $path,
                'original_name' => $nome,
                'mime_type' => $imagem['mime'],
                'size' => strlen($imagem['contents']),
                'width' => $imagem['width'],
                'height' => $imagem['height'],
            ]);

            CreateOccurrenceHistoryAction::execute(
                $occurrence,
                $author,
                OccurrenceHistoryType::Comment,
                null,
                null,
                "Imagem anexada: {$nome}.",
            );

            $description = "Imagem {$attachment->id} anexada à ocorrência {$occurrence->id} {$occurrence->title} por {$author->name}.";
            CreateLogAction::execute(TypeLogEnum::OCCURRENCE->value, $description, [
                'arquivo' => $nome,
                'bytes' => $attachment->size,
                'regravada' => $imagem['reencoded'],
            ]);
        } catch (\Throwable $th) {
            $disco->delete($path);

            throw $th;
        }

        return $attachment;
    }

    /**
     * O limite por chamado existe por causa dos inodes do InfinityFree: cada
     * imagem é um arquivo, e a conta para de aceitar arquivo novo de qualquer
     * tipo ao chegar no teto.
     */
    private static function checkLimite(Occurrence $occurrence, int $maximo): void
    {
        $quantidade = $occurrence->attachments()->count();

        if ($quantidade >= $maximo) {
            throw BusinessRuleException::conflict(
                "Este chamado já tem {$quantidade} imagens, o máximo permitido."
            );
        }
    }

    /**
     * Confere o arquivo e decide como gravá-lo.
     *
     * Com GD, regrava como JPEG. Sem GD — que alguns relatos dizem faltar no
     * InfinityFree —, guarda o original, já conferido pelos bytes: perde-se a
     * redução e a limpeza dos metadados, mas o morador não fica sem poder
     * anexar. Com GD mas sem memória para esta foto, recusa: guardar o
     * original aí seria escolher em silêncio a versão pior.
     *
     * @param  array<string, mixed>  $config
     * @return array{contents: string, mime: string, extension: string, width: int, height: int, reencoded: bool}
     */
    private static function preparar(UploadedFile $file, array $config): array
    {
        $caminho = $file->getRealPath();
        $imagem = ImageUtil::inspect($caminho);

        if ($imagem === null) {
            throw BusinessRuleException::unprocessable(
                'O arquivo enviado não é uma imagem JPG, PNG ou WEBP.'
            );
        }

        $megapixels = $imagem['width'] * $imagem['height'] / 1_000_000;

        if ($megapixels > $config['max_megapixels']) {
            throw BusinessRuleException::unprocessable(sprintf(
                'A imagem tem %.1f megapixels; o máximo é %d. Envie uma foto em resolução menor.',
                $megapixels,
                $config['max_megapixels']
            ));
        }

        if (! $config['reencode'] || ! ImageUtil::canReencode($imagem['type'])) {
            return [
                'contents' => (string) file_get_contents($caminho),
                'mime' => $imagem['mime'],
                'extension' => $imagem['extension'],
                'width' => $imagem['width'],
                'height' => $imagem['height'],
                'reencoded' => false,
            ];
        }

        if (! ImageUtil::fitsInMemory($imagem['width'], $imagem['height'], $config['max_dimension'])) {
            throw BusinessRuleException::unprocessable(
                'A imagem é grande demais para ser processada pelo servidor. Envie uma foto em resolução menor.'
            );
        }

        $regravada = ImageUtil::reencodeAsJpeg($caminho, $imagem['type'], $config['max_dimension'], $config['jpeg_quality']);

        if ($regravada === null) {
            throw BusinessRuleException::unprocessable(
                'Não foi possível ler a imagem: o arquivo pode estar corrompido.'
            );
        }

        return $regravada + ['mime' => 'image/jpeg', 'extension' => 'jpg', 'reencoded' => true];
    }

    /**
     * O nome enviado serve só para exibição, e mesmo assim precisa de limpeza.
     *
     * Tira caminho (`../../`), caracteres de controle e excesso de tamanho. A
     * extensão passa a ser a do arquivo como ficou gravado: "print.png"
     * regravado como JPEG baixaria como "print.jpg".
     */
    private static function nomeParaExibir(string $original, string $extensao): string
    {
        $base = pathinfo(basename(str_replace('\\', '/', $original)), PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $base));
        $base = Str::limit($base, 120, '');

        return ($base !== '' ? $base : 'imagem').'.'.$extensao;
    }
}
