<?php

namespace App\Http\Utils;

/**
 * Gera a senha temporária do primeiro acesso.
 *
 * Ela não é digitada de um e-mail: é **ditada** por quem cadastrou — por
 * telefone, no balcão da portaria, numa mensagem. Por isso o formato é palavra
 * conhecida mais três dígitos, em oito caracteres: "porta482" se diz numa
 * frase e se digita sem erro, enquanto uma sequência aleatória de dezesseis
 * caracteres se perde na transcrição e volta como "não consigo entrar".
 *
 * **A troca é menos entropia por mais usabilidade, e isso é deliberado.** O que
 * sustenta a escolha é o que cerca a senha, não ela mesma:
 *
 * - ela só serve uma vez, porque o primeiro acesso obriga a trocá-la;
 * - o limitador do login permite 5 tentativas por minuto por conta;
 * - cada tentativa custa ~376 ms de Argon2id no servidor.
 *
 * Nessas condições, varrer o espaço inteiro levaria meses — tempo muito maior
 * que o de vida pretendido para a senha, que é de horas.
 *
 * As palavras são de cinco letras, sem acento e sem cedilha, para serem
 * ditadas e digitadas sem ambiguidade.
 */
class TemporaryPasswordUtil
{
    /**
     * Menor e maior número somados à palavra. Começa em 100 para o sufixo ter
     * sempre três dígitos — "porta7" seria mais curto e mais fácil de adivinhar.
     */
    private const MENOR_NUMERO = 100;

    private const MAIOR_NUMERO = 999;

    /** @var list<string> */
    private const PALAVRAS = [
        'areia', 'aroma', 'bacia', 'banho', 'barco', 'bolsa', 'bravo', 'brisa',
        'cabra', 'calma', 'campo', 'canto', 'carro', 'carta', 'casco', 'certo',
        'chave', 'chuva', 'claro', 'copia', 'corpo', 'curso', 'curva', 'dente',
        'duplo', 'feira', 'festa', 'folha', 'forma', 'forte', 'fruta', 'fundo',
        'garfo', 'globo', 'grade', 'grama', 'grupo', 'horta', 'justo', 'lagoa',
        'lapis', 'largo', 'leite', 'lento', 'letra', 'limpo', 'linha', 'livro',
        'lugar', 'macio', 'magro', 'manta', 'marco', 'massa', 'metal', 'metro',
        'monte', 'mundo', 'nobre', 'noite', 'norte', 'nuvem', 'palco', 'papel',
        'parte', 'passo', 'pasta', 'pedra', 'pente', 'perto', 'pesca', 'pinho',
        'pista', 'placa', 'plano', 'ponte', 'porta', 'porto', 'posto', 'pouco',
        'prato', 'prazo', 'praia', 'preto', 'prova', 'queda', 'rampa', 'ramos',
        'redor', 'regra', 'reino', 'resto', 'risco', 'ritmo', 'roupa', 'sabor',
        'salto', 'santo', 'selva', 'serra', 'sinal', 'sogro', 'solar', 'sonho',
        'sorte', 'surdo', 'talco', 'tampa', 'tarde', 'tecla', 'telha', 'tempo',
        'tenda', 'terra', 'texto', 'tigre', 'tinta', 'tocha', 'torre', 'trigo',
        'treze', 'tropa', 'turma', 'vapor', 'velho', 'vento', 'verde', 'vidro',
        'vinho', 'visor', 'viver', 'volta', 'zebra',
    ];

    /**
     * Quantas senhas distintas o formato produz. Serve para o teste cobrar um
     * piso: encolher a lista de palavras enfraquece a senha em silêncio.
     */
    public static function espacoDeBusca(): int
    {
        return count(self::PALAVRAS) * (self::MAIOR_NUMERO - self::MENOR_NUMERO + 1);
    }

    public static function gerar(): string
    {
        // random_int, e não rand: a senha é uma credencial, e um gerador
        // previsível tornaria a lista de palavras irrelevante.
        $palavra = self::PALAVRAS[random_int(0, count(self::PALAVRAS) - 1)];
        $numero = random_int(self::MENOR_NUMERO, self::MAIOR_NUMERO);

        return $palavra.$numero;
    }
}
