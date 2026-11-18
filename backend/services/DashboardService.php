<?php
declare(strict_types=1);

final class DashboardService
{
    public function __construct(private PDO $db) {}

    public function summary(array $user): array
    {
        $this->db->exec("UPDATE mensalidades SET situacao='atrasado' WHERE situacao='pendente' AND vencimento<CURRENT_DATE()");
        $teacherId = (int) ($user['professor_id'] ?? 0);
        $isTeacher = ($user['perfil'] ?? '') === 'professor';
        if ($isTeacher) {
            $counts = $this->db->prepare("SELECT
                (SELECT COUNT(DISTINCT m.aluno_id) FROM matriculas m JOIN turmas t ON t.id=m.turma_id WHERE m.situacao='ativa' AND t.professor_id=:teacher_a) AS students,
                (SELECT COUNT(*) FROM turmas WHERE situacao='ativa' AND professor_id=:teacher_b) AS classes,
                (SELECT COUNT(*) FROM matriculas m JOIN turmas t ON t.id=m.turma_id WHERE m.situacao='ativa' AND t.professor_id=:teacher_c) AS enrollments");
            $counts->execute(['teacher_a'=>$teacherId,'teacher_b'=>$teacherId,'teacher_c'=>$teacherId]);
            $stat = $counts->fetch();
            $teachers = 0;
        } else {
            $stat = [
                'students' => $this->scalar("SELECT COUNT(*) FROM alunos WHERE situacao='ativo'"),
                'classes' => $this->scalar("SELECT COUNT(*) FROM turmas WHERE situacao='ativa'"),
                'enrollments' => $this->scalar("SELECT COUNT(*) FROM matriculas WHERE situacao='ativa'"),
            ];
            $teachers = $this->scalar("SELECT COUNT(*) FROM professores WHERE situacao='ativo'");
        }

        $received = 0.0;
        $pending = 0.0;
        $overdue = 0.0;
        $revenue = array_fill(0, 12, 0.0);
        if (!$isTeacher) {
            $month = $this->db->query("SELECT COALESCE(SUM(valor),0) FROM pagamentos WHERE DATE_FORMAT(data_pagamento,'%Y-%m')=DATE_FORMAT(CURRENT_DATE(),'%Y-%m')");
            $received = (float) $month->fetchColumn();
            $balances = $this->db->query("SELECT m.situacao, GREATEST(m.valor-m.desconto+m.multa+m.juros-COALESCE(p.total,0),0) AS saldo FROM mensalidades m LEFT JOIN (SELECT mensalidade_id,SUM(valor) total FROM pagamentos GROUP BY mensalidade_id) p ON p.mensalidade_id=m.id WHERE m.situacao IN ('pendente','atrasado')");
            foreach ($balances->fetchAll() as $balance) {
                if ($balance['situacao'] === 'atrasado') $overdue += (float) $balance['saldo'];
                else $pending += (float) $balance['saldo'];
            }
            $revenueQuery = $this->db->query("SELECT DATE_FORMAT(data_pagamento,'%Y-%m') month_key,SUM(valor) total FROM pagamentos WHERE data_pagamento >= DATE_SUB(DATE_FORMAT(CURRENT_DATE(),'%Y-%m-01'),INTERVAL 11 MONTH) GROUP BY month_key ORDER BY month_key");
            $monthMap = [];
            foreach ($revenueQuery->fetchAll() as $item) $monthMap[$item['month_key']] = (float) $item['total'];
            $period = new DateTimeImmutable('first day of this month');
            $labels = [];
            for ($index=0; $index<12; $index++) {
                $key = $period->format('Y-m');
                $labels[] = $period->format('M');
                $revenue[$index] = $monthMap[$key] ?? 0.0;
                $period = $period->modify('-1 month');
            }
            $labels = array_reverse($labels);
            $revenue = array_reverse($revenue);
        } else {
            $labels = [];
            $period = new DateTimeImmutable('first day of this month -11 months');
            for ($index=0; $index<12; $index++) { $labels[] = $period->format('M'); $period = $period->modify('+1 month'); }
        }

        $upcomingSql = "SELECT a.id, a.data_aula, a.hora_inicio, a.hora_fim, t.nome AS turma_nome,
                               c.idioma, c.nivel, p.nome_completo AS professor_nome,
                               (SELECT COUNT(*) FROM matriculas m WHERE m.turma_id=t.id AND m.situacao='ativa') AS alunos
                        FROM aulas a JOIN turmas t ON t.id=a.turma_id JOIN cursos c ON c.id=t.curso_id
                        JOIN professores p ON p.id=t.professor_id
                        WHERE TIMESTAMP(a.data_aula,a.hora_inicio)>=CURRENT_TIMESTAMP() AND t.situacao='ativa'";
        if ($isTeacher) $upcomingSql .= ' AND t.professor_id=' . $teacherId;
        $upcomingSql .= ' ORDER BY a.data_aula,a.hora_inicio LIMIT 5';
        $upcoming = $this->db->query($upcomingSql)->fetchAll();

        $activity = [];
        if (!$isTeacher) {
            $activity = array_merge(
                $this->db->query("SELECT 'matricula' tipo, CONCAT('Nova matrícula: ', a.nome_completo) descricao, m.created_at momento FROM matriculas m JOIN alunos a ON a.id=m.aluno_id ORDER BY m.created_at DESC LIMIT 4")->fetchAll(),
                $this->db->query("SELECT 'pagamento' tipo, CONCAT('Pagamento recebido: ', a.nome_completo) descricao, p.created_at momento FROM pagamentos p JOIN mensalidades f ON f.id=p.mensalidade_id JOIN matriculas m ON m.id=f.matricula_id JOIN alunos a ON a.id=m.aluno_id ORDER BY p.created_at DESC LIMIT 4")->fetchAll()
            );
            usort($activity, static fn(array $left, array $right): int => strcmp((string) $right['momento'], (string) $left['momento']));
            $activity = array_slice($activity, 0, 6);
        }

        return [
            'statistics' => [
                'students' => (int) $stat['students'], 'teachers' => (int) $teachers,
                'classes' => (int) $stat['classes'], 'enrollments' => (int) $stat['enrollments'],
                'received' => $received, 'pending' => $pending, 'overdue' => $overdue,
            ],
            'revenue' => ['labels'=>$labels, 'values'=>$revenue],
            'upcoming_lessons' => $upcoming,
            'recent_activity' => $activity,
        ];
    }

    private function scalar(string $sql): int
    {
        return (int) $this->db->query($sql)->fetchColumn();
    }
}
