<?php
declare(strict_types=1);

final class AuthService
{
    public function __construct(private PDO $db) {}

    public function login(string $email, string $password): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, nome, email, senha_hash, perfil FROM usuarios WHERE email=:email AND ativo=1 LIMIT 1'
        );
        $statement->execute(['email' => strtolower(trim($email))]);
        $record = $statement->fetch();
        if (!$record || !password_verify($password, $record['senha_hash'])) return null;

        $user = [
            'id' => (int) $record['id'],
            'nome' => $record['nome'],
            'email' => $record['email'],
            'perfil' => $record['perfil'],
        ];
        $teacher = $this->db->prepare('SELECT id FROM professores WHERE usuario_id=:user_id LIMIT 1');
        $teacher->execute(['user_id' => $user['id']]);
        $teacherId = $teacher->fetchColumn();
        if ($teacherId !== false) $user['professor_id'] = (int) $teacherId;
        if ($user['perfil'] === 'professor' && $teacherId === false) return null;

        $student = $this->db->prepare('SELECT id FROM alunos WHERE usuario_id=:user_id LIMIT 1');
        $student->execute(['user_id' => $user['id']]);
        $studentId = $student->fetchColumn();
        if ($user['perfil'] === 'aluno') {
            if ($studentId === false) return null;
            $user['aluno_id'] = (int) $studentId;
        }

        startAppSession();
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $user;
    }

    public function logout(): void
    {
        startAppSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }

    public function requestPasswordReset(string $email): ?string
    {
        $q=$this->db->prepare('SELECT id,nome,email FROM usuarios WHERE email=:email AND ativo=1 LIMIT 1');$q->execute(['email'=>strtolower(trim($email))]);$user=$q->fetch();if(!$user)return null;
        $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);$this->db->beginTransaction();
        try{$old=$this->db->prepare('DELETE FROM recuperacao_senhas WHERE usuario_id=:id AND usado_em IS NULL');$old->execute(['id'=>$user['id']]);$save=$this->db->prepare('INSERT INTO recuperacao_senhas (usuario_id,token_hash,expira_em) VALUES (:id,:hash,DATE_ADD(NOW(),INTERVAL 30 MINUTE))');$save->execute(['id'=>$user['id'],'hash'=>$hash]);$this->db->commit();}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
        if((getenv('APP_ENV')?:'production')==='development')return $token;
        $from=getenv('MAIL_FROM')?:'';$appUrl=rtrim(getenv('APP_URL')?:'','/');if($from!==''&&$appUrl!==''){$link=$appUrl.'/frontend/reset-password.html?token='.rawurlencode($token);$sent=mail($user['email'],'Redefinição de senha - ClassManager',"Olá, {$user['nome']}. Use este link em até 30 minutos: {$link}",'From: '.$from);if(!$sent)error_log('[ClassManager] Não foi possível enviar e-mail de redefinição.');}
        return null;
    }

    public function resetPassword(string $token,string $password): void
    {
        if(strlen($password)<10)throw new InvalidArgumentException('A nova senha deve ter pelo menos 10 caracteres.');
        if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new InvalidArgumentException('Link de recuperação inválido ou expirado.');
        $this->db->beginTransaction();try{$q=$this->db->prepare('SELECT id,usuario_id FROM recuperacao_senhas WHERE token_hash=:hash AND usado_em IS NULL AND expira_em>NOW() FOR UPDATE');$q->execute(['hash'=>hash('sha256',$token)]);$record=$q->fetch();if(!$record)throw new InvalidArgumentException('Link de recuperação inválido ou expirado.');$u=$this->db->prepare('UPDATE usuarios SET senha_hash=:hash WHERE id=:id');$u->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$record['usuario_id']]);$done=$this->db->prepare('UPDATE recuperacao_senhas SET usado_em=NOW() WHERE id=:id');$done->execute(['id'=>$record['id']]);$this->db->commit();}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
