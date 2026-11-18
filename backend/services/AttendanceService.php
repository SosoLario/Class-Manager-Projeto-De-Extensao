<?php
declare(strict_types=1);

final class AttendanceService
{
    public function __construct(private PDO $db) {}

    public function listForLesson(int $lessonId): array
    {
        $statement = $this->db->prepare(
            "SELECT a.id AS student_id, a.nome_completo,
                    COALESCE(f.situacao,'presente') AS situacao, f.observacoes
             FROM aulas l JOIN matriculas m ON m.turma_id=l.turma_id AND m.situacao='ativa'
             JOIN alunos a ON a.id=m.aluno_id
             LEFT JOIN frequencias f ON f.aula_id=l.id AND f.aluno_id=a.id
             WHERE l.id=:lesson_id AND a.situacao='ativo' ORDER BY a.nome_completo"
        );
        $statement->execute(['lesson_id' => $lessonId]);
        return $statement->fetchAll();
    }

    public function saveForLesson(int $lessonId, array $records): void
    {
        $this->db->beginTransaction();
        try {
            $lesson = $this->db->prepare('SELECT turma_id FROM aulas WHERE id=:id FOR UPDATE');
            $lesson->execute(['id' => $lessonId]);
            $classId = $lesson->fetchColumn();
            if ($classId === false) throw new InvalidArgumentException('A aula selecionada não existe.');

            $eligible = $this->db->prepare("SELECT 1 FROM matriculas WHERE aluno_id=:student AND turma_id=:class AND situacao='ativa'");
            $save = $this->db->prepare(
                'INSERT INTO frequencias (aula_id,aluno_id,situacao,observacoes) VALUES (:lesson,:student,:status,:notes)
                 ON DUPLICATE KEY UPDATE situacao=VALUES(situacao), observacoes=VALUES(observacoes)'
            );
            foreach ($records as $record) {
                $studentId = (int) ($record['aluno_id'] ?? 0);
                $status = (string) ($record['situacao'] ?? '');
                if ($studentId < 1 || !in_array($status, ['presente','falta','atrasado'], true)) {
                    throw new InvalidArgumentException('Confira a situação de frequência dos alunos.');
                }
                $eligible->execute(['student' => $studentId, 'class' => $classId]);
                if (!$eligible->fetchColumn()) throw new InvalidArgumentException('Só é possível registrar frequência para alunos ativos da turma.');
                $save->execute([
                    'lesson' => $lessonId, 'student' => $studentId, 'status' => $status,
                    'notes' => trim((string) ($record['observacoes'] ?? '')) ?: null,
                ]);
            }
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function lessonClass(int $lessonId): ?array
    {
        $statement = $this->db->prepare('SELECT turma_id, professor_id FROM aulas a JOIN turmas t ON t.id=a.turma_id WHERE a.id=:id');
        $statement->execute(['id' => $lessonId]);
        return $statement->fetch() ?: null;
    }
}
