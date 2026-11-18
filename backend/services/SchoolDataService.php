<?php
declare(strict_types=1);

/** Consultas e gravações dos módulos administrativos, sempre com campos permitidos por módulo. */
final class SchoolDataService
{
    private const DEFINITIONS = [
        'teachers' => [
            'table' => 'professores',
            'fields' => ['nome_completo', 'telefone', 'email', 'idiomas', 'situacao'],
            'select' => 'SELECT id, nome_completo, telefone, email, idiomas, situacao FROM professores',
            'search' => ['nome_completo', 'email', 'idiomas'],
        ],
        'courses' => [
            'table' => 'cursos',
            'fields' => ['nome', 'idioma', 'nivel', 'duracao_meses', 'carga_horaria', 'valor', 'ativo'],
            'select' => 'SELECT id, nome, idioma, nivel, duracao_meses, carga_horaria, valor, ativo FROM cursos',
            'search' => ['nome', 'idioma', 'nivel'],
        ],
        'classes' => [
            'table' => 'turmas',
            'fields' => ['nome', 'curso_id', 'professor_id', 'capacidade', 'data_inicio', 'data_fim', 'situacao'],
            'select' => "SELECT t.id, t.nome, t.curso_id, c.nome AS curso_nome, t.professor_id,
                                p.nome_completo AS professor_nome, t.capacidade, t.data_inicio, t.data_fim,
                                t.situacao,
                                (SELECT GROUP_CONCAT(CONCAT(dia_semana, '|', TIME_FORMAT(hora_inicio,'%H:%i'), '|', TIME_FORMAT(hora_fim,'%H:%i')) ORDER BY dia_semana SEPARATOR ',')
                                   FROM turma_horarios h WHERE h.turma_id=t.id) AS horarios
                         FROM turmas t JOIN cursos c ON c.id=t.curso_id JOIN professores p ON p.id=t.professor_id",
            'search' => ['t.nome', 'c.nome', 'p.nome_completo'],
        ],
        'enrollments' => [
            'table' => 'matriculas',
            'fields' => ['aluno_id', 'turma_id', 'data_matricula', 'valor_mensalidade', 'situacao', 'observacoes'],
            'select' => 'SELECT m.id, m.aluno_id, a.nome_completo AS aluno_nome, m.turma_id, t.nome AS turma_nome,
                                m.data_matricula, m.valor_mensalidade, m.situacao, m.observacoes
                         FROM matriculas m JOIN alunos a ON a.id=m.aluno_id JOIN turmas t ON t.id=m.turma_id',
            'search' => ['a.nome_completo', 't.nome'],
        ],
        'lessons' => [
            'table' => 'aulas',
            'fields' => ['turma_id', 'data_aula', 'hora_inicio', 'hora_fim', 'conteudo', 'professor_substituto_id'],
            'select' => 'SELECT a.id, a.turma_id, t.nome AS turma_nome, a.data_aula, a.hora_inicio,
                                a.hora_fim, a.conteudo, a.professor_substituto_id, ps.nome_completo AS substituto_nome FROM aulas a JOIN turmas t ON t.id=a.turma_id LEFT JOIN professores ps ON ps.id=a.professor_substituto_id',
            'search' => ['t.nome', 'a.conteudo'],
        ],
        'invoices' => [
            'table' => 'mensalidades',
            'fields' => ['matricula_id', 'referencia', 'valor', 'desconto', 'multa', 'juros', 'vencimento', 'situacao'],
            'select' => 'SELECT m.id, m.matricula_id, a.nome_completo AS aluno_nome, t.nome AS turma_nome,
                                m.referencia, m.valor, m.desconto, m.multa, m.juros, m.vencimento, m.situacao,
                                COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.mensalidade_id=m.id),0) AS total_pago
                         FROM mensalidades m JOIN matriculas x ON x.id=m.matricula_id
                         JOIN alunos a ON a.id=x.aluno_id JOIN turmas t ON t.id=x.turma_id',
            'search' => ['a.nome_completo', 't.nome', 'm.referencia'],
        ],
        'payments' => [
            'table' => 'pagamentos',
            'fields' => ['mensalidade_id', 'valor', 'data_pagamento', 'forma_pagamento', 'observacoes'],
            'select' => 'SELECT p.id, p.mensalidade_id, a.nome_completo AS aluno_nome, m.referencia,
                                p.valor, p.data_pagamento, p.forma_pagamento, p.observacoes
                         FROM pagamentos p JOIN mensalidades m ON m.id=p.mensalidade_id
                         JOIN matriculas x ON x.id=m.matricula_id JOIN alunos a ON a.id=x.aluno_id',
            'search' => ['a.nome_completo', 'm.referencia', 'p.forma_pagamento'],
        ],
        'users' => [
            'table' => 'usuarios',
            'fields' => ['nome', 'email', 'perfil', 'ativo'],
            'select' => 'SELECT usuarios.id, usuarios.nome, usuarios.email, usuarios.perfil, usuarios.ativo,
                                (SELECT id FROM professores WHERE usuario_id=usuarios.id LIMIT 1) AS professor_id,
                                (SELECT id FROM alunos WHERE usuario_id=usuarios.id LIMIT 1) AS student_id FROM usuarios',
            'search' => ['nome', 'email', 'perfil'],
        ],
    ];

    public function __construct(private PDO $db) {}

    public static function names(): array { return array_keys(self::DEFINITIONS); }

    public function list(string $module, string $search = '', string $status = '', array $user = []): array
    {
        if ($module === 'invoices') {
            $this->db->exec("UPDATE mensalidades SET situacao='atrasado' WHERE situacao='pendente' AND vencimento<CURRENT_DATE()");
        }
        $definition = $this->definition($module);
        $sql = $definition['select'];
        $filters = [];
        $parameters = [];

        if ($search !== '') {
            $parts = [];
            foreach ($definition['search'] as $index => $column) {
                $key = 'search_' . $index;
                $parts[] = $column . ' LIKE :' . $key;
                $parameters[$key] = '%' . $search . '%';
            }
            if ($parts !== []) $filters[] = '(' . implode(' OR ', $parts) . ')';
        }
        if ($status !== '' && in_array($module, ['teachers', 'classes', 'enrollments', 'invoices'], true)) {
            $statusColumn = [
                'teachers' => 'situacao', 'classes' => 't.situacao',
                'enrollments' => 'm.situacao', 'invoices' => 'm.situacao',
            ][$module];
            $filters[] = $statusColumn . ' = :status';
            $parameters['status'] = $status;
        }

        if (($user['perfil'] ?? '') === 'professor') {
            $teacherId = (int) ($user['professor_id'] ?? 0);
            if ($module === 'classes') {
                $filters[] = 't.professor_id = :teacher_scope';
                $parameters['teacher_scope'] = $teacherId;
            } elseif ($module === 'enrollments') {
                $filters[] = 't.professor_id = :teacher_scope';
                $parameters['teacher_scope'] = $teacherId;
            } elseif ($module === 'lessons') {
                $filters[] = 't.professor_id = :teacher_scope';
                $parameters['teacher_scope'] = $teacherId;
            } elseif ($module === 'students') {
                $filters[] = 'EXISTS (SELECT 1 FROM matriculas mm JOIN turmas tt ON tt.id=mm.turma_id WHERE mm.aluno_id=a.id AND mm.situacao=\'ativa\' AND tt.professor_id=:teacher_scope)';
                $parameters['teacher_scope'] = $teacherId;
            } else {
                jsonResponse(['error' => 'Seu perfil não pode consultar este módulo.'], 403);
            }
        }

        if ($module === 'students') {
            $sql = 'SELECT a.id, a.nome_completo, a.cpf, a.data_nascimento, a.telefone, a.email,
                           a.endereco, a.data_matricula, a.situacao, r.nome_completo AS responsavel_nome
                    FROM alunos a LEFT JOIN responsaveis r ON r.id=a.responsavel_id';
            if ($status !== '') { $filters[] = 'a.situacao=:student_status'; $parameters['student_status'] = $status; }
            if ($search !== '') {
                $filters[] = '(a.nome_completo LIKE :student_name OR a.telefone LIKE :student_phone OR a.email LIKE :student_email)';
                $term = '%' . $search . '%';
                $parameters['student_name'] = $term;
                $parameters['student_phone'] = '%' . (preg_replace('/\D+/', '', $search) ?: $search) . '%';
                $parameters['student_email'] = $term;
            }
        }

        if ($filters !== []) $sql .= ' WHERE ' . implode(' AND ', $filters);
        $sql .= $module === 'classes' ? ' ORDER BY t.nome' : ' ORDER BY 1 DESC';
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function find(string $module, int $id, array $user = []): ?array
    {
        $items = $this->list($module, '', '', $user);
        foreach ($items as $item) if ((int) $item['id'] === $id) return $item;
        return null;
    }

    public function options(string $module, array $user = []): array
    {
        $queries = [
            'students' => 'SELECT id, nome_completo AS label FROM alunos WHERE situacao=\'ativo\' ORDER BY nome_completo',
            'teachers' => 'SELECT id, nome_completo AS label FROM professores WHERE situacao=\'ativo\' ORDER BY nome_completo',
            'courses' => 'SELECT id, nome AS label FROM cursos WHERE ativo=1 ORDER BY nome',
            'classes' => 'SELECT id, nome AS label FROM turmas WHERE situacao=\'ativa\' ORDER BY nome',
            'enrollments' => "SELECT m.id, CONCAT(a.nome_completo, ' — ', t.nome) AS label FROM matriculas m JOIN alunos a ON a.id=m.aluno_id JOIN turmas t ON t.id=m.turma_id WHERE m.situacao='ativa' ORDER BY a.nome_completo",
            'invoices' => "SELECT m.id, CONCAT(a.nome_completo, ' — ', t.nome, ' (', m.referencia, ')') AS label FROM mensalidades m JOIN matriculas x ON x.id=m.matricula_id JOIN alunos a ON a.id=x.aluno_id JOIN turmas t ON t.id=x.turma_id WHERE m.situacao IN ('pendente','atrasado') ORDER BY m.vencimento",
        ];
        $queries['substitute_teachers'] = $queries['teachers'];
        if (!isset($queries[$module])) throw new InvalidArgumentException('Opção desconhecida.');
        $sql = $queries[$module];
        $parameters = [];
        if (($user['perfil'] ?? '') === 'professor' && $module === 'enrollments') {
            $sql = "SELECT m.id, CONCAT(a.nome_completo, ' — ', t.nome) AS label FROM matriculas m JOIN alunos a ON a.id=m.aluno_id JOIN turmas t ON t.id=m.turma_id WHERE m.situacao='ativa' AND t.professor_id=:teacher ORDER BY a.nome_completo";
            $parameters['teacher'] = (int)($user['professor_id'] ?? 0);
        }
        if (($user['perfil'] ?? '') === 'professor' && $module === 'classes') {
            $sql = 'SELECT id, nome AS label FROM turmas WHERE professor_id=:teacher AND situacao=\'ativa\' ORDER BY nome';
            $parameters['teacher'] = (int) ($user['professor_id'] ?? 0);
        }
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function save(string $module, array $data, ?int $id = null): int
    {
        if ($module === 'classes') return $this->saveClass($data, $id);
        if ($module === 'enrollments') return $this->saveEnrollment($data, $id);
        if ($module === 'invoices') return $this->saveInvoice($data, $id);
        if ($module === 'users') return $this->saveUser($data, $id);
        if ($module === 'payments') { if($id!==null)throw new InvalidArgumentException('Pagamentos registrados são imutáveis; faça um estorno e registre o valor correto.'); return $this->savePayment($data); }

        $definition = $this->definition($module);
        $fields = $definition['fields'];
        $payload = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) $payload[$field] = $data[$field];
        }
        $this->validate($module, $payload, $id);
        foreach (['telefone'] as $phoneField) if (isset($payload[$phoneField])) $payload[$phoneField] = preg_replace('/\D+/', '', (string) $payload[$phoneField]) ?: null;
        foreach (['valor', 'valor_mensalidade', 'desconto', 'multa', 'juros'] as $moneyField) if (isset($payload[$moneyField])) $payload[$moneyField] = $this->money($payload[$moneyField]);

        if ($module === 'lessons' && ($payload['hora_fim'] ?? '') <= ($payload['hora_inicio'] ?? '')) {
            throw new InvalidArgumentException('O horário de término precisa ser depois do início.');
        }
        if ($module === 'lessons' && trim((string)($payload['professor_substituto_id'] ?? '')) === '') $payload['professor_substituto_id'] = null;

        $previous=null;
        if($module==='lessons' && $id!==null){$q=$this->db->prepare('SELECT turma_id,data_aula,hora_inicio,hora_fim,professor_substituto_id FROM aulas WHERE id=:id');$q->execute(['id'=>$id]);$previous=$q->fetch()?:null;if($previous===null)throw new OutOfBoundsException('Aula não encontrada.');}
        $saved=$this->write($definition['table'], $fields, $payload, $id);
        if($module==='lessons'){
            $changed=$previous===null;
            if($previous!==null)foreach(['turma_id','data_aula','hora_inicio','hora_fim','professor_substituto_id'] as $field)if(array_key_exists($field,$payload)&&(string)$payload[$field] !== (string)($previous[$field]??''))$changed=true;
            if($changed){$class=(int)($payload['turma_id']??$previous['turma_id']??0);$message='A aula da turma foi atualizada para '.($payload['data_aula']??$previous['data_aula']??'').' às '.substr((string)($payload['hora_inicio']??$previous['hora_inicio']??''),0,5).'.';$substitute=$payload['professor_substituto_id']??$previous['professor_substituto_id']??null;if($substitute){$teacher=$this->db->prepare('SELECT nome_completo FROM professores WHERE id=:id');$teacher->execute(['id'=>$substitute]);$message.=' Professor substituto: '.(string)$teacher->fetchColumn().'.';}$notice=$this->db->prepare("INSERT INTO comunicados (titulo,mensagem,publico,turma_id,professor_destinatario_id) VALUES ('Atualização de aula',:message,'todos',:class_id,:teacher_id)");$notice->execute(['message'=>$message,'class_id'=>$class,'teacher_id'=>$substitute]);}
        }
        return $saved;
    }

    public function archive(string $module, int $id): bool
    {
        $mapping = [
            'teachers' => ['professores', 'situacao', 'inativo'],
            'courses' => ['cursos', 'ativo', 0],
            'classes' => ['turmas', 'situacao', 'cancelada'],
            'enrollments' => ['matriculas', 'situacao', 'cancelada'],
            'invoices' => ['mensalidades', 'situacao', 'cancelado'],
            'users' => ['usuarios', 'ativo', 0],
        ];
        if (!isset($mapping[$module]) || $module === 'payments' || $module === 'lessons') throw new InvalidArgumentException('Este registro não pode ser arquivado.');
        [$table, $field, $value] = $mapping[$module];
        $statement = $this->db->prepare("UPDATE {$table} SET {$field}=:value WHERE id=:id");
        $statement->execute(['value' => $value, 'id' => $id]);
        if ($statement->rowCount() > 0) return true;
        $check = $this->db->prepare("SELECT 1 FROM {$table} WHERE id=:id");
        $check->execute(['id' => $id]);
        return (bool) $check->fetchColumn();
    }

    public function transferEnrollment(int $enrollmentId, int $targetClassId, string $date): int
    {
        if ($targetClassId < 1 || !$this->validDate($date)) throw new InvalidArgumentException('Informe uma turma e uma data de transferência válidas.');
        $this->db->beginTransaction();
        try {
            $find = $this->db->prepare("SELECT aluno_id,turma_id,valor_mensalidade FROM matriculas WHERE id=:id AND situacao='ativa' FOR UPDATE");
            $find->execute(['id'=>$enrollmentId]);
            $old = $find->fetch();
            if (!$old) throw new InvalidArgumentException('A matrícula ativa não foi encontrada.');
            if ((int) $old['turma_id'] === $targetClassId) throw new InvalidArgumentException('Selecione uma turma diferente da atual.');
            $target = $this->db->prepare("SELECT capacidade FROM turmas WHERE id=:id AND situacao='ativa' FOR UPDATE");
            $target->execute(['id'=>$targetClassId]);
            $capacity = $target->fetchColumn();
            if ($capacity === false) throw new InvalidArgumentException('A turma de destino não está ativa.');
            $duplicate = $this->db->prepare("SELECT COUNT(*) FROM matriculas WHERE aluno_id=:student AND turma_id=:class AND situacao='ativa'");
            $duplicate->execute(['student'=>$old['aluno_id'],'class'=>$targetClassId]);
            if ((int) $duplicate->fetchColumn() > 0) throw new InvalidArgumentException('O aluno já está matriculado nesta turma.');
            $occupied = $this->db->prepare("SELECT COUNT(*) FROM matriculas WHERE turma_id=:class AND situacao='ativa'");
            $occupied->execute(['class'=>$targetClassId]);
            if ((int) $occupied->fetchColumn() >= (int) $capacity) throw new InvalidArgumentException('A turma de destino atingiu a capacidade máxima.');
            $this->assertNoStudentScheduleConflict((int)$old['aluno_id'],$targetClassId,$enrollmentId);
            $close = $this->db->prepare("UPDATE matriculas SET situacao='transferida' WHERE id=:id");
            $close->execute(['id'=>$enrollmentId]);
            $insert = $this->db->prepare("INSERT INTO matriculas (aluno_id,turma_id,data_matricula,valor_mensalidade,situacao,observacoes) VALUES (:student,:class,:date,:fee,'ativa',:notes)");
            $insert->execute(['student'=>$old['aluno_id'],'class'=>$targetClassId,'date'=>$date,'fee'=>$old['valor_mensalidade'],'notes'=>'Transferência da matrícula #' . $enrollmentId]);
            $newId = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $newId;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    private function saveClass(array $data, ?int $id): int
    {
        $payload = [];
        foreach (self::DEFINITIONS['classes']['fields'] as $field) if (array_key_exists($field, $data)) $payload[$field] = $data[$field];
        $this->validate('classes', $payload, $id);
        $schedule = is_array($data['horarios'] ?? null) ? $data['horarios'] : [];
        if ($schedule === []) throw new InvalidArgumentException('Adicione pelo menos um dia e horário para a turma.');
        foreach ($schedule as $period) {
            $day = (int) ($period['dia_semana'] ?? 0);
            $start = (string) ($period['hora_inicio'] ?? '');
            $end = (string) ($period['hora_fim'] ?? '');
            if ($day < 1 || $day > 7 || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $end <= $start) {
                throw new InvalidArgumentException('Confira os dias e horários da turma.');
            }
        }
        $days = array_map(static fn(array $period): int => (int) ($period['dia_semana'] ?? 0), $schedule);
        if (count($days) !== count(array_unique($days))) throw new InvalidArgumentException('Escolha apenas um horário por dia da semana para esta turma.');
        $this->db->beginTransaction();
        try {
            $this->assertNoTeacherConflict($payload, $schedule, $id);
            if($id!==null){$occupied=$this->db->prepare("SELECT COUNT(*) FROM matriculas WHERE turma_id=:id AND situacao='ativa'");$occupied->execute(['id'=>$id]);if((int)$occupied->fetchColumn()>(int)$payload['capacidade'])throw new InvalidArgumentException('A capacidade não pode ficar abaixo do número de matrículas ativas.');}
            $savedId = $this->write('turmas', self::DEFINITIONS['classes']['fields'], $payload, $id);
            if ($id !== null) {
                $delete = $this->db->prepare('DELETE FROM turma_horarios WHERE turma_id=:id');
                $delete->execute(['id' => $savedId]);
            }
            $insert = $this->db->prepare('INSERT INTO turma_horarios (turma_id,dia_semana,hora_inicio,hora_fim) VALUES (:class_id,:day,:start,:end)');
            foreach ($schedule as $period) {
                $day = (int) ($period['dia_semana'] ?? 0);
                $start = (string) ($period['hora_inicio'] ?? '');
                $end = (string) ($period['hora_fim'] ?? '');
                if ($day < 1 || $day > 7 || $start === '' || $end <= $start) throw new InvalidArgumentException('Confira os dias e os horários da turma.');
                $insert->execute(['class_id' => $savedId, 'day' => $day, 'start' => $start, 'end' => $end]);
            }
            $enrolled=$this->db->prepare("SELECT aluno_id FROM matriculas WHERE turma_id=:id AND situacao='ativa'");$enrolled->execute(['id'=>$savedId]);foreach($enrolled->fetchAll(PDO::FETCH_COLUMN) as $studentId)$this->assertNoStudentScheduleConflict((int)$studentId,$savedId,null);
            if($id!==null){$notice=$this->db->prepare("INSERT INTO comunicados (titulo,mensagem,publico,turma_id) VALUES (:title,:message,'todos',:class_id)");$notice->execute(['title'=>'Atualização de turma','message'=>'Os horários ou as informações da turma '.$payload['nome'].' foram atualizados. Consulte o calendário acadêmico.','class_id'=>$savedId]);}
            $this->db->commit();
            return $savedId;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    private function assertNoTeacherConflict(array $class, array $schedule, ?int $ignoreId): void
    {
        $sql = 'SELECT t.nome FROM turma_horarios h JOIN turmas t ON t.id=h.turma_id
                WHERE t.professor_id=:teacher AND t.situacao=\'ativa\'
                  AND t.data_inicio <= COALESCE(:new_end,\'9999-12-31\')
                  AND COALESCE(t.data_fim,\'9999-12-31\') >= :new_start
                  AND h.dia_semana=:day AND h.hora_inicio < :new_end_time AND h.hora_fim > :new_start_time';
        if ($ignoreId !== null) $sql .= ' AND t.id <> :ignore_id';
        $statement = $this->db->prepare($sql);
        foreach ($schedule as $period) {
            $params = [
                'teacher' => $class['professor_id'], 'new_end' => $class['data_fim'] ?: null,
                'new_start' => $class['data_inicio'], 'day' => (int) $period['dia_semana'],
                'new_end_time' => $period['hora_fim'], 'new_start_time' => $period['hora_inicio'],
            ];
            if ($ignoreId !== null) $params['ignore_id'] = $ignoreId;
            $statement->execute($params);
            $conflict = $statement->fetchColumn();
            if ($conflict !== false) throw new InvalidArgumentException('Conflito de horário: o professor já tem uma turma nesse dia e período.');
        }
    }

    private function saveEnrollment(array $data, ?int $id): int
    {
        $fields = self::DEFINITIONS['enrollments']['fields'];
        $payload = [];
        foreach ($fields as $field) if (array_key_exists($field, $data)) $payload[$field] = $data[$field];
        $this->validate('enrollments', $payload, $id);
        $payload['valor_mensalidade'] = $this->money($payload['valor_mensalidade']);
        if (($payload['situacao'] ?? '') === 'ativa') {
            $student = $this->db->prepare("SELECT 1 FROM alunos WHERE id=:id AND situacao='ativo'");
            $student->execute(['id'=>$payload['aluno_id']]);
            if (!$student->fetchColumn()) throw new InvalidArgumentException('Selecione um aluno ativo.');
            $activeClass = $this->db->prepare("SELECT 1 FROM turmas WHERE id=:id AND situacao='ativa'");
            $activeClass->execute(['id'=>$payload['turma_id']]);
            if (!$activeClass->fetchColumn()) throw new InvalidArgumentException('Selecione uma turma ativa.');
            $duplicateSql = 'SELECT COUNT(*) FROM matriculas WHERE aluno_id=:student AND turma_id=:class AND situacao=\'ativa\'';
            $duplicateParams = ['student' => $payload['aluno_id'], 'class' => $payload['turma_id']];
            if ($id !== null) { $duplicateSql .= ' AND id<>:id'; $duplicateParams['id'] = $id; }
            $duplicate = $this->db->prepare($duplicateSql); $duplicate->execute($duplicateParams);
            if ((int) $duplicate->fetchColumn() > 0) throw new InvalidArgumentException('Este aluno já tem matrícula ativa nesta turma.');
            $capacitySql = 'SELECT t.capacidade, COUNT(m.id) AS ocupadas FROM turmas t LEFT JOIN matriculas m ON m.turma_id=t.id AND m.situacao=\'ativa\'';
            if ($id !== null) $capacitySql .= ' AND m.id<>:enrollment_id';
            $capacitySql .= ' WHERE t.id=:id GROUP BY t.id';
            $capacity = $this->db->prepare($capacitySql);
            $capacityParams = ['id' => $payload['turma_id']];
            if ($id !== null) $capacityParams['enrollment_id'] = $id;
            $capacity->execute($capacityParams);
            $counts = $capacity->fetch();
            if (!$counts) throw new InvalidArgumentException('A turma selecionada não existe.');
            if ((int) $counts['ocupadas'] >= (int) $counts['capacidade']) throw new InvalidArgumentException('A turma atingiu a capacidade máxima.');
            $this->assertNoStudentScheduleConflict((int)$payload['aluno_id'],(int)$payload['turma_id'],$id);
        }
        return $this->write('matriculas', $fields, $payload, $id);
    }

    private function savePayment(array $data): int
    {
        $this->validate('payments', $data, null);
        $value = $this->money($data['valor'] ?? 0);
        $invoiceId = (int) ($data['mensalidade_id'] ?? 0);
        $this->db->beginTransaction();
        try {
            $invoice = $this->db->prepare('SELECT valor, desconto, multa, juros FROM mensalidades WHERE id=:id AND situacao<>\'cancelado\' FOR UPDATE');
            $invoice->execute(['id' => $invoiceId]);
            $record = $invoice->fetch();
            if (!$record) throw new InvalidArgumentException('Selecione uma mensalidade válida.');
            $paid = $this->db->prepare('SELECT COALESCE(SUM(valor),0) FROM pagamentos WHERE mensalidade_id=:id');
            $paid->execute(['id' => $invoiceId]);
            $total = (float) $record['valor'] - (float) $record['desconto'] + (float) $record['multa'] + (float) $record['juros'];
            if ((float) $paid->fetchColumn() + (float) $value > $total + 0.005) throw new InvalidArgumentException('O pagamento ultrapassa o saldo da mensalidade.');
            $id = $this->write('pagamentos', self::DEFINITIONS['payments']['fields'], [
                'mensalidade_id' => $invoiceId, 'valor' => $value,
                'data_pagamento' => str_replace('T', ' ', (string) ($data['data_pagamento'] ?: date('Y-m-d H:i:s'))) . (strlen((string) ($data['data_pagamento'] ?? '')) === 16 ? ':00' : ''),
                'forma_pagamento' => $data['forma_pagamento'] ?? 'outro',
                'observacoes' => $data['observacoes'] ?? null,
            ], null);
            $paid->execute(['id' => $invoiceId]);
            if ((float) $paid->fetchColumn() >= $total - 0.005) {
                $update = $this->db->prepare("UPDATE mensalidades SET situacao='pago' WHERE id=:id");
                $update->execute(['id' => $invoiceId]);
            }
            $this->db->commit();
            return $id;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    private function saveInvoice(array $data, ?int $id): int
    {
        $fields = self::DEFINITIONS['invoices']['fields'];
        $payload = [];
        foreach ($fields as $field) if (array_key_exists($field, $data)) $payload[$field] = $data[$field];
        $this->validate('invoices', $payload, $id);
        foreach (['valor','desconto','multa','juros'] as $field) $payload[$field] = $this->money($payload[$field] ?? 0);
        $balance = $payload['valor'] - $payload['desconto'] + $payload['multa'] + $payload['juros'];
        if ($balance < 0) throw new InvalidArgumentException('O desconto não pode ser maior que o valor da mensalidade.');
        if ($id !== null) {
            $payments = $this->db->prepare('SELECT COALESCE(SUM(valor),0) FROM pagamentos WHERE mensalidade_id=:id');
            $payments->execute(['id' => $id]);
            $paid = (float) $payments->fetchColumn();
            if ($paid > $balance + 0.005) throw new InvalidArgumentException('O novo valor ficaria menor que os pagamentos já registrados.');
            if (($payload['situacao'] ?? '') === 'pago' && $paid < $balance - 0.005) throw new InvalidArgumentException('Marque a mensalidade como paga registrando o pagamento correspondente.');
        } elseif (($payload['situacao'] ?? 'pendente') === 'pago') {
            throw new InvalidArgumentException('Registre o pagamento para marcar a mensalidade como paga.');
        }
        return $this->write('mensalidades', $fields, $payload, $id);
    }

    private function saveUser(array $data, ?int $id): int
    {
        $fields = self::DEFINITIONS['users']['fields'];
        $payload = [];
        foreach ($fields as $field) if (array_key_exists($field, $data)) $payload[$field] = $data[$field];
        $this->validate('users', $payload, $id, $data['password'] ?? null);
        $password = (string) ($data['password'] ?? '');
        $professorId = trim((string) ($data['professor_id'] ?? '')) === '' ? null : (int) $data['professor_id'];
        $studentId = trim((string) ($data['student_id'] ?? '')) === '' ? null : (int) $data['student_id'];
        $role = $payload['perfil'] ?? '';
        if ($role === 'professor' && $professorId === null) throw new InvalidArgumentException('Vincule a conta ao cadastro do professor.');
        if ($role === 'aluno' && $studentId === null) throw new InvalidArgumentException('Vincule a conta ao cadastro do aluno.');
        if ($role !== 'professor') $professorId = null;
        if ($role !== 'aluno') $studentId = null;
        if ($password !== '') $payload['senha_hash'] = password_hash($password, PASSWORD_DEFAULT);
        if ($id === null && $password === '') throw new InvalidArgumentException('Defina uma senha inicial com pelo menos 10 caracteres.');
        $columns = $id === null || $password !== '' ? array_merge($fields, ['senha_hash']) : $fields;

        $this->db->beginTransaction();
        try {
            $savedId = $this->write('usuarios', $columns, $payload, $id);
            $this->assertLinkAvailable('professores', $professorId, $savedId, 'professor');
            $this->assertLinkAvailable('alunos', $studentId, $savedId, 'aluno');
            $unlinkTeacher = $this->db->prepare('UPDATE professores SET usuario_id=NULL WHERE usuario_id=:user_id');
            $unlinkTeacher->execute(['user_id'=>$savedId]);
            $unlinkStudent = $this->db->prepare('UPDATE alunos SET usuario_id=NULL WHERE usuario_id=:user_id');
            $unlinkStudent->execute(['user_id'=>$savedId]);
            if ($professorId !== null) {
                $link = $this->db->prepare('UPDATE professores SET usuario_id=:user WHERE id=:id AND usuario_id IS NULL');
                $link->execute(['user'=>$savedId,'id'=>$professorId]);
                if ($link->rowCount() !== 1) throw new InvalidArgumentException('Não foi possível vincular o cadastro do professor.');
            }
            if ($studentId !== null) {
                $link = $this->db->prepare('UPDATE alunos SET usuario_id=:user WHERE id=:id AND usuario_id IS NULL');
                $link->execute(['user'=>$savedId,'id'=>$studentId]);
                if ($link->rowCount() !== 1) throw new InvalidArgumentException('Não foi possível vincular o cadastro do aluno.');
            }
            $this->db->commit();
            return $savedId;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    private function assertLinkAvailable(string $table, ?int $recordId, int $userId, string $label): void
    {
        if ($recordId === null) return;
        $statement = $this->db->prepare("SELECT usuario_id FROM {$table} WHERE id=:id FOR UPDATE");
        $statement->execute(['id'=>$recordId]);
        $linkedUser = $statement->fetchColumn();
        if ($linkedUser === false) throw new InvalidArgumentException('O cadastro de ' . $label . ' selecionado não existe.');
        if ($linkedUser !== null && (int) $linkedUser !== $userId) throw new InvalidArgumentException('Este cadastro já está vinculado a outra conta.');
    }

    private function assertNoStudentScheduleConflict(int $studentId,int $classId,?int $ignoreEnrollment): void
    {
        $target=$this->db->prepare('SELECT h.dia_semana,h.hora_inicio,h.hora_fim,t.data_inicio,t.data_fim FROM turma_horarios h JOIN turmas t ON t.id=h.turma_id WHERE t.id=:class_id');
        $target->execute(['class_id'=>$classId]);$periods=$target->fetchAll();
        if($periods===[])return;
        $sql="SELECT 1 FROM matriculas m JOIN turmas t ON t.id=m.turma_id JOIN turma_horarios h ON h.turma_id=t.id
              WHERE m.aluno_id=:student AND m.situacao='ativa' AND t.situacao='ativa' AND t.id<>:target_class
              AND h.dia_semana=:day AND h.hora_inicio<:end_time AND h.hora_fim>:start_time
              AND t.data_inicio<=COALESCE(:target_end,'9999-12-31') AND COALESCE(t.data_fim,'9999-12-31')>=:target_start";
        if($ignoreEnrollment!==null)$sql.=' AND m.id<>:ignore_id';$q=$this->db->prepare($sql);
        foreach($periods as $period){$params=['student'=>$studentId,'target_class'=>$classId,'day'=>$period['dia_semana'],'end_time'=>$period['hora_fim'],'start_time'=>$period['hora_inicio'],'target_end'=>$period['data_fim'],'target_start'=>$period['data_inicio']];if($ignoreEnrollment!==null)$params['ignore_id']=$ignoreEnrollment;$q->execute($params);if($q->fetchColumn())throw new InvalidArgumentException('O aluno já está matriculado em outra turma com conflito de horário.');}
    }

    private function write(string $table, array $allowedFields, array $data, ?int $id): int
    {
        $payload = [];
        foreach ($allowedFields as $field) if (array_key_exists($field, $data)) $payload[$field] = $data[$field];
        if ($payload === []) throw new InvalidArgumentException('Nenhum dado válido para salvar.');
        if ($id === null) {
            $columns = array_keys($payload);
            $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')';
        } else {
            $sets = [];
            foreach (array_keys($payload) as $field) $sets[] = $field . '=:' . $field;
            $sql = 'UPDATE ' . $table . ' SET ' . implode(',', $sets) . ' WHERE id=:__id';
            $payload['__id'] = $id;
        }
        $statement = $this->db->prepare($sql);
        $statement->execute($payload);
        return $id ?? (int) $this->db->lastInsertId();
    }

    private function validate(string $module, array $data, ?int $id, mixed $password = null): void
    {
        $required = [
            'teachers' => ['nome_completo','idiomas'], 'courses' => ['nome','idioma','nivel','duracao_meses','carga_horaria'],
            'classes' => ['nome','curso_id','professor_id','capacidade','data_inicio'],
            'enrollments' => ['aluno_id','turma_id','data_matricula','valor_mensalidade'],
            'lessons' => ['turma_id','data_aula','hora_inicio','hora_fim'],
            'invoices' => ['matricula_id','referencia','valor','vencimento'],
            'payments' => ['mensalidade_id','valor','data_pagamento','forma_pagamento'],
            'users' => ['nome','email','perfil'],
        ][$module] ?? [];
        foreach ($required as $field) if (!isset($data[$field]) || trim((string) $data[$field]) === '') throw new InvalidArgumentException('Preencha todos os campos obrigatórios.');

        if (isset($data['email']) && $data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Informe um e-mail válido.');
        if (isset($data['telefone'])) {
            $digits = preg_replace('/\D+/', '', (string) $data['telefone']);
            if ($digits !== '' && !in_array(strlen($digits), [10,11], true)) throw new InvalidArgumentException('O telefone precisa ter DDD e 8 ou 9 dígitos.');
        }
        if ($module === 'users') {
            if (!in_array($data['perfil'] ?? '', ['administrador','professor','secretaria','aluno'], true)) throw new InvalidArgumentException('Selecione um perfil válido.');
            if (isset($password) && $password !== '' && strlen((string) $password) < 10) throw new InvalidArgumentException('A senha deve ter pelo menos 10 caracteres.');
        }
        if ($module === 'teachers' && !in_array($data['situacao'] ?? 'ativo', ['ativo','inativo'], true)) throw new InvalidArgumentException('Situação de professor inválida.');
        if ($module === 'courses') {
            if (!in_array($data['nivel'] ?? '', ['básico','intermediário','avançado'], true)) throw new InvalidArgumentException('Nível do curso inválido.');
            if ((int) $data['duracao_meses'] < 1 || (int) $data['carga_horaria'] < 1 || $this->money($data['valor'] ?? 0) < 0) throw new InvalidArgumentException('Duração, carga horária e valor não podem ser negativos ou zero.');
        }
        if ($module === 'lessons') {
            $activeClass = $this->db->prepare("SELECT 1 FROM turmas WHERE id=:id AND situacao='ativa'");
            $activeClass->execute(['id'=>$data['turma_id'] ?? 0]);
            if (!$activeClass->fetchColumn()) throw new InvalidArgumentException('Selecione uma turma ativa para a aula.');
        }
        if ($module === 'classes' && ((int) $data['capacidade'] < 1 || !in_array($data['situacao'] ?? 'ativa', ['ativa','concluída','cancelada'], true))) throw new InvalidArgumentException('Confira a capacidade e a situação da turma.');
        if ($module === 'lessons' && !empty($data['professor_substituto_id'])) {
            $teacher=$this->db->prepare("SELECT 1 FROM professores WHERE id=:id AND situacao='ativo'");$teacher->execute(['id'=>(int)$data['professor_substituto_id']]);
            if (!$teacher->fetchColumn()) throw new InvalidArgumentException('Selecione um professor substituto ativo.');
        }
        if ($module === 'enrollments' && $this->money($data['valor_mensalidade']) < 0) throw new InvalidArgumentException('A mensalidade não pode ser negativa.');
        foreach (['valor','valor_mensalidade','desconto','multa','juros'] as $moneyField) {
            if (isset($data[$moneyField]) && $this->money($data[$moneyField]) < 0) throw new InvalidArgumentException('Valores financeiros não podem ser negativos.');
        }
        if ($module === 'invoices') {
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $data['referencia'])) throw new InvalidArgumentException('Referência deve estar no formato AAAA-MM.');
            if ($this->money($data['valor']) <= 0) throw new InvalidArgumentException('O valor da mensalidade precisa ser maior que zero.');
        }
        if ($module === 'payments') {
            if ($this->money($data['valor'] ?? 0) <= 0) throw new InvalidArgumentException('O pagamento precisa ser maior que zero.');
            if (!in_array($data['forma_pagamento'] ?? '', ['dinheiro','pix','cartão','transferência','outro'], true)) throw new InvalidArgumentException('Forma de pagamento inválida.');
            $paymentDate = (string) ($data['data_pagamento'] ?? '');
            $dateFormat = str_contains($paymentDate, 'T') ? 'Y-m-d\\TH:i' : 'Y-m-d H:i:s';
            if (DateTime::createFromFormat('!' . $dateFormat, $paymentDate) === false) throw new InvalidArgumentException('Informe uma data e horário de pagamento válidos.');
        }
        if ($module === 'lessons') {
            foreach (['hora_inicio','hora_fim'] as $field) if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) ($data[$field] ?? ''))) throw new InvalidArgumentException('Informe horários válidos para a aula.');
        }
        foreach (['data_inicio','data_fim','data_matricula','data_aula','vencimento'] as $field) {
            if (!empty($data[$field]) && !$this->validDate((string) $data[$field])) throw new InvalidArgumentException('Confira as datas informadas.');
        }
        if ($module === 'classes' && !empty($data['data_fim']) && $data['data_fim'] < $data['data_inicio']) throw new InvalidArgumentException('A data final da turma precisa ser posterior à inicial.');
    }

    private function definition(string $module): array
    {
        if ($module === 'students') return [
            'table'=>'alunos', 'fields'=>['nome_completo','cpf','data_nascimento','telefone','email','endereco','data_matricula','situacao'],
            'select'=>'SELECT id FROM alunos', 'search'=>[],
        ];
        if (!isset(self::DEFINITIONS[$module])) throw new InvalidArgumentException('Módulo não encontrado.');
        return self::DEFINITIONS[$module];
    }

    private function money(mixed $value): float
    {
        if (is_int($value) || is_float($value)) return round((float) $value, 2);
        $text = trim((string) $value);
        $text = preg_replace('/[^0-9,.-]/u', '', $text) ?? '';
        if (str_contains($text, ',')) $text = str_replace(',', '.', str_replace('.', '', $text));
        return round((float) $text, 2);
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
