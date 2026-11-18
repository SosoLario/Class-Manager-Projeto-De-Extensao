<?php
declare(strict_types=1);

final class AcademicService
{
    public function __construct(private PDO $db) {}

    public function calendar(array $user, string $from, string $to): array
    {
        $this->assertDateRange($from, $to);
        $sql = "SELECT a.id,a.data_aula,a.hora_inicio,a.hora_fim,a.conteudo,t.id turma_id,t.nome turma_nome,
                       c.nome curso_nome,COALESCE(ps.nome_completo,p.nome_completo) professor_nome,
                       CASE WHEN a.professor_substituto_id IS NULL THEN 0 ELSE 1 END substituicao
                FROM aulas a JOIN turmas t ON t.id=a.turma_id JOIN cursos c ON c.id=t.curso_id
                JOIN professores p ON p.id=t.professor_id
                LEFT JOIN professores ps ON ps.id=a.professor_substituto_id
                WHERE a.data_aula BETWEEN :from_date AND :to_date";
        $params = ['from_date'=>$from,'to_date'=>$to];
        if (($user['perfil'] ?? '') === 'professor') { $sql .= ' AND (t.professor_id=:teacher OR a.professor_substituto_id=:teacher_sub)'; $params['teacher']=(int)($user['professor_id']??0); $params['teacher_sub']=(int)($user['professor_id']??0); }
        if (($user['perfil'] ?? '') === 'aluno') { $sql .= " AND EXISTS (SELECT 1 FROM matriculas m WHERE m.turma_id=t.id AND m.aluno_id=:student AND m.situacao='ativa')"; $params['student']=(int)($user['aluno_id']??0); }
        $sql .= ' ORDER BY a.data_aula,a.hora_inicio';
        $q=$this->db->prepare($sql); $q->execute($params); $lessons=$q->fetchAll();
        $classSql="SELECT t.id turma_id,t.nome turma_nome,c.nome curso_nome,p.nome_completo professor_nome,t.data_inicio,t.data_fim,
                         h.dia_semana,h.hora_inicio,h.hora_fim FROM turmas t JOIN cursos c ON c.id=t.curso_id
                  JOIN professores p ON p.id=t.professor_id JOIN turma_horarios h ON h.turma_id=t.id
                  WHERE t.situacao='ativa' AND t.data_inicio<=:to_date AND COALESCE(t.data_fim,'9999-12-31')>=:from_date";
        $classParams=['from_date'=>$from,'to_date'=>$to];
        if (($user['perfil'] ?? '') === 'professor') { $classSql.=' AND t.professor_id=:teacher';$classParams['teacher']=(int)($user['professor_id']??0); }
        if (($user['perfil'] ?? '') === 'aluno') { $classSql.=" AND EXISTS (SELECT 1 FROM matriculas m WHERE m.turma_id=t.id AND m.aluno_id=:student AND m.situacao='ativa')";$classParams['student']=(int)($user['aluno_id']??0); }
        $cq=$this->db->prepare($classSql);$cq->execute($classParams);$periods=$cq->fetchAll();
        $byDate=[];foreach($lessons as $lesson)$byDate[$lesson['turma_id'].'|'.$lesson['data_aula']]=true;
        $cursor=new DateTimeImmutable($from);$last=new DateTimeImmutable($to);
        while($cursor<=$last){$iso=(int)$cursor->format('N');$day=$cursor->format('Y-m-d');foreach($periods as $p){if((int)$p['dia_semana']!==$iso||$day<$p['data_inicio']||($p['data_fim']!==null&&$day>$p['data_fim'])||isset($byDate[$p['turma_id'].'|'.$day]))continue;$lessons[]=['id'=>null,'data_aula'=>$day,'hora_inicio'=>$p['hora_inicio'],'hora_fim'=>$p['hora_fim'],'conteudo'=>'Horário semanal da turma','turma_id'=>$p['turma_id'],'turma_nome'=>$p['turma_nome'],'curso_nome'=>$p['curso_nome'],'professor_nome'=>$p['professor_nome'],'substituicao'=>0];}$cursor=$cursor->modify('+1 day');}
        usort($lessons,static fn($a,$b)=>[$a['data_aula'],$a['hora_inicio']]<=>[$b['data_aula'],$b['hora_inicio']]);return $lessons;
    }

    public function grades(array $user): array
    {
        $sql="SELECT n.id,n.matricula_id,n.titulo,n.nota,n.nota_maxima,n.data_avaliacao,n.observacoes,
                     a.id aluno_id,a.nome_completo aluno_nome,t.id turma_id,t.nome turma_nome,p.id professor_id
              FROM notas n JOIN matriculas m ON m.id=n.matricula_id JOIN alunos a ON a.id=m.aluno_id
              JOIN turmas t ON t.id=m.turma_id JOIN professores p ON p.id=t.professor_id";
        $params=[]; $role=$user['perfil']??'';
        if($role==='aluno'){ $sql.=' WHERE a.id=:student'; $params['student']=(int)($user['aluno_id']??0); }
        elseif($role==='professor'){ $sql.=' WHERE p.id=:teacher'; $params['teacher']=(int)($user['professor_id']??0); }
        $sql.=' ORDER BY n.data_avaliacao DESC,n.id DESC'; $q=$this->db->prepare($sql);$q->execute($params);return $q->fetchAll();
    }

    public function saveGrade(array $data, array $user, ?int $id=null): int
    {
        if(!in_array($user['perfil']??'', ['administrador','professor'], true)) throw new DomainException('Seu perfil não pode lançar notas.');
        $enrollment=(int)($data['matricula_id']??0); $title=trim((string)($data['titulo']??''));
        $grade=filter_var($data['nota']??null,FILTER_VALIDATE_FLOAT); $max=filter_var($data['nota_maxima']??10,FILTER_VALIDATE_FLOAT); $date=(string)($data['data_avaliacao']??'');
        if($enrollment<1 || $title==='' || mb_strlen($title)>120 || $grade===false || $max===false || $max<=0 || $grade<0 || $grade>$max || !$this->validDate($date)) throw new InvalidArgumentException('Confira turma, título, nota, limite e data da avaliação.');
        $q=$this->db->prepare("SELECT t.professor_id FROM matriculas m JOIN turmas t ON t.id=m.turma_id WHERE m.id=:id AND m.situacao='ativa'");$q->execute(['id'=>$enrollment]);$teacher=$q->fetchColumn();
        if($teacher===false) throw new InvalidArgumentException('Selecione uma matrícula ativa.');
        if(($user['perfil']??'')==='professor' && (int)$teacher!==(int)($user['professor_id']??0)) throw new DomainException('Você só pode lançar notas nas suas turmas.');
        $values=['matricula_id'=>$enrollment,'titulo'=>$title,'nota'=>$grade,'nota_maxima'=>$max,'data_avaliacao'=>$date,'observacoes'=>trim((string)($data['observacoes']??''))?:null];
        if($id===null){$s=$this->db->prepare('INSERT INTO notas (matricula_id,titulo,nota,nota_maxima,data_avaliacao,observacoes) VALUES (:matricula_id,:titulo,:nota,:nota_maxima,:data_avaliacao,:observacoes)');$s->execute($values);return (int)$this->db->lastInsertId();}
        $check=$this->db->prepare('SELECT p.id FROM notas n JOIN matriculas m ON m.id=n.matricula_id JOIN turmas t ON t.id=m.turma_id JOIN professores p ON p.id=t.professor_id WHERE n.id=:id');$check->execute(['id'=>$id]);$owner=$check->fetchColumn();
        if($owner===false) throw new OutOfBoundsException('Nota não encontrada.'); if(($user['perfil']??'')==='professor' && (int)$owner!==(int)($user['professor_id']??0)) throw new DomainException('Acesso negado a esta nota.');
        $values['id']=$id;$s=$this->db->prepare('UPDATE notas SET matricula_id=:matricula_id,titulo=:titulo,nota=:nota,nota_maxima=:nota_maxima,data_avaliacao=:data_avaliacao,observacoes=:observacoes WHERE id=:id');$s->execute($values);return $id;
    }

    public function deleteGrade(int $id, array $user): void
    {
        if(!in_array($user['perfil']??'', ['administrador','professor'], true)) throw new DomainException('Seu perfil não pode excluir notas.');
        $sql='DELETE n FROM notas n JOIN matriculas m ON m.id=n.matricula_id JOIN turmas t ON t.id=m.turma_id WHERE n.id=:id';$p=['id'=>$id];if(($user['perfil']??'')==='professor'){$sql.=' AND t.professor_id=:teacher';$p['teacher']=(int)($user['professor_id']??0);}$q=$this->db->prepare($sql);$q->execute($p);if(!$q->rowCount())throw new OutOfBoundsException('Nota não encontrada ou sem permissão.');
    }

    public function announcements(array $user): array
    {
        $sql="SELECT c.id,c.titulo,c.mensagem,c.publico,c.publicar_em,c.expira_em,u.nome autor FROM comunicados c LEFT JOIN usuarios u ON u.id=c.autor_id WHERE c.publicar_em<=NOW() AND (c.expira_em IS NULL OR c.expira_em>=NOW())";$p=[];$role=$user['perfil']??'';
        if($role==='aluno')$sql.=" AND c.publico IN ('todos','alunos')"; elseif($role==='professor')$sql.=" AND c.publico IN ('todos','professores')"; elseif($role==='secretaria')$sql.=" AND c.publico IN ('todos','professores','alunos')";
        if($role==='aluno'){$sql.=" AND (c.turma_id IS NULL OR EXISTS (SELECT 1 FROM matriculas m WHERE m.aluno_id=:student AND m.turma_id=c.turma_id AND m.situacao='ativa'))";$p['student']=(int)($user['aluno_id']??0);}elseif($role==='professor'){$sql.=" AND (c.turma_id IS NULL OR EXISTS (SELECT 1 FROM turmas t WHERE t.id=c.turma_id AND t.professor_id=:teacher) OR c.professor_destinatario_id=:teacher_target)";$p['teacher']=(int)($user['professor_id']??0);$p['teacher_target']=(int)($user['professor_id']??0);}
        $sql.=' ORDER BY c.publicar_em DESC';$q=$this->db->prepare($sql);$q->execute($p);return $q->fetchAll();
    }

    public function publishAnnouncement(array $data,array $user): int
    {
        if(!in_array($user['perfil']??'', ['administrador','secretaria'],true))throw new DomainException('Somente administração e secretaria podem publicar comunicados.');
        $title=trim((string)($data['titulo']??''));$body=trim((string)($data['mensagem']??''));$audience=(string)($data['publico']??'todos');
        if($title===''||mb_strlen($title)>160||$body===''||mb_strlen($body)>10000||!in_array($audience,['todos','alunos','professores'],true))throw new InvalidArgumentException('Preencha título, mensagem e público válido.');
        $q=$this->db->prepare('INSERT INTO comunicados (titulo,mensagem,publico,autor_id) VALUES (:title,:message,:audience,:author)');$q->execute(['title'=>$title,'message'=>$body,'audience'=>$audience,'author'=>(int)$user['id']]);return (int)$this->db->lastInsertId();
    }

    private function assertDateRange(string $from,string $to): void { if(!$this->validDate($from)||!$this->validDate($to)||$from>$to||((strtotime($to)-strtotime($from))/86400)>93)throw new InvalidArgumentException('Informe um período válido de até 94 dias.'); }
    private function validDate(string $date): bool { $d=DateTime::createFromFormat('!Y-m-d',$date);return $d!==false&&$d->format('Y-m-d')===$date; }
}
