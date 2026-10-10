<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Entrega a senha temporária de uma conta recém-cadastrada.
 *
 * A senha vai no corpo porque é descartável e só serve para o primeiro acesso:
 * o middleware password.change impede que ela alcance qualquer outra coisa
 * antes de ser trocada.
 */
class TemporaryPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'CondoVale — seu primeiro acesso');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.temporary-password',
            with: [
                'nome' => $this->user->name,
                'senha' => $this->temporaryPassword,
            ],
        );
    }
}
