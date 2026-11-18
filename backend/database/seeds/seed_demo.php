<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Execute este gerador somente pelo terminal.\n");
}

$db = databaseConnection(); // Também carrega as variáveis do .env.
$adminEmail = getenv('SEED_ADMIN_EMAIL') ?: 'admin@classmanager.test';
$adminPassword = getenv('SEED_ADMIN_PASSWORD') ?: '';
$demoPassword = getenv('SEED_DEMO_PASSWORD') ?: '';
if (strlen($adminPassword) < 10 || strlen($demoPassword) < 10) {
    fwrite(STDERR, "Defina SEED_ADMIN_PASSWORD e SEED_DEMO_PASSWORD com pelo menos 10 caracteres no .env.\n");
    exit(1);
}

function ensureUser(PDO $db, string $name, string $email, string $role, string $password): int
{
    $find = $db->prepare('SELECT id FROM usuarios WHERE email=:email');
    $find->execute(['email' => strtolower($email)]);
    $id = $find->fetchColumn();
    if ($id !== false) {
        // Reexecutar o seed sincroniza as credenciais demonstrativas com o .env.
        $update = $db->prepare('UPDATE usuarios SET nome=:name, senha_hash=:hash, perfil=:role, ativo=1 WHERE id=:id');
        $update->execute([
            'name' => $name,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'id' => (int) $id,
        ]);
        return (int) $id;
    }
    $insert = $db->prepare('INSERT INTO usuarios (nome,email,senha_hash,perfil,ativo) VALUES (:name,:email,:hash,:role,1)');
    $insert->execute(['name'=>$name,'email'=>strtolower($email),'hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role]);
    return (int) $db->lastInsertId();
}

function ensureTeacher(PDO $db, string $name, string $email, string $phone, string $languages, ?int $userId = null): int
{
    $find = $db->prepare('SELECT id FROM professores WHERE email=:email');
    $find->execute(['email'=>$email]);
    $id = $find->fetchColumn();
    if ($id !== false) {
        if ($userId !== null) { $link=$db->prepare('UPDATE professores SET usuario_id=:user WHERE id=:id AND usuario_id IS NULL'); $link->execute(['user'=>$userId,'id'=>$id]); }
        return (int) $id;
    }
    $insert = $db->prepare('INSERT INTO professores (usuario_id,nome_completo,telefone,email,idiomas,situacao) VALUES (:user,:name,:phone,:email,:languages,\'ativo\')');
    $insert->execute(['user'=>$userId,'name'=>$name,'phone'=>$phone,'email'=>$email,'languages'=>$languages]);
    return (int) $db->lastInsertId();
}

function ensureCourse(PDO $db, string $name, string $language, string $level, int $months, int $hours, float $price): int
{
    $find = $db->prepare('SELECT id FROM cursos WHERE nome=:name AND idioma=:language AND nivel=:level LIMIT 1');
    $find->execute(['name'=>$name,'language'=>$language,'level'=>$level]);
    $id = $find->fetchColumn();
    if ($id !== false) return (int) $id;
    $insert = $db->prepare('INSERT INTO cursos (nome,idioma,nivel,duracao_meses,carga_horaria,valor,ativo) VALUES (:name,:language,:level,:months,:hours,:price,1)');
    $insert->execute(['name'=>$name,'language'=>$language,'level'=>$level,'months'=>$months,'hours'=>$hours,'price'=>$price]);
    return (int) $db->lastInsertId();
}

function ensureClass(PDO $db, string $name, int $course, int $teacher, int $capacity, string $startDate, array $schedules): int
{
    $find = $db->prepare('SELECT id FROM turmas WHERE nome=:name LIMIT 1');
    $find->execute(['name'=>$name]);
    $id = $find->fetchColumn();
    if ($id !== false) return (int) $id;
    $insert = $db->prepare('INSERT INTO turmas (nome,curso_id,professor_id,capacidade,data_inicio,situacao) VALUES (:name,:course,:teacher,:capacity,:start,\'ativa\')');
    $insert->execute(['name'=>$name,'course'=>$course,'teacher'=>$teacher,'capacity'=>$capacity,'start'=>$startDate]);
    $id = (int) $db->lastInsertId();
    $scheduleInsert = $db->prepare('INSERT INTO turma_horarios (turma_id,dia_semana,hora_inicio,hora_fim) VALUES (:class,:day,:start,:end)');
    foreach ($schedules as $schedule) $scheduleInsert->execute(['class'=>$id,'day'=>$schedule[0],'start'=>$schedule[1],'end'=>$schedule[2]]);
    return $id;
}

function ensureStudent(PDO $db, string $name, string $email, string $phone, string $date, ?array $guardian): int
{
    $find = $db->prepare('SELECT id FROM alunos WHERE email=:email LIMIT 1');
    $find->execute(['email'=>$email]);
    $id = $find->fetchColumn();
    if ($id !== false) return (int) $id;
    $guardianId = null;
    if ($guardian !== null) {
        $responsible = $db->prepare('INSERT INTO responsaveis (nome_completo,telefone,email) VALUES (:name,:phone,:email)');
        $responsible->execute(['name'=>$guardian[0],'phone'=>$guardian[1],'email'=>$guardian[2]]);
        $guardianId = (int) $db->lastInsertId();
    }
    $insert = $db->prepare('INSERT INTO alunos (responsavel_id,nome_completo,telefone,email,data_matricula,situacao) VALUES (:guardian,:name,:phone,:email,:date,\'ativo\')');
    $insert->execute(['guardian'=>$guardianId,'name'=>$name,'phone'=>$phone,'email'=>$email,'date'=>$date]);
    return (int) $db->lastInsertId();
}

function ensureEnrollment(PDO $db, int $student, int $class, float $fee, string $date): int
{
    $find = $db->prepare('SELECT id FROM matriculas WHERE aluno_id=:student AND turma_id=:class LIMIT 1');
    $find->execute(['student'=>$student,'class'=>$class]);
    $id = $find->fetchColumn();
    if ($id !== false) return (int) $id;
    $insert = $db->prepare('INSERT INTO matriculas (aluno_id,turma_id,data_matricula,valor_mensalidade,situacao) VALUES (:student,:class,:date,:fee,\'ativa\')');
    $insert->execute(['student'=>$student,'class'=>$class,'date'=>$date,'fee'=>$fee]);
    return (int) $db->lastInsertId();
}

$adminId = ensureUser($db,'Administrador Demonstrativo',$adminEmail,'administrador',$adminPassword);
$secretaryId = ensureUser($db,'Secretaria Demonstrativa','secretaria@classmanager.test','secretaria',$demoPassword);
$professorUserId = ensureUser($db,'Professor Demonstrativo','professor@classmanager.test','professor',$demoPassword);
$teacherA = ensureTeacher($db,'Marina Andrade','marina@classmanager.test','11900001001','Inglês, Espanhol');
$teacherB = ensureTeacher($db,'Rafael Nunes','rafael@classmanager.test','11900001002','Inglês, Francês',$professorUserId);
$teacherC = ensureTeacher($db,'Luiza Campos','luiza@classmanager.test','11900001003','Espanhol, Francês');

$courseA = ensureCourse($db,'Inglês Intermediário','Inglês','intermediário',12,96,320.00);
$courseB = ensureCourse($db,'Espanhol Básico','Espanhol','básico',10,80,280.00);
$courseC = ensureCourse($db,'Francês Básico','Francês','básico',10,80,300.00);
$classA = ensureClass($db,'IN-204',$courseA,$teacherA,12,date('Y-m-d'),[[1,'09:00:00','10:30:00'],[3,'09:00:00','10:30:00']]);
$classB = ensureClass($db,'ES-101',$courseB,$teacherC,12,date('Y-m-d'),[[2,'10:45:00','12:15:00'],[4,'10:45:00','12:15:00']]);
$classC = ensureClass($db,'FR-103',$courseC,$teacherB,10,date('Y-m-d'),[[1,'14:00:00','15:30:00'],[5,'14:00:00','15:30:00']]);

$studentNames = ['Beatriz Oliveira','Lucas Santos','Camila Rocha','Pedro Almeida','Sofia Martins','Helena Costa','Miguel Ferreira','Laura Barros','Arthur Lima','Isabela Melo','Davi Ribeiro','Clara Teixeira'];
$studentIds = [];
foreach ($studentNames as $index => $name) {
    $guardian = $index === 0 ? ['Responsável Demonstrativo','11900002001','responsavel1@example.test'] : ($index === 3 ? ['Responsável Fictício','11900002002','responsavel2@example.test'] : null);
    $studentIds[] = ensureStudent($db,$name,'aluno'.($index+1).'@example.test','1190000'.str_pad((string) ($index+100),4,'0',STR_PAD_LEFT),date('Y-m-d'),$guardian);
}
$studentDemoUserId = ensureUser($db,'Aluno Demonstrativo','aluno1@example.test','aluno',$demoPassword);
$linkStudentUser = $db->prepare('UPDATE alunos SET usuario_id=:user WHERE id=:student AND (usuario_id IS NULL OR usuario_id=:same_user)');
$linkStudentUser->execute(['user'=>$studentDemoUserId,'student'=>$studentIds[0],'same_user'=>$studentDemoUserId]);
if ($linkStudentUser->rowCount() === 0) {
    $studentLink = $db->prepare('SELECT usuario_id FROM alunos WHERE id=:id');
    $studentLink->execute(['id'=>$studentIds[0]]);
    if ((int) $studentLink->fetchColumn() !== $studentDemoUserId) {
        throw new RuntimeException('A conta demonstrativa já está vinculada a outro aluno.');
    }
}

$classIds = [$classA,$classB,$classC];
$enrollmentIds = [];
foreach ($studentIds as $index => $studentId) {
    $classId = $classIds[$index % count($classIds)];
    $enrollmentIds[] = ensureEnrollment($db,$studentId,$classId,[320.00,280.00,300.00][$index % 3],date('Y-m-d'));
}

$today = new DateTimeImmutable('today');
$lessonsByClass = [];
foreach ($classIds as $index => $classId) {
    $date = $today->modify('+' . ($index + 1) . ' days')->format('Y-m-d');
    $find = $db->prepare('SELECT id FROM aulas WHERE turma_id=:class AND data_aula=:date LIMIT 1');
    $find->execute(['class'=>$classId,'date'=>$date]);
    $lessonId = $find->fetchColumn();
    if ($lessonId === false) {
        $insert = $db->prepare('INSERT INTO aulas (turma_id,data_aula,hora_inicio,hora_fim,conteudo) VALUES (:class,:date,\'09:00:00\',\'10:30:00\',:content)');
        $insert->execute(['class'=>$classId,'date'=>$date,'content'=>'Conteúdo demonstrativo da aula']);
        $lessonId = $db->lastInsertId();
    }
    $lessonsByClass[$classId] = (int) $lessonId;
}

$attendance = $db->prepare("INSERT INTO frequencias (aula_id,aluno_id,situacao) VALUES (:lesson,:student,'presente') ON DUPLICATE KEY UPDATE situacao=VALUES(situacao)");
foreach ($studentIds as $index => $studentId) $attendance->execute(['lesson'=>$lessonsByClass[$classIds[$index % count($classIds)]],'student'=>$studentId]);

$reference = date('Y-m');
$dueDate = $today->modify('+10 days')->format('Y-m-d');
foreach ($enrollmentIds as $index => $enrollmentId) {
    $find = $db->prepare('SELECT id FROM mensalidades WHERE matricula_id=:enrollment AND referencia=:reference');
    $find->execute(['enrollment'=>$enrollmentId,'reference'=>$reference]);
    $invoiceId = $find->fetchColumn();
    if ($invoiceId === false) {
        $insert = $db->prepare("INSERT INTO mensalidades (matricula_id,referencia,valor,vencimento,situacao) VALUES (:enrollment,:reference,:value,:due,'pendente')");
        $insert->execute(['enrollment'=>$enrollmentId,'reference'=>$reference,'value'=>[320.00,280.00,300.00][$index % 3],'due'=>$dueDate]);
        $invoiceId = (int) $db->lastInsertId();
    } else { $invoiceId = (int) $invoiceId; }
    if ($index % 3 === 0) {
        $paymentCheck = $db->prepare('SELECT COUNT(*) FROM pagamentos WHERE mensalidade_id=:invoice');
        $paymentCheck->execute(['invoice'=>$invoiceId]);
        if ((int) $paymentCheck->fetchColumn() === 0) {
            $payment = $db->prepare("INSERT INTO pagamentos (mensalidade_id,valor,data_pagamento,forma_pagamento,observacoes) VALUES (:invoice,:value,NOW(),'pix','Pagamento demonstrativo')");
            $payment->execute(['invoice'=>$invoiceId,'value'=>[320.00,280.00,300.00][$index % 3]]);
            $update = $db->prepare("UPDATE mensalidades SET situacao='pago' WHERE id=:id"); $update->execute(['id'=>$invoiceId]);
        }
    }
}

// Conteúdo de demonstração para os módulos novos da V2. O seed pode ser
// executado novamente sem duplicar estas avaliações e este comunicado.
$gradeTitle = 'Avaliação diagnóstica';
$gradeDate = date('Y-m-d');
$findGrade = $db->prepare('SELECT id FROM notas WHERE matricula_id=:enrollment AND titulo=:title AND data_avaliacao=:date LIMIT 1');
$insertGrade = $db->prepare('INSERT INTO notas (matricula_id,titulo,nota,nota_maxima,data_avaliacao,observacoes) VALUES (:enrollment,:title,:grade,10,:date,:notes)');
foreach (array_slice($enrollmentIds, 0, 6) as $index => $enrollmentId) {
    $findGrade->execute(['enrollment'=>$enrollmentId,'title'=>$gradeTitle,'date'=>$gradeDate]);
    if ($findGrade->fetchColumn() === false) {
        $insertGrade->execute(['enrollment'=>$enrollmentId,'title'=>$gradeTitle,'grade'=>6.5 + ($index % 5) * 0.7,'date'=>$gradeDate,'notes'=>'Registro fictício de demonstração.']);
    }
}
$findAnnouncement = $db->prepare("SELECT id FROM comunicados WHERE titulo='Bem-vindos ao ClassManager V2' AND turma_id IS NULL LIMIT 1");
$findAnnouncement->execute();
if ($findAnnouncement->fetchColumn() === false) {
    $announcement = $db->prepare("INSERT INTO comunicados (titulo,mensagem,publico,autor_id) VALUES ('Bem-vindos ao ClassManager V2',:message,'todos',:author)");
    $announcement->execute(['message'=>'Este é um comunicado fictício de demonstração. Consulte aqui avisos acadêmicos e atualizações de turmas.','author'=>$adminId]);
}

echo "Dados fictícios criados ou preservados sem duplicação.\n";
echo "Contas: {$adminEmail}, secretaria@classmanager.test, professor@classmanager.test, aluno1@example.test\n";
echo "As senhas ficam no .env: SEED_ADMIN_PASSWORD e SEED_DEMO_PASSWORD.\n";
