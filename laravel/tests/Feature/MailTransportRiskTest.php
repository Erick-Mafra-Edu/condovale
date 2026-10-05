<?php

namespace Tests\Feature;

use Illuminate\Foundation\Application;
use Tests\TestCase;

/**
 * Fio de alarme para o CVE-2026-48019, aceito como risco residual.
 *
 * O projeto permanece no Laravel 11 por decisão de escopo, e a correção desse
 * CRLF injection só existe a partir da 12.60. A falha não está em recusar o
 * e-mail malformado — a validação recusa quebra de linha literal —, e sim em
 * sequências que passam pela validação e que o Symfony Mailer reinterpreta
 * como CRLF ao montar a mensagem, forjando cabeçalhos.
 *
 * Ou seja: o alvo é o **envio**. Hoje o CondoVale não envia e-mail nenhum
 * (MAIL_MAILER=log nos dois ambientes, e não há recuperação de senha nem
 * notificação), então o caminho de exploração não existe.
 *
 * Este teste existe para que essa premissa não caia em silêncio. No dia em que
 * alguém configurar um transporte de verdade — e esse dia chega junto com a
 * recuperação de senha —, a suíte falha e aponta para cá em vez de o sistema
 * passar a enviar e-mail forjável sem ninguém lembrar do motivo.
 */
class MailTransportRiskTest extends TestCase
{
    /**
     * Primeira versão com a correção do CVE-2026-48019.
     */
    private const VERSAO_CORRIGIDA = '12.60.0';

    public function test_nao_ha_transporte_de_email_real_enquanto_o_framework_nao_for_corrigido(): void
    {
        if (version_compare(Application::VERSION, self::VERSAO_CORRIGIDA, '>=')) {
            $this->markTestSkipped(
                'O framework já está em '.Application::VERSION.', com a correção do CVE-2026-48019. '.
                'Este fio de alarme pode ser removido.'
            );
        }

        $transporte = config('mail.default');

        $this->assertContains(
            $transporte,
            ['log', 'array', 'null'],
            "O transporte de e-mail passou a ser \"{$transporte}\", mas o framework ".
            'ainda é o '.Application::VERSION.', afetado pelo CVE-2026-48019 — um CRLF '.
            'injection que permite forjar cabeçalhos em mensagens enviadas para endereços '.
            'informados pelo usuário. Antes de enviar e-mail de verdade, atualize o '.
            'framework para a '.self::VERSAO_CORRIGIDA.' ou posterior.'
        );
    }
}
