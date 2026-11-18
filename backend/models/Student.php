<?php
declare(strict_types=1);

final class Student
{
    public function __construct(private PDO $db) {}

    public function list(string $search = '', string $status = ''): array
    {
        $sql = 'SELECT a.id, a.nome_completo, a.cpf, a.data_nascimento, a.telefone,
                       a.email, a.endereco, a.data_matricula, a.situacao,
                       r.nome_completo AS responsavel_nome
                FROM alunos a
                LEFT JOIN responsaveis r ON r.id = a.responsavel_id';
        $filters = [];
        $parameters = [];

        if (trim($search) !== '') {
            $filters[] = '(a.nome_completo LIKE :name_search OR a.telefone LIKE :phone_search OR a.email LIKE :email_search)';
            $term = '%' . trim($search) . '%';
            $parameters['name_search'] = $term;
            $parameters['phone_search'] = '%' . (preg_replace('/\D+/', '', trim($search)) ?: trim($search)) . '%';
            $parameters['email_search'] = $term;
        }
        if (trim($status) !== '') {
            $filters[] = 'a.situacao = :status';
            $parameters['status'] = trim($status);
        }
        if ($filters !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $filters);
        }
        $sql .= ' ORDER BY a.nome_completo';

        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT a.*, r.nome_completo AS responsavel_nome, r.telefone AS responsavel_telefone,
                    r.email AS responsavel_email
             FROM alunos a LEFT JOIN responsaveis r ON r.id = a.responsavel_id WHERE a.id = :id'
        );
        $statement->execute(['id' => $id]);
        $student = $statement->fetch();
        return $student ?: null;
    }

    public function save(array $data, ?int $id = null): int
    {
        $this->db->beginTransaction();
        try {
            $responsibleId = $this->saveResponsible($data, $id);
            $fields = [
                'responsavel_id' => $responsibleId,
                'nome_completo' => trim((string) ($data['nome_completo'] ?? '')),
                'cpf' => preg_replace('/\D+/', '', (string) ($data['cpf'] ?? '')) ?: null,
                'data_nascimento' => ($data['data_nascimento'] ?? '') ?: null,
                'telefone' => preg_replace('/\D+/', '', (string) ($data['telefone'] ?? '')) ?: null,
                'email' => trim((string) ($data['email'] ?? '')) ?: null,
                'endereco' => trim((string) ($data['endereco'] ?? '')) ?: null,
                'data_matricula' => ($data['data_matricula'] ?? '') ?: date('Y-m-d'),
                'situacao' => $data['situacao'] ?? 'ativo',
            ];

            if ($id === null) {
                $statement = $this->db->prepare(
                    'INSERT INTO alunos (responsavel_id, nome_completo, cpf, data_nascimento, telefone, email, endereco, data_matricula, situacao)
                     VALUES (:responsavel_id, :nome_completo, :cpf, :data_nascimento, :telefone, :email, :endereco, :data_matricula, :situacao)'
                );
                $statement->execute($fields);
                $id = (int) $this->db->lastInsertId();
            } else {
                $fields['id'] = $id;
                $statement = $this->db->prepare(
                    'UPDATE alunos SET responsavel_id=:responsavel_id, nome_completo=:nome_completo, cpf=:cpf,
                     data_nascimento=:data_nascimento, telefone=:telefone, email=:email, endereco=:endereco,
                     data_matricula=:data_matricula, situacao=:situacao WHERE id=:id'
                );
                $statement->execute($fields);
            }
            $this->db->commit();
            return $id;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function archive(int $id): bool
    {
        $statement = $this->db->prepare("UPDATE alunos SET situacao='inativo' WHERE id=:id");
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() > 0) return true;
        $exists = $this->db->prepare('SELECT 1 FROM alunos WHERE id=:id');
        $exists->execute(['id' => $id]);
        return (bool) $exists->fetchColumn();
    }

    private function saveResponsible(array $data, ?int $studentId): ?int
    {
        $name = trim((string) ($data['responsavel_nome'] ?? ''));
        if ($name === '') {
            if ($studentId !== null) {
                $clear = $this->db->prepare('UPDATE alunos SET responsavel_id=NULL WHERE id=:id');
                $clear->execute(['id' => $studentId]);
            }
            return null;
        }

        $phone = preg_replace('/\D+/', '', (string) ($data['responsavel_telefone'] ?? '')) ?: null;
        $email = trim((string) ($data['responsavel_email'] ?? '')) ?: null;
        if ($studentId !== null) {
            $current = $this->db->prepare('SELECT responsavel_id FROM alunos WHERE id=:id');
            $current->execute(['id' => $studentId]);
            $responsibleId = $current->fetchColumn();
            if ($responsibleId !== false && $responsibleId !== null) {
                $update = $this->db->prepare(
                    'UPDATE responsaveis SET nome_completo=:name, telefone=:phone, email=:email WHERE id=:id'
                );
                $update->execute(['name' => $name, 'phone' => $phone, 'email' => $email, 'id' => $responsibleId]);
                return (int) $responsibleId;
            }
        }
        $statement = $this->db->prepare(
            'INSERT INTO responsaveis (nome_completo, telefone, email) VALUES (:name, :phone, :email)'
        );
        $statement->execute(['name' => $name, 'phone' => $phone, 'email' => $email]);
        return (int) $this->db->lastInsertId();
    }
}
