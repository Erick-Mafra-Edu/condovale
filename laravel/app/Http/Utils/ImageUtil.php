<?php

namespace App\Http\Utils;

use GdImage;

/**
 * Inspeciona e reprocessa as imagens enviadas pelos usuários.
 *
 * Reprocessar — decodificar e gravar de novo como JPEG — resolve quatro coisas
 * de uma vez:
 *
 * - **Tamanho.** A foto de celular sai de alguns MB para algumas centenas de
 *   KB, o que cabe nos 5 GB do plano gratuito.
 * - **Metadados.** O EXIF da câmera, que pode trazer as coordenadas de GPS de
 *   onde a foto foi tirada, não sobrevive à regravação.
 * - **Arquivo disfarçado.** Um arquivo que é imagem válida e também carrega
 *   HTML ou script escondido sai daqui só com os pixels.
 * - **Formato.** O navegador recebe sempre JPEG, sem depender de suporte a
 *   WEBP de quem estiver olhando.
 *
 * Depende do GD, que alguns relatos dizem faltar em contas do InfinityFree. Por
 * isso tudo aqui verifica antes de usar, e quem chama decide o que fazer
 * quando o reprocessamento não é possível. A rota /api/deploy/uploads diz o
 * que o servidor tem de fato.
 */
class ImageUtil
{
    /**
     * Os tipos aceitos, reconhecidos pelos bytes do arquivo.
     *
     * @var array<int, array{mime: string, extension: string, decoder: string}>
     */
    private const TIPOS = [
        IMAGETYPE_JPEG => ['mime' => 'image/jpeg', 'extension' => 'jpg', 'decoder' => 'imagecreatefromjpeg'],
        IMAGETYPE_PNG => ['mime' => 'image/png', 'extension' => 'png', 'decoder' => 'imagecreatefrompng'],
        IMAGETYPE_WEBP => ['mime' => 'image/webp', 'extension' => 'webp', 'decoder' => 'imagecreatefromwebp'],
    ];

    /**
     * Folga de memória além dos pixels, para o próprio GD e o framework.
     */
    private const FOLGA_DE_MEMORIA = 8 * 1024 * 1024;

    /**
     * Tipo e dimensões lidos da assinatura binária do arquivo.
     *
     * `getimagesize()` olha os bytes, não a extensão nem o tipo que o navegador
     * declarou — que são o que quem envia controla. E é do PHP, não do GD: o
     * tipo é conferido mesmo num servidor sem a extensão de imagem.
     *
     * @return array{width: int, height: int, type: int, mime: string, extension: string}|null
     */
    public static function inspect(string $path): ?array
    {
        $info = @getimagesize($path);

        if ($info === false || ! isset(self::TIPOS[$info[2]])) {
            return null;
        }

        return [
            'width' => (int) $info[0],
            'height' => (int) $info[1],
            'type' => (int) $info[2],
            'mime' => self::TIPOS[$info[2]]['mime'],
            'extension' => self::TIPOS[$info[2]]['extension'],
        ];
    }

    public static function canReencode(int $type): bool
    {
        return extension_loaded('gd')
            && isset(self::TIPOS[$type])
            && function_exists(self::TIPOS[$type]['decoder'])
            && function_exists('imagejpeg');
    }

    /**
     * Cabe na memória que o processo ainda tem?
     *
     * Decodificar ocupa cerca de 4 bytes por pixel na origem e outros tantos
     * no destino. Uma foto de 12 megapixels pede uns 60 MB; o teto é o
     * memory_limit do host. Estourá-lo derruba a requisição com erro fatal,
     * sem mensagem — por isso a conta é feita antes.
     */
    public static function fitsInMemory(int $width, int $height, int $maxDimension): bool
    {
        $disponivel = PhpLimitUtil::availableMemory();

        if ($disponivel === null) {
            return true;
        }

        [$larguraFinal, $alturaFinal] = self::fit($width, $height, $maxDimension);

        $necessario = ($width * $height + $larguraFinal * $alturaFinal) * 4 + self::FOLGA_DE_MEMORIA;

        return $necessario <= $disponivel;
    }

    /**
     * Dimensões para caber em `$max` no lado maior, mantendo a proporção.
     * Imagem menor que o limite não é ampliada.
     *
     * @return array{0: int, 1: int}
     */
    public static function fit(int $width, int $height, int $max): array
    {
        $maior = max($width, $height);

        if ($maior <= $max) {
            return [$width, $height];
        }

        $escala = $max / $maior;

        return [max(1, (int) round($width * $escala)), max(1, (int) round($height * $escala))];
    }

    /**
     * Grava de novo como JPEG: reduz, endireita e descarta os metadados.
     *
     * A ordem importa para a memória: primeiro reduz, e só depois gira. Girar
     * a foto inteira antes de reduzir criaria uma segunda cópia do tamanho
     * original, e o pico dobraria.
     *
     * @return array{contents: string, width: int, height: int}|null null se o arquivo não decodificar
     */
    public static function reencodeAsJpeg(string $path, int $type, int $maxDimension, int $quality): ?array
    {
        $decoder = self::TIPOS[$type]['decoder'];
        $origem = @$decoder($path);

        if (! $origem instanceof GdImage) {
            return null;
        }

        $largura = imagesx($origem);
        $altura = imagesy($origem);
        [$larguraFinal, $alturaFinal] = self::fit($largura, $altura, $maxDimension);

        $destino = imagecreatetruecolor($larguraFinal, $alturaFinal);

        // JPEG não tem transparência. Sem o fundo branco, o transparente de um
        // PNG ou WEBP sairia preto.
        imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));
        imagecopyresampled($destino, $origem, 0, 0, 0, 0, $larguraFinal, $alturaFinal, $largura, $altura);
        unset($origem);

        $destino = self::endireitar($destino, self::orientacao($path, $type));

        ob_start();
        imagejpeg($destino, null, $quality);
        $conteudo = (string) ob_get_clean();

        $resultado = ['contents' => $conteudo, 'width' => imagesx($destino), 'height' => imagesy($destino)];
        unset($destino);

        return $resultado;
    }

    /**
     * A orientação que a câmera gravou no EXIF.
     *
     * O celular grava a foto sempre "deitada" e anota no EXIF como ela deve
     * ser exibida. Como a regravação descarta o EXIF, a rotação precisa ser
     * aplicada nos pixels — senão metade das fotos tiradas em pé apareceria
     * de lado.
     */
    private static function orientacao(string $path, int $type): int
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($path);

        return (int) ($exif['Orientation'] ?? 1);
    }

    /**
     * Aplica as rotações que as câmeras de fato produzem: 3, 6 e 8.
     *
     * As orientações espelhadas (2, 4, 5 e 7) só aparecem em situações raras,
     * como algumas câmeras frontais, e ficam como vieram.
     */
    private static function endireitar(GdImage $imagem, int $orientacao): GdImage
    {
        // imagerotate gira no sentido anti-horário.
        $angulo = match ($orientacao) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angulo === 0) {
            return $imagem;
        }

        $girada = imagerotate($imagem, $angulo, 0);

        return $girada instanceof GdImage ? $girada : $imagem;
    }
}
