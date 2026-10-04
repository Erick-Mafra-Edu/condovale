<?php

namespace App\Http\Actions;

use App\Enums\TypeLogEnum;
use App\Exceptions\BusinessRuleException;
use App\Http\Utils\AuthUtil;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * RF02 / UC02 — authenticates by e-mail and password.
 */
class AuthenticateUserAction
{
    /**
     * Opens a session for the credentials, or fails with the reason.
     */
    public static function execute(Request $request, string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        self::checkCredentials($user, $password);
        self::checkActive($user);

        // A senha é regravada quando os parâmetros do Argon2id mudam, para que
        // contas antigas não fiquem presas a um custo menor que o atual (RNF02).
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }

        AuthUtil::guard()->login($user);

        // Sem isto o identificador de sessão criado antes do login continuaria
        // valendo depois dele, e quem o tivesse plantado no navegador da vítima
        // passaria a compartilhar a sessão autenticada.
        $request->session()->regenerate();

        $description = "Usuário {$user->id} {$user->name} autenticado.";
        CreateLogAction::execute(TypeLogEnum::LOGIN->value, $description);

        return $user;
    }

    /**
     * Ends the session of whoever is authenticated.
     */
    public static function logout(Request $request): void
    {
        $user = AuthUtil::user();

        if ($user) {
            $description = "Usuário {$user->id} {$user->name} encerrou a sessão.";
            CreateLogAction::execute(TypeLogEnum::LOGOUT->value, $description);
        }

        AuthUtil::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * E-mail desconhecido e senha errada devolvem a mesma recusa, de propósito:
     * mensagens distintas transformariam a tela de login num verificador de
     * quais e-mails estão cadastrados no condomínio.
     */
    private static function checkCredentials(?User $user, string $password): void
    {
        if ($user && self::isArgon2id($user->password) && Hash::check($password, $user->password)) {
            return;
        }

        throw BusinessRuleException::unauthorized('E-mail ou senha inválidos.');
    }

    /**
     * RNF02 — a senha guardada tem de ser Argon2id.
     *
     * Sem esta checagem, um hash de outro algoritmo faz o verificador lançar
     * exceção (o driver está com `verify` ligado) e o login devolve 500 com
     * "Erro ao salvar as informações na base de dados" — mensagem que manda
     * quem for investigar olhar para o banco, que não tem nada com isso.
     *
     * Tratado aqui, a conta simplesmente não autentica, com a mesma recusa de
     * qualquer credencial inválida. O caminho de volta é a administração
     * redefinir a senha, que a regrava no algoritmo certo.
     */
    private static function isArgon2id(?string $hash): bool
    {
        return is_string($hash) && str_starts_with($hash, '$argon2id$');
    }

    /**
     * RN04 — apenas usuários ativos acessam o sistema.
     *
     * A verificação vem depois da senha justamente para não vazar a existência
     * da conta: só quem já provou conhecer a credencial descobre que ela está
     * inativa.
     */
    private static function checkActive(User $user): void
    {
        if (! $user->isActive()) {
            throw BusinessRuleException::forbidden('Usuário inativo não pode acessar o sistema.');
        }
    }
}
