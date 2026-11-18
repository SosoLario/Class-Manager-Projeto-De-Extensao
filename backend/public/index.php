<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/utils/response.php';
require_once dirname(__DIR__) . '/utils/security.php';
require_once dirname(__DIR__) . '/models/Student.php';
require_once dirname(__DIR__) . '/services/AuthService.php';
require_once dirname(__DIR__) . '/services/SchoolDataService.php';
require_once dirname(__DIR__) . '/services/AttendanceService.php';
require_once dirname(__DIR__) . '/services/DashboardService.php';
require_once dirname(__DIR__) . '/services/StudentPortalService.php';
require_once dirname(__DIR__) . '/services/AcademicService.php';
require_once dirname(__DIR__) . '/controllers/StudentController.php';
require_once dirname(__DIR__) . '/controllers/AuthController.php';
require_once dirname(__DIR__) . '/controllers/ResourceController.php';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
startAppSession();

$route = trim((string) ($_GET['route'] ?? ''), '/');
$segments = $route === '' ? [] : explode('/', $route);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($segments === [] && $method === 'GET') {
    jsonResponse(['name' => 'ClassManager API', 'status' => 'ready']);
}

try {
    if (($segments[0] ?? '') === 'auth') {
        $auth = new AuthController(new AuthService(databaseConnection()));
        $auth->handle((string) ($segments[1] ?? ''), $method);
    }

    $user = requireSignedInUser();
    $data = new SchoolDataService(databaseConnection());
    $academic = new AcademicService(databaseConnection());

    if (($segments[0] ?? '') === 'calendar' && $method === 'GET' && count($segments) === 1) {
        $from = (string)($_GET['from'] ?? date('Y-m-d', strtotime('monday this week')));
        $to = (string)($_GET['to'] ?? date('Y-m-d', strtotime('sunday this week')));
        jsonResponse(['data'=>$academic->calendar($user,$from,$to)]);
    }
    if (($segments[0] ?? '') === 'grades') {
        $role=$user['perfil']??'';
        if ($method==='GET') {
            if (!in_array($role,['administrador','secretaria','professor','aluno'],true)) jsonResponse(['error'=>'Acesso negado.'],403);
            jsonResponse(['data'=>$academic->grades($user)]);
        }
        if (!in_array($role,['administrador','professor'],true)) jsonResponse(['error'=>'Seu perfil não pode alterar notas.'],403);
        verifyCsrfToken();
        if ($method==='DELETE' && isset($segments[1]) && ctype_digit($segments[1])) { $academic->deleteGrade((int)$segments[1],$user); jsonResponse(['message'=>'Nota removida.']); }
        if (($method==='POST' && !isset($segments[1])) || ($method==='PUT' && isset($segments[1]) && ctype_digit($segments[1]))) {
            $saved=$academic->saveGrade(requestBody(),$user,$method==='PUT'?(int)$segments[1]:null); jsonResponse(['data'=>['id'=>$saved]],$method==='POST'?201:200);
        }
        jsonResponse(['error'=>'Método ou rota de notas inválido.'],405);
    }
    if (($segments[0] ?? '') === 'announcements') {
        if ($method==='GET' && count($segments)===1) jsonResponse(['data'=>$academic->announcements($user)]);
        if ($method==='POST' && count($segments)===1) { verifyCsrfToken(); $id=$academic->publishAnnouncement(requestBody(),$user); jsonResponse(['data'=>['id'=>$id]],201); }
        jsonResponse(['error'=>'Método ou rota de comunicados inválido.'],405);
    }

    if (($segments[0] ?? '') === 'student-portal' && count($segments) === 1 && $method === 'GET') {
        $studentId = (int) ($user['aluno_id'] ?? 0);
        if (($user['perfil'] ?? '') !== 'aluno' || $studentId < 1) jsonResponse(['error' => 'Acesso permitido somente para a conta de aluno.'], 403);
        jsonResponse(['data' => (new StudentPortalService(databaseConnection()))->summary($studentId)]);
    }

    if (($segments[0] ?? '') === 'dashboard' && count($segments) === 1 && $method === 'GET') {
        if (!in_array($user['perfil'] ?? '', ['administrador','secretaria','professor'], true)) jsonResponse(['error' => 'Acesso negado.'], 403);
        jsonResponse(['data' => (new DashboardService(databaseConnection()))->summary($user)]);
    }

    if (($segments[0] ?? '') === 'options' && isset($segments[1]) && count($segments) === 2) {
        $allowedModules = ['students','teachers','courses','classes','enrollments','invoices','substitute_teachers'];
        if (!in_array($segments[1], $allowedModules, true)) jsonResponse(['error' => 'Opções não encontradas.'], 404);
        (new ResourceController($data, new AttendanceService(databaseConnection()), new StudentController(new Student(databaseConnection()))))
            ->options($segments[1], $user);
    }

    $module = (string) ($segments[0] ?? '');
    $id = null;
    if (isset($segments[1])) {
        if (!ctype_digit($segments[1]) || (int) $segments[1] < 1) jsonResponse(['error' => 'Identificador inválido.'], 400);
        $id = (int) $segments[1];
    }
    if (count($segments) > 2) jsonResponse(['error' => 'Rota não encontrada.'], 404);

    $controller = new ResourceController($data, new AttendanceService(databaseConnection()), new StudentController(new Student(databaseConnection())));
    $controller->handle($module, $id, $method, $user);
} catch (DomainException $error) {
    jsonResponse(['error'=>$error->getMessage()],403);
} catch (OutOfBoundsException $error) {
    jsonResponse(['error'=>$error->getMessage()],404);
} catch (InvalidArgumentException $error) {
    jsonResponse(['error' => $error->getMessage()], 422);
} catch (PDOException $error) {
    error_log('[ClassManager] Database error: ' . $error->getMessage());
    $sqlState = (string) $error->getCode();
    $driverCode = (int) ($error->errorInfo[1] ?? 0);
    if (str_contains(strtolower($error->getMessage()), 'could not find driver')) {
        jsonResponse(['error' => 'O PHP está sem o driver PDO MySQL. Habilite a extensão pdo_mysql na instalação do PHP.'], 503);
    }
    if ($sqlState === '23000') jsonResponse(['error' => 'O registro já existe ou está vinculado a outros dados. Confira os campos informados.'], 409);
    if ($driverCode === 1049) jsonResponse(['error' => 'O banco classmanager_v2 ainda não existe. Execute as migrações SQL desta versão.'], 503);
    if ($driverCode === 1146 || $sqlState === '42S02') jsonResponse(['error' => 'As tabelas do módulo ainda não foram criadas. Execute as migrações SQL correspondentes.'], 503);
    if ($driverCode === 1045 || $sqlState === '28000') jsonResponse(['error' => 'O MySQL recusou o acesso. Confira DB_USER e DB_PASSWORD no arquivo .env.'], 503);
    if (in_array($driverCode, [2002,2003,2005], true)) jsonResponse(['error' => 'Não foi possível acessar o MySQL. Confira se ele está iniciado no XAMPP e se DB_HOST e DB_PORT estão corretos.'], 503);
    jsonResponse(['error' => 'Erro no MySQL. Confira a conexão do XAMPP, as credenciais no .env e as migrações SQL.'], 503);
} catch (Throwable $error) {
    error_log('[ClassManager] API error: ' . $error->getMessage());
    jsonResponse(['error' => 'Ocorreu um erro ao processar a solicitação.'], 500);
}
