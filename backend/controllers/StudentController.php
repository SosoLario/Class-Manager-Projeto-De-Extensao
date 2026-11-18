<?php
declare(strict_types=1);

final class StudentController
{
    public function __construct(private Student $students) {}

    public function handle(string $method, ?int $id): never
    {
        if ($method === 'GET' && $id === null) {
            $items = $this->students->list(
                trim((string) ($_GET['search'] ?? '')),
                trim((string) ($_GET['status'] ?? ''))
            );
            jsonResponse(['data' => $items]);
        }

        if ($method === 'GET' && $id !== null) {
            $student = $this->students->find($id);
            $student === null
                ? jsonResponse(['error' => 'Aluno não encontrado.'], 404)
                : jsonResponse(['data' => $student]);
        }

        if ($method === 'POST' || $method === 'PUT') {
            $data = requestBody();
            $errors = $this->validate($data);
            if ($errors !== []) jsonResponse(['error' => 'Revise os campos informados.', 'fields' => $errors], 422);
            if ($method === 'PUT' && ($id === null || $this->students->find($id) === null)) {
                jsonResponse(['error' => 'Aluno não encontrado.'], 404);
            }

            $savedId = $this->students->save($data, $method === 'PUT' ? $id : null);
            jsonResponse(['data' => $this->students->find($savedId)], $method === 'POST' ? 201 : 200);
        }

        if ($method === 'DELETE' && $id !== null) {
            if (!$this->students->archive($id)) jsonResponse(['error' => 'Aluno não encontrado.'], 404);
            jsonResponse(['message' => 'Aluno arquivado com situação inativa.']);
        }

        header('Allow: GET, POST, PUT, DELETE');
        jsonResponse(['error' => 'Método ou rota não permitido.'], 405);
    }

    private function validate(array $data): array
    {
        $errors = [];
        $name = trim((string) ($data['nome_completo'] ?? ''));
        $nameLength = preg_match_all('/./us', $name, $matches);
        if ($name === '' || $nameLength === false || $nameLength > 160) $errors['nome_completo'] = 'Informe o nome completo (até 160 caracteres).';

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $errors['email'] = 'Informe um e-mail válido.';
        }

        $cpf = preg_replace('/\D+/', '', (string) ($data['cpf'] ?? ''));
        if ($cpf !== '' && !$this->isValidCpf($cpf)) $errors['cpf'] = 'Informe um CPF válido com 11 dígitos.';

        foreach (['telefone','responsavel_telefone'] as $field) {
            $phone = preg_replace('/\D+/', '', (string) ($data[$field] ?? ''));
            if ($phone !== '' && !in_array(strlen($phone), [10,11], true)) $errors[$field] = 'Informe o telefone com DDD e 8 ou 9 dígitos.';
        }
        $guardianEmail = trim((string) ($data['responsavel_email'] ?? ''));
        if ($guardianEmail !== '' && !filter_var($guardianEmail, FILTER_VALIDATE_EMAIL)) $errors['responsavel_email'] = 'Informe um e-mail válido para o responsável.';

        $status = $data['situacao'] ?? 'ativo';
        if (!in_array($status, ['ativo', 'inativo', 'trancado'], true)) $errors['situacao'] = 'Situação inválida.';

        foreach (['data_nascimento', 'data_matricula'] as $field) {
            $value = (string) ($data[$field] ?? '');
            if ($value !== '') {
                $date = DateTime::createFromFormat('!Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) $errors[$field] = 'Informe uma data válida.';
            }
        }
        if (!empty($data['data_nascimento']) && $data['data_nascimento'] > date('Y-m-d')) $errors['data_nascimento'] = 'A data de nascimento não pode estar no futuro.';
        return $errors;
    }

    private function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        for ($position = 9; $position < 11; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) $sum += (int) $cpf[$index] * (($position + 1) - $index);
            $digit = ($sum * 10) % 11;
            if ($digit === 10) $digit = 0;
            if ($digit !== (int) $cpf[$position]) return false;
        }
        return true;
    }
}
