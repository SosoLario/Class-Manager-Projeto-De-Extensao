<?php
declare(strict_types=1);

final class AuthController
{
    public function __construct(private AuthService $auth) {}

    public function handle(string $action, string $method): never
    {
        if ($action === 'request-reset' && $method === 'POST') {
            $data=requestBody();$email=trim((string)($data['email']??''));if(!filter_var($email,FILTER_VALIDATE_EMAIL))jsonResponse(['error'=>'Informe um e-mail válido.'],422);
            $token=$this->auth->requestPasswordReset($email);$result=['message'=>'Se o e-mail estiver cadastrado, você receberá instruções para redefinir a senha.'];if($token!==null)$result['development_token']=$token;jsonResponse($result);
        }
        if ($action === 'reset-password' && $method === 'POST') { $data=requestBody();$this->auth->resetPassword((string)($data['token']??''),(string)($data['password']??''));jsonResponse(['message'=>'Senha redefinida. Faça login com a nova senha.']); }
        if ($action === 'login' && $method === 'POST') {
            $data = requestBody();
            $email = trim((string) ($data['email'] ?? ''));
            $password = (string) ($data['password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
                jsonResponse(['error' => 'Informe um e-mail válido e a senha.'], 422);
            }
            $user = $this->auth->login($email, $password);
            if ($user === null) jsonResponse(['error' => 'E-mail ou senha incorretos.'], 401);
            jsonResponse(['data' => $user, 'csrf_token' => csrfToken()]);
        }

        if ($action === 'me' && $method === 'GET') {
            $user = requireSignedInUser();
            jsonResponse(['data' => $user, 'csrf_token' => csrfToken()]);
        }

        if ($action === 'logout' && $method === 'POST') {
            requireSignedInUser();
            verifyCsrfToken();
            $this->auth->logout();
            jsonResponse(['message' => 'Sessão encerrada.']);
        }

        header('Allow: GET, POST');
        jsonResponse(['error' => 'Ação de autenticação não encontrada.'], 404);
    }
}
