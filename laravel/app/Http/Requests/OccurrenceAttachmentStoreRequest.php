<?php

namespace App\Http\Requests;

use App\Http\Utils\PhpLimitUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * RF04 — o envio da imagem, em multipart/form-data no campo `image`.
 *
 * O tamanho máximo é o menor entre o configurado e o que o php.ini do host
 * aceita: anunciar mais do que o servidor recebe faria o PHP descartar o
 * arquivo antes desta validação, e a pessoa leria "selecione a imagem" logo
 * depois de selecioná-la.
 *
 * A extensão é conferida aqui só como primeira barreira, barata. Quem decide
 * se o arquivo é imagem de verdade são os bytes, na Action.
 */
class OccurrenceAttachmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $extensoes = implode(',', config('uploads.occurrence_attachments.extensions'));

        return [
            'image' => "required|file|max:{$this->limiteKb()}|extensions:{$extensoes}",
        ];
    }

    public function messages(): array
    {
        $limite = round($this->limiteKb() / 1024, 1);

        return [
            'image.required' => 'Selecione a imagem a anexar.',
            'image.file' => 'O envio da imagem falhou; tente novamente.',
            'image.uploaded' => "A imagem não chegou ao servidor. Ela pode ter passado do limite de {$limite} MB.",
            'image.max' => "A imagem deve ter no máximo {$limite} MB.",
            'image.extensions' => 'Envie uma imagem JPG, PNG ou WEBP.',
        ];
    }

    private function limiteKb(): int
    {
        return PhpLimitUtil::uploadMaxKilobytes((int) config('uploads.occurrence_attachments.max_kilobytes'));
    }
}
