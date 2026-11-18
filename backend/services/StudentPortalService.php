<?php
declare(strict_types=1);

/** Entrega ao aluno apenas os próprios dados acadêmicos e financeiros. */
final class StudentPortalService
{
    public function __construct(private PDO $db) {}

    public function summary(int $studentId): array
    {
        $this->db->exec("UPDATE mensalidades SET situacao='atrasado' WHERE situacao='pendente' AND vencimento<CURRENT_DATE()");
        $studentQuery = $this->db->prepare('SELECT id,nome_completo,email,situacao FROM alunos WHERE id=:id');
        $studentQuery->execute(['id'=>$studentId]);
        $student = $studentQuery->fetch();
        if (!$student) throw new InvalidArgumentException('Cadastro do aluno não encontrado.');

        $enrollments = $this->db->prepare(
            'SELECT m.id,m.data_matricula,m.situacao,m.valor_mensalidade,t.nome AS turma_nome,
                    c.nome AS curso_nome,c.idioma,c.nivel,
                    (SELECT GROUP_CONCAT(CONCAT(h.dia_semana,\'|\',TIME_FORMAT(h.hora_inicio,\'%H:%i\'),\'|\',TIME_FORMAT(h.hora_fim,\'%H:%i\')) ORDER BY h.dia_semana SEPARATOR \',\') FROM turma_horarios h WHERE h.turma_id=t.id) AS horarios
             FROM matriculas m JOIN turmas t ON t.id=m.turma_id JOIN cursos c ON c.id=t.curso_id
             WHERE m.aluno_id=:student ORDER BY m.created_at DESC'
        );
        $enrollments->execute(['student'=>$studentId]);

        $lessons = $this->db->prepare(
            'SELECT DISTINCT a.data_aula,a.hora_inicio,a.hora_fim,a.conteudo,t.nome AS turma_nome,c.idioma,c.nivel,
                    f.situacao AS frequencia
             FROM aulas a JOIN turmas t ON t.id=a.turma_id JOIN cursos c ON c.id=t.curso_id
             JOIN matriculas m ON m.turma_id=t.id AND m.aluno_id=:student
             LEFT JOIN frequencias f ON f.aula_id=a.id AND f.aluno_id=:student_attendance
             WHERE a.data_aula>=DATE_SUB(CURRENT_DATE(),INTERVAL 12 MONTH)
             ORDER BY a.data_aula DESC,a.hora_inicio DESC LIMIT 30'
        );
        $lessons->execute(['student'=>$studentId,'student_attendance'=>$studentId]);

        $invoices = $this->db->prepare(
            'SELECT f.referencia,f.valor,f.desconto,f.multa,f.juros,f.vencimento,f.situacao,
                    COALESCE(p.total,0) AS total_pago,
                    GREATEST(f.valor-f.desconto+f.multa+f.juros-COALESCE(p.total,0),0) AS saldo
             FROM mensalidades f JOIN matriculas m ON m.id=f.matricula_id
             LEFT JOIN (SELECT mensalidade_id,SUM(valor) total FROM pagamentos GROUP BY mensalidade_id) p ON p.mensalidade_id=f.id
             WHERE m.aluno_id=:student ORDER BY f.vencimento DESC LIMIT 24'
        );
        $invoices->execute(['student'=>$studentId]);

        return [
            'student'=>$student,
            'enrollments'=>$enrollments->fetchAll(),
            'lessons'=>$lessons->fetchAll(),
            'invoices'=>$invoices->fetchAll(),
        ];
    }
}
