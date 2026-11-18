<?php
declare(strict_types=1);

final class ResourceController
{
    private const READ_ROLES = [
        'students' => ['administrador','secretaria','professor'],
        'teachers' => ['administrador'], 'courses' => ['administrador'],
        'classes' => ['administrador','secretaria','professor'],
        'enrollments' => ['administrador','secretaria'],
        'lessons' => ['administrador','secretaria','professor'],
        'attendance' => ['administrador','professor'],
        'invoices' => ['administrador','secretaria'], 'payments' => ['administrador','secretaria'],
        'users' => ['administrador'],
    ];
    private const WRITE_ROLES = [
        'students' => ['administrador','secretaria'],
        'teachers' => ['administrador'], 'courses' => ['administrador'], 'classes' => ['administrador'],
        'enrollments' => ['administrador','secretaria'],
        'lessons' => ['administrador','professor'], 'attendance' => ['administrador','professor'],
        'invoices' => ['administrador'], 'payments' => ['administrador','secretaria'], 'users' => ['administrador'],
    ];

    public function __construct(private SchoolDataService $data, private AttendanceService $attendance, private StudentController $students) {}

    public function handle(string $module, ?int $id, string $method, array $user): never
    {
        $allowed = self::READ_ROLES[$module] ?? null;
        if ($allowed === null) jsonResponse(['error' => 'Módulo não encontrado.'], 404);
        if (!in_array($user['perfil'] ?? '', $allowed, true)) jsonResponse(['error' => 'Seu perfil não pode consultar esse módulo.'], 403);

        if ($module === 'attendance') {
            $lessonId = $id ?? (int) ($_GET['lesson_id'] ?? 0);
            if ($lessonId < 1) jsonResponse(['error' => 'Selecione uma aula para consultar a frequência.'], 422);
            $this->assertTeacherLessonAccess($user, $lessonId);
            if ($method === 'GET') jsonResponse(['data' => $this->attendance->listForLesson($lessonId)]);
            $this->assertWritable($module, $user);
            verifyCsrfToken();
            $this->attendance->saveForLesson($lessonId, (array) (requestBody()['records'] ?? []));
            jsonResponse(['message' => 'Frequência salva.']);
        }

        if ($module === 'students') {
            if ($method === 'GET' && $id === null) {
                $items = $this->data->list('students', trim((string) ($_GET['search'] ?? '')), trim((string) ($_GET['status'] ?? '')), $user);
                jsonResponse(['data' => $items]);
            }
            if ($method === 'GET' && $id !== null) {
                $item = $this->data->find('students', $id, $user);
                $item === null ? jsonResponse(['error' => 'Aluno não encontrado.'], 404) : jsonResponse(['data' => $item]);
            }
            $this->assertWritable($module, $user);
            verifyCsrfToken();
            $this->students->handle($method, $id);
        }

        if ($method === 'GET' && $id === null) {
            $items = $this->data->list($module, trim((string) ($_GET['search'] ?? '')), trim((string) ($_GET['status'] ?? '')), $user);
            jsonResponse(['data' => $items]);
        }
        if ($method === 'GET' && $id !== null) {
            $item = $this->data->find($module, $id, $user);
            $item === null ? jsonResponse(['error' => 'Registro não encontrado.'], 404) : jsonResponse(['data' => $item]);
        }

        $this->assertWritable($module, $user);
        verifyCsrfToken();
        try {
            if ($module === 'enrollments' && $method === 'POST' && $id !== null) {
                $body = requestBody();
                if (($body['action'] ?? '') !== 'transfer') jsonResponse(['error' => 'Ação de matrícula inválida.'], 422);
                $newId = $this->data->transferEnrollment($id, (int) ($body['turma_id'] ?? 0), (string) ($body['data_matricula'] ?? ''));
                jsonResponse(['data' => $this->data->find('enrollments', $newId, $user), 'message' => 'Matrícula transferida.'], 201);
            }
            if (($method === 'POST' || $method === 'PUT') && ($method === 'POST' || $id !== null)) {
                $data = requestBody();
                if($method==='PUT' && ($module==='payments' || $this->data->find($module,(int)$id,$user)===null)) jsonResponse(['error'=>$module==='payments'?'Pagamentos registrados não podem ser editados.':'Registro não encontrado.'], $module==='payments'?409:404);
                if (($user['perfil'] ?? '') === 'professor') $this->assertTeacherClassAccess($user, (int) ($data['turma_id'] ?? 0));
                if (($user['perfil'] ?? '') === 'professor' && !empty($data['professor_substituto_id'])) jsonResponse(['error'=>'Somente administração pode definir professor substituto.'],403);
                $savedId = $this->data->save($module, $data, $method === 'PUT' ? $id : null);
                jsonResponse(['data' => $this->data->find($module, $savedId, $user)], $method === 'POST' ? 201 : 200);
            }
            if ($method === 'DELETE' && $id !== null) {
                if (!$this->data->archive($module, $id)) jsonResponse(['error' => 'Registro não encontrado.'], 404);
                jsonResponse(['message' => 'Registro arquivado.']);
            }
        } catch (InvalidArgumentException $error) {
            jsonResponse(['error' => $error->getMessage()], 422);
        }

        header('Allow: GET, POST, PUT, DELETE');
        jsonResponse(['error' => 'Método ou rota não permitido.'], 405);
    }

    public function options(string $module, array $user): never
    {
        $optionRoles = [
            'students' => ['administrador','secretaria'], 'teachers' => ['administrador'],
            'courses' => ['administrador'], 'classes' => ['administrador','secretaria','professor'],
            'enrollments' => ['administrador','secretaria','professor'], 'invoices' => ['administrador','secretaria'],
            'substitute_teachers' => ['administrador'],
        ];
        if (!in_array($user['perfil'] ?? '', $optionRoles[$module] ?? [], true)) jsonResponse(['error' => 'Acesso negado.'], 403);
        jsonResponse(['data' => $this->data->options($module, $user)]);
    }

    private function assertWritable(string $module, array $user): void
    {
        if (!in_array($user['perfil'] ?? '', self::WRITE_ROLES[$module] ?? [], true)) {
            jsonResponse(['error' => 'Seu perfil não pode alterar esse módulo.'], 403);
        }
    }

    private function assertTeacherClassAccess(array $user, int $classId): void
    {
        if (($user['perfil'] ?? '') !== 'professor') return;
        if ($classId < 1) jsonResponse(['error' => 'Selecione uma turma.'], 422);
        $db = databaseConnection();
        $statement = $db->prepare('SELECT 1 FROM turmas WHERE id=:class_id AND professor_id=:teacher_id');
        $statement->execute(['class_id' => $classId, 'teacher_id' => (int) ($user['professor_id'] ?? 0)]);
        if (!$statement->fetchColumn()) jsonResponse(['error' => 'Você só pode acessar suas próprias turmas.'], 403);
    }

    private function assertTeacherLessonAccess(array $user, int $lessonId): void
    {
        if (($user['perfil'] ?? '') !== 'professor') return;
        $lesson = $this->attendance->lessonClass($lessonId);
        if (!$lesson || (int) $lesson['professor_id'] !== (int) ($user['professor_id'] ?? 0)) {
            jsonResponse(['error' => 'Você só pode acessar aulas das suas turmas.'], 403);
        }
    }
}
