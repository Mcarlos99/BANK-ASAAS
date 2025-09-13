<?php
/**
 * API.PHP - SISTEMA DE MENSALIDADES
 * PARTE 1/4 - CONFIGURAÇÃO E ESTRUTURA BASE
 * Versão: 3.0
 * Data: 2025
 */

// Configurações de segurança e headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Tratamento de requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuração de timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Configuração do banco de dados
class DatabaseConfig {
    const HOST = 'localhost';
    const DB_NAME = 'mensalidades_db';
    const USERNAME = 'root';
    const PASSWORD = '';
    const CHARSET = 'utf8mb4';
    
    private static $instance = null;
    private $connection = null;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . self::HOST . ";dbname=" . self::DB_NAME . ";charset=" . self::CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->connection = new PDO($dsn, self::USERNAME, self::PASSWORD, $options);
        } catch (PDOException $e) {
            error_log("Erro de conexão com banco: " . $e->getMessage());
            throw new Exception("Erro interno do servidor");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

// Classe para logs do sistema
class Logger {
    private static $logFile = 'logs/api_mensalidades.log';
    
    public static function log($message, $level = 'INFO', $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' - Context: ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        // Criar diretório de logs se não existir
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    public static function error($message, $context = []) {
        self::log($message, 'ERROR', $context);
    }
    
    public static function warning($message, $context = []) {
        self::log($message, 'WARNING', $context);
    }
    
    public static function info($message, $context = []) {
        self::log($message, 'INFO', $context);
    }
}

// Classe para validações
class Validator {
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validateCPF($cpf) {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }
    
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    public static function validateMoney($value) {
        return is_numeric($value) && $value >= 0;
    }
    
    public static function sanitizeString($string) {
        return trim(filter_var($string, FILTER_SANITIZE_STRING));
    }
    
    public static function validateRequired($data, $requiredFields) {
        $missing = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        return $missing;
    }
}

// Classe para respostas da API
class ApiResponse {
    public static function success($data = null, $message = 'Sucesso', $statusCode = 200) {
        http_response_code($statusCode);
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    public static function error($message = 'Erro interno', $statusCode = 500, $errors = []) {
        http_response_code($statusCode);
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        Logger::error("API Error: {$message}", ['status' => $statusCode, 'errors' => $errors]);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    public static function notFound($message = 'Recurso não encontrado') {
        self::error($message, 404);
    }
    
    public static function badRequest($message = 'Dados inválidos', $errors = []) {
        self::error($message, 400, $errors);
    }
    
    public static function unauthorized($message = 'Não autorizado') {
        self::error($message, 401);
    }
}

// Classe principal da API
class MensalidadeAPI {
    private $db;
    private $allowedActions = [
        'get_students',
        'get_student',
        'create_student',
        'update_student',
        'delete_student',
        'get_installments',
        'get_student_installments',
        'create_installment',
        'create_installment_with_discount', // Ação corrigida
        'update_installment',
        'delete_installment',
        'pay_installment',
        'get_dashboard_data',
        'export_data',
        'get_reports'
    ];
    
    public function __construct() {
        try {
            $this->db = DatabaseConfig::getInstance()->getConnection();
        } catch (Exception $e) {
            ApiResponse::error("Erro de conexão com banco de dados");
        }
    }
    
    public function handleRequest() {
        try {
            // Log da requisição
            Logger::info("Nova requisição", [
                'method' => $_SERVER['REQUEST_METHOD'],
                'uri' => $_SERVER['REQUEST_URI'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            
            $method = $_SERVER['REQUEST_METHOD'];
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validar se é POST/PUT/DELETE
            if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
                if ($input === null && !empty(file_get_contents('php://input'))) {
                    ApiResponse::badRequest('JSON inválido');
                }
            }
            
            // Obter ação
            $action = $input['action'] ?? $_GET['action'] ?? null;
            
            if (!$action) {
                ApiResponse::badRequest('Ação não especificada');
            }
            
            if (!in_array($action, $this->allowedActions)) {
                ApiResponse::badRequest("Ação não reconhecida: {$action}");
            }
            
            // Executar ação
            $this->executeAction($action, $input ?? []);
            
        } catch (Exception $e) {
            Logger::error("Erro não tratado: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            ApiResponse::error("Erro interno do servidor");
        }
    }
    
    private function executeAction($action, $data) {
        switch ($action) {
            // Ações de estudantes
            case 'get_students':
                $this->getStudents($data);
                break;
            case 'get_student':
                $this->getStudent($data);
                break;
            case 'create_student':
                $this->createStudent($data);
                break;
            case 'update_student':
                $this->updateStudent($data);
                break;
            case 'delete_student':
                $this->deleteStudent($data);
                break;
                
            // Ações de mensalidades
            case 'get_installments':
                $this->getInstallments($data);
                break;
            case 'get_student_installments':
                $this->getStudentInstallments($data);
                break;
            case 'create_installment':
                $this->createInstallment($data);
                break;
            case 'create_installment_with_discount': // Método corrigido
                $this->createInstallmentWithDiscount($data);
                break;
            case 'update_installment':
                $this->updateInstallment($data);
                break;
            case 'delete_installment':
                $this->deleteInstallment($data);
                break;
            case 'pay_installment':
                $this->payInstallment($data);
                break;
                
            // Ações de relatórios
            case 'get_dashboard_data':
                $this->getDashboardData($data);
                break;
            case 'export_data':
                $this->exportData($data);
                break;
            case 'get_reports':
                $this->getReports($data);
                break;
                
            default:
                ApiResponse::badRequest("Ação não implementada: {$action}");
        }
    }
}
/**
     * PARTE 2/4 - MÉTODOS DE ESTUDANTES (CRUD COMPLETO)
     * Todos os métodos para gerenciamento de estudantes
     */
    
    /**
     * Listar todos os estudantes com filtros e paginação
     */
    private function getStudents($data) {
        try {
            // Parâmetros de paginação
            $page = max(1, intval($data['page'] ?? 1));
            $limit = min(100, max(1, intval($data['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            // Parâmetros de filtro
            $search = $data['search'] ?? '';
            $status = $data['status'] ?? '';
            $course = $data['course'] ?? '';
            
            // Construir query base
            $whereConditions = [];
            $params = [];
            
            if (!empty($search)) {
                $whereConditions[] = "(name LIKE :search OR email LIKE :search OR cpf LIKE :search)";
                $params['search'] = "%{$search}%";
            }
            
            if (!empty($status)) {
                $whereConditions[] = "status = :status";
                $params['status'] = $status;
            }
            
            if (!empty($course)) {
                $whereConditions[] = "course LIKE :course";
                $params['course'] = "%{$course}%";
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Query para contar total
            $countSql = "SELECT COUNT(*) as total FROM students {$whereClause}";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Query principal com paginação
            $sql = "SELECT 
                        id, name, email, cpf, phone, course, status, 
                        created_at, updated_at,
                        (SELECT COUNT(*) FROM installments WHERE student_id = students.id) as total_installments,
                        (SELECT COUNT(*) FROM installments WHERE student_id = students.id AND status = 'paid') as paid_installments,
                        (SELECT COALESCE(SUM(amount), 0) FROM installments WHERE student_id = students.id AND status = 'pending') as pending_amount
                    FROM students 
                    {$whereClause}
                    ORDER BY created_at DESC 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            
            // Bind parâmetros
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $students = $stmt->fetchAll();
            
            // Formatar dados
            foreach ($students as &$student) {
                $student['pending_amount'] = number_format($student['pending_amount'], 2, '.', '');
                $student['created_at'] = date('d/m/Y H:i', strtotime($student['created_at']));
                $student['updated_at'] = date('d/m/Y H:i', strtotime($student['updated_at']));
            }
            
            $response = [
                'students' => $students,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => intval($total),
                    'total_pages' => ceil($total / $limit),
                    'has_next' => $page < ceil($total / $limit),
                    'has_prev' => $page > 1
                ]
            ];
            
            Logger::info("Listagem de estudantes", ['total' => $total, 'page' => $page]);
            ApiResponse::success($response, "Estudantes listados com sucesso");
            
        } catch (Exception $e) {
            Logger::error("Erro ao listar estudantes: " . $e->getMessage());
            ApiResponse::error("Erro ao listar estudantes");
        }
    }
    
    /**
     * Obter um estudante específico por ID
     */
    private function getStudent($data) {
        try {
            $studentId = $data['id'] ?? null;
            
            if (!$studentId) {
                ApiResponse::badRequest("ID do estudante é obrigatório");
            }
            
            $sql = "SELECT 
                        s.*,
                        COUNT(i.id) as total_installments,
                        COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as paid_installments,
                        COUNT(CASE WHEN i.status = 'pending' THEN 1 END) as pending_installments,
                        COUNT(CASE WHEN i.status = 'overdue' THEN 1 END) as overdue_installments,
                        COALESCE(SUM(CASE WHEN i.status = 'pending' THEN i.amount END), 0) as pending_amount,
                        COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.amount END), 0) as paid_amount
                    FROM students s
                    LEFT JOIN installments i ON s.id = i.student_id
                    WHERE s.id = :id
                    GROUP BY s.id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $studentId]);
            $student = $stmt->fetch();
            
            if (!$student) {
                ApiResponse::notFound("Estudante não encontrado");
            }
            
            // Formatar valores monetários
            $student['pending_amount'] = number_format($student['pending_amount'], 2, '.', '');
            $student['paid_amount'] = number_format($student['paid_amount'], 2, '.', '');
            $student['created_at'] = date('d/m/Y H:i', strtotime($student['created_at']));
            $student['updated_at'] = date('d/m/Y H:i', strtotime($student['updated_at']));
            
            Logger::info("Consulta de estudante", ['student_id' => $studentId]);
            ApiResponse::success($student, "Estudante encontrado");
            
        } catch (Exception $e) {
            Logger::error("Erro ao buscar estudante: " . $e->getMessage());
            ApiResponse::error("Erro ao buscar estudante");
        }
    }
    
    /**
     * Criar novo estudante
     */
    private function createStudent($data) {
        try {
            // Validar campos obrigatórios
            $required = ['name', 'email', 'cpf', 'course'];
            $missing = Validator::validateRequired($data, $required);
            
            if (!empty($missing)) {
                ApiResponse::badRequest("Campos obrigatórios faltando", $missing);
            }
            
            // Validações específicas
            $errors = [];
            
            if (!Validator::validateEmail($data['email'])) {
                $errors[] = "Email inválido";
            }
            
            if (!Validator::validateCPF($data['cpf'])) {
                $errors[] = "CPF inválido";
            }
            
            if (!empty($errors)) {
                ApiResponse::badRequest("Dados inválidos", $errors);
            }
            
            // Verificar se email ou CPF já existem
            $checkSql = "SELECT id, email, cpf FROM students WHERE email = :email OR cpf = :cpf";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([
                'email' => $data['email'],
                'cpf' => preg_replace('/[^0-9]/', '', $data['cpf'])
            ]);
            
            if ($existing = $checkStmt->fetch()) {
                if ($existing['email'] === $data['email']) {
                    ApiResponse::badRequest("Email já está em uso");
                }
                if ($existing['cpf'] === preg_replace('/[^0-9]/', '', $data['cpf'])) {
                    ApiResponse::badRequest("CPF já está cadastrado");
                }
            }
            
            // Sanitizar dados
            $studentData = [
                'name' => Validator::sanitizeString($data['name']),
                'email' => filter_var($data['email'], FILTER_SANITIZE_EMAIL),
                'cpf' => preg_replace('/[^0-9]/', '', $data['cpf']),
                'phone' => preg_replace('/[^0-9]/', '', $data['phone'] ?? ''),
                'course' => Validator::sanitizeString($data['course']),
                'status' => in_array($data['status'] ?? 'active', ['active', 'inactive']) ? $data['status'] : 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Inserir no banco
            $sql = "INSERT INTO students (name, email, cpf, phone, course, status, created_at, updated_at) 
                    VALUES (:name, :email, :cpf, :phone, :course, :status, :created_at, :updated_at)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($studentData);
            
            $studentId = $this->db->lastInsertId();
            
            // Buscar estudante criado
            $createdStudent = $this->db->prepare("SELECT * FROM students WHERE id = :id");
            $createdStudent->execute(['id' => $studentId]);
            $student = $createdStudent->fetch();
            
            Logger::info("Estudante criado", ['student_id' => $studentId, 'name' => $studentData['name']]);
            ApiResponse::success($student, "Estudante criado com sucesso", 201);
            
        } catch (Exception $e) {
            Logger::error("Erro ao criar estudante: " . $e->getMessage());
            ApiResponse::error("Erro ao criar estudante");
        }
    }
    
    /**
     * Atualizar estudante existente
     */
    private function updateStudent($data) {
        try {
            $studentId = $data['id'] ?? null;
            
            if (!$studentId) {
                ApiResponse::badRequest("ID do estudante é obrigatório");
            }
            
            // Verificar se estudante existe
            $checkStmt = $this->db->prepare("SELECT id FROM students WHERE id = :id");
            $checkStmt->execute(['id' => $studentId]);
            
            if (!$checkStmt->fetch()) {
                ApiResponse::notFound("Estudante não encontrado");
            }
            
            // Campos que podem ser atualizados
            $allowedFields = ['name', 'email', 'cpf', 'phone', 'course', 'status'];
            $updateData = [];
            $updateFields = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    switch ($field) {
                        case 'email':
                            if (!Validator::validateEmail($data[$field])) {
                                ApiResponse::badRequest("Email inválido");
                            }
                            
                            // Verificar se email já existe em outro estudante
                            $emailCheck = $this->db->prepare("SELECT id FROM students WHERE email = :email AND id != :id");
                            $emailCheck->execute(['email' => $data[$field], 'id' => $studentId]);
                            if ($emailCheck->fetch()) {
                                ApiResponse::badRequest("Email já está em uso por outro estudante");
                            }
                            
                            $updateData[$field] = filter_var($data[$field], FILTER_SANITIZE_EMAIL);
                            break;
                            
                        case 'cpf':
                            if (!Validator::validateCPF($data[$field])) {
                                ApiResponse::badRequest("CPF inválido");
                            }
                            
                            $cleanCpf = preg_replace('/[^0-9]/', '', $data[$field]);
                            
                            // Verificar se CPF já existe em outro estudante
                            $cpfCheck = $this->db->prepare("SELECT id FROM students WHERE cpf = :cpf AND id != :id");
                            $cpfCheck->execute(['cpf' => $cleanCpf, 'id' => $studentId]);
                            if ($cpfCheck->fetch()) {
                                ApiResponse::badRequest("CPF já está cadastrado para outro estudante");
                            }
                            
                            $updateData[$field] = $cleanCpf;
                            break;
                            
                        case 'phone':
                            $updateData[$field] = preg_replace('/[^0-9]/', '', $data[$field]);
                            break;
                            
                        case 'status':
                            if (!in_array($data[$field], ['active', 'inactive'])) {
                                ApiResponse::badRequest("Status inválido. Use 'active' ou 'inactive'");
                            }
                            $updateData[$field] = $data[$field];
                            break;
                            
                        default:
                            $updateData[$field] = Validator::sanitizeString($data[$field]);
                            break;
                    }
                    
                    $updateFields[] = "$field = :$field";
                }
            }
            
            if (empty($updateFields)) {
                ApiResponse::badRequest("Nenhum campo válido para atualizar");
            }
            
            // Adicionar timestamp de atualização
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $updateFields[] = "updated_at = :updated_at";
            
            // Atualizar no banco
            $sql = "UPDATE students SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $updateData['id'] = $studentId;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($updateData);
            
            // Buscar estudante atualizado
            $updatedStudent = $this->db->prepare("SELECT * FROM students WHERE id = :id");
            $updatedStudent->execute(['id' => $studentId]);
            $student = $updatedStudent->fetch();
            
            Logger::info("Estudante atualizado", ['student_id' => $studentId, 'fields' => array_keys($updateData)]);
            ApiResponse::success($student, "Estudante atualizado com sucesso");
            
        } catch (Exception $e) {
            Logger::error("Erro ao atualizar estudante: " . $e->getMessage());
            ApiResponse::error("Erro ao atualizar estudante");
        }
    }
    
    /**
     * Excluir estudante (soft delete)
     */
    private function deleteStudent($data) {
        try {
            $studentId = $data['id'] ?? null;
            $forceDelete = $data['force'] ?? false;
            
            if (!$studentId) {
                ApiResponse::badRequest("ID do estudante é obrigatório");
            }
            
            // Verificar se estudante existe
            $checkStmt = $this->db->prepare("SELECT id, name FROM students WHERE id = :id");
            $checkStmt->execute(['id' => $studentId]);
            $student = $checkStmt->fetch();
            
            if (!$student) {
                ApiResponse::notFound("Estudante não encontrado");
            }
            
            // Verificar se tem mensalidades pendentes
            $installmentsCheck = $this->db->prepare("SELECT COUNT(*) as count FROM installments WHERE student_id = :id AND status IN ('pending', 'overdue')");
            $installmentsCheck->execute(['id' => $studentId]);
            $pendingCount = $installmentsCheck->fetch()['count'];
            
            if ($pendingCount > 0 && !$forceDelete) {
                ApiResponse::badRequest("Não é possível excluir estudante com mensalidades pendentes. Use force=true para forçar exclusão.");
            }
            
            $this->db->beginTransaction();
            
            try {
                if ($forceDelete) {
                    // Deletar todas as mensalidades primeiro
                    $deleteInstallments = $this->db->prepare("DELETE FROM installments WHERE student_id = :id");
                    $deleteInstallments->execute(['id' => $studentId]);
                    
                    // Deletar estudante completamente
                    $deleteStudent = $this->db->prepare("DELETE FROM students WHERE id = :id");
                    $deleteStudent->execute(['id' => $studentId]);
                    
                    Logger::warning("Estudante e mensalidades excluídos permanentemente", [
                        'student_id' => $studentId, 
                        'name' => $student['name'],
                        'installments_deleted' => $pendingCount
                    ]);
                } else {
                    // Soft delete - marcar como inativo
                    $softDelete = $this->db->prepare("UPDATE students SET status = 'inactive', updated_at = :updated_at WHERE id = :id");
                    $softDelete->execute([
                        'id' => $studentId,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    Logger::info("Estudante marcado como inativo", [
                        'student_id' => $studentId, 
                        'name' => $student['name']
                    ]);
                }
                
                $this->db->commit();
                
                $message = $forceDelete ? "Estudante excluído permanentemente" : "Estudante marcado como inativo";
                ApiResponse::success(null, $message);
                
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            Logger::error("Erro ao excluir estudante: " . $e->getMessage());
            ApiResponse::error("Erro ao excluir estudante");
        }
    }
    /**
     * PARTE 3/4 - MÉTODOS DE MENSALIDADES (CRUD + PAGAMENTOS)
     * Todos os métodos para gerenciamento de mensalidades e pagamentos
     */
    
    /**
     * Listar todas as mensalidades com filtros avançados
     */
    private function getInstallments($data) {
        try {
            // Parâmetros de paginação
            $page = max(1, intval($data['page'] ?? 1));
            $limit = min(100, max(1, intval($data['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            // Parâmetros de filtro
            $status = $data['status'] ?? '';
            $studentId = $data['student_id'] ?? '';
            $month = $data['month'] ?? '';
            $year = $data['year'] ?? '';
            $overdue = $data['overdue'] ?? false;
            
            // Construir query base
            $whereConditions = [];
            $params = [];
            
            if (!empty($status)) {
                $whereConditions[] = "i.status = :status";
                $params['status'] = $status;
            }
            
            if (!empty($studentId)) {
                $whereConditions[] = "i.student_id = :student_id";
                $params['student_id'] = $studentId;
            }
            
            if (!empty($month) && !empty($year)) {
                $whereConditions[] = "MONTH(i.due_date) = :month AND YEAR(i.due_date) = :year";
                $params['month'] = $month;
                $params['year'] = $year;
            }
            
            if ($overdue) {
                $whereConditions[] = "i.due_date < CURDATE() AND i.status = 'pending'";
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Query para contar total
            $countSql = "SELECT COUNT(*) as total 
                        FROM installments i 
                        LEFT JOIN students s ON i.student_id = s.id 
                        {$whereClause}";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Query principal
            $sql = "SELECT 
                        i.id, i.student_id, i.amount, i.due_date, i.paid_date, 
                        i.status, i.discount, i.payment_method, i.notes,
                        i.created_at, i.updated_at,
                        s.name as student_name, s.email as student_email,
                        s.course as student_course,
                        CASE 
                            WHEN i.due_date < CURDATE() AND i.status = 'pending' THEN DATEDIFF(CURDATE(), i.due_date)
                            ELSE 0
                        END as days_overdue
                    FROM installments i
                    LEFT JOIN students s ON i.student_id = s.id
                    {$whereClause}
                    ORDER BY i.due_date DESC
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            
            // Bind parâmetros
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $installments = $stmt->fetchAll();
            
            // Formatar dados
            foreach ($installments as &$installment) {
                $installment['amount'] = number_format($installment['amount'], 2, '.', '');
                $installment['discount'] = number_format($installment['discount'] ?? 0, 2, '.', '');
                $installment['due_date'] = date('d/m/Y', strtotime($installment['due_date']));
                $installment['paid_date'] = $installment['paid_date'] ? date('d/m/Y H:i', strtotime($installment['paid_date'])) : null;
                $installment['created_at'] = date('d/m/Y H:i', strtotime($installment['created_at']));
                $installment['is_overdue'] = $installment['days_overdue'] > 0;
            }
            
            $response = [
                'installments' => $installments,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => intval($total),
                    'total_pages' => ceil($total / $limit),
                    'has_next' => $page < ceil($total / $limit),
                    'has_prev' => $page > 1
                ]
            ];
            
            Logger::info("Listagem de mensalidades", ['total' => $total, 'filters' => $data]);
            ApiResponse::success($response, "Mensalidades listadas com sucesso");
            
        } catch (Exception $e) {
            Logger::error("Erro ao listar mensalidades: " . $e->getMessage());
            ApiResponse::error("Erro ao listar mensalidades");
        }
    }
    
    /**
     * Obter mensalidades de um estudante específico
     */
    private function getStudentInstallments($data) {
        try {
            $studentId = $data['student_id'] ?? null;
            
            if (!$studentId) {
                ApiResponse::badRequest("ID do estudante é obrigatório");
            }
            
            // Verificar se estudante existe
            $studentCheck = $this->db->prepare("SELECT id, name FROM students WHERE id = :id");
            $studentCheck->execute(['id' => $studentId]);
            $student = $studentCheck->fetch();
            
            if (!$student) {
                ApiResponse::notFound("Estudante não encontrado");
            }
            
            $sql = "SELECT 
                        id, amount, due_date, paid_date, status, discount, 
                        payment_method, notes, created_at,
                        CASE 
                            WHEN due_date < CURDATE() AND status = 'pending' THEN DATEDIFF(CURDATE(), due_date)
                            ELSE 0
                        END as days_overdue
                    FROM installments 
                    WHERE student_id = :student_id
                    ORDER BY due_date ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['student_id' => $studentId]);
            $installments = $stmt->fetchAll();
            
            // Formatar dados e calcular estatísticas
            $stats = [
                'total' => 0,
                'paid' => 0,
                'pending' => 0,
                'overdue' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'pending_amount' => 0,
                'overdue_amount' => 0
            ];
            
            foreach ($installments as &$installment) {
                $amount = floatval($installment['amount']);
                $discount = floatval($installment['discount'] ?? 0);
                $finalAmount = $amount - $discount;
                
                $stats['total']++;
                $stats['total_amount'] += $finalAmount;
                
                switch ($installment['status']) {
                    case 'paid':
                        $stats['paid']++;
                        $stats['paid_amount'] += $finalAmount;
                        break;
                    case 'pending':
                        if ($installment['days_overdue'] > 0) {
                            $stats['overdue']++;
                            $stats['overdue_amount'] += $finalAmount;
                        } else {
                            $stats['pending']++;
                            $stats['pending_amount'] += $finalAmount;
                        }
                        break;
                }
                
                // Formatar para resposta
                $installment['amount'] = number_format($amount, 2, '.', '');
                $installment['discount'] = number_format($discount, 2, '.', '');
                $installment['final_amount'] = number_format($finalAmount, 2, '.', '');
                $installment['due_date'] = date('d/m/Y', strtotime($installment['due_date']));
                $installment['paid_date'] = $installment['paid_date'] ? date('d/m/Y H:i', strtotime($installment['paid_date'])) : null;
                $installment['is_overdue'] = $installment['days_overdue'] > 0;
            }
            
            // Formatar estatísticas
            foreach (['total_amount', 'paid_amount', 'pending_amount', 'overdue_amount'] as $field) {
                $stats[$field] = number_format($stats[$field], 2, '.', '');
            }
            
            $response = [
                'student' => $student,
                'installments' => $installments,
                'statistics' => $stats
            ];
            
            Logger::info("Consulta mensalidades do estudante", ['student_id' => $studentId]);
            ApiResponse::success($response, "Mensalidades do estudante encontradas");
            
        } catch (Exception $e) {
            Logger::error("Erro ao buscar mensalidades do estudante: " . $e->getMessage());
            ApiResponse::error("Erro ao buscar mensalidades do estudante");
        }
    }
    
    /**
     * Criar nova mensalidade
     */
    private function createInstallment($data) {
        try {
            // Validar campos obrigatórios
            $required = ['student_id', 'amount', 'due_date'];
            $missing = Validator::validateRequired($data, $required);
            
            if (!empty($missing)) {
                ApiResponse::badRequest("Campos obrigatórios faltando", $missing);
            }
            
            // Validações específicas
            $errors = [];
            
            if (!Validator::validateMoney($data['amount'])) {
                $errors[] = "Valor inválido";
            }
            
            if (!Validator::validateDate($data['due_date'])) {
                $errors[] = "Data de vencimento inválida";
            }
            
            if (!empty($errors)) {
                ApiResponse::badRequest("Dados inválidos", $errors);
            }
            
            // Verificar se estudante existe
            $studentCheck = $this->db->prepare("SELECT id, name FROM students WHERE id = :id");
            $studentCheck->execute(['id' => $data['student_id']]);
            $student = $studentCheck->fetch();
            
            if (!$student) {
                ApiResponse::badRequest("Estudante não encontrado");
            }
            
            // Preparar dados
            $installmentData = [
                'student_id' => $data['student_id'],
                'amount' => floatval($data['amount']),
                'due_date' => $data['due_date'],
                'status' => 'pending',
                'discount' => floatval($data['discount'] ?? 0),
                'notes' => Validator::sanitizeString($data['notes'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Inserir no banco
            $sql = "INSERT INTO installments (student_id, amount, due_date, status, discount, notes, created_at, updated_at) 
                    VALUES (:student_id, :amount, :due_date, :status, :discount, :notes, :created_at, :updated_at)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($installmentData);
            
            $installmentId = $this->db->lastInsertId();
            
            // Buscar mensalidade criada
            $createdStmt = $this->db->prepare("SELECT * FROM installments WHERE id = :id");
            $createdStmt->execute(['id' => $installmentId]);
            $installment = $createdStmt->fetch();
            
            Logger::info("Mensalidade criada", [
                'installment_id' => $installmentId,
                'student_id' => $data['student_id'],
                'amount' => $installmentData['amount']
            ]);
            
            ApiResponse::success($installment, "Mensalidade criada com sucesso", 201);
            
        } catch (Exception $e) {
            Logger::error("Erro ao criar mensalidade: " . $e->getMessage());
            ApiResponse::error("Erro ao criar mensalidade");
        }
    }
    
    /**
     * Criar mensalidade COM DESCONTO - MÉTODO CORRIGIDO
     */
    private function createInstallmentWithDiscount($data) {
        try {
            // Validar campos obrigatórios
            $required = ['student_id', 'amount', 'due_date', 'discount'];
            $missing = Validator::validateRequired($data, $required);
            
            if (!empty($missing)) {
                ApiResponse::badRequest("Campos obrigatórios faltando", $missing);
            }
            
            // Validações específicas
            $errors = [];
            
            if (!Validator::validateMoney($data['amount'])) {
                $errors[] = "Valor inválido";
            }
            
            if (!Validator::validateMoney($data['discount'])) {
                $errors[] = "Desconto inválido";
            }
            
            if (!Validator::validateDate($data['due_date'])) {
                $errors[] = "Data de vencimento inválida";
            }
            
            // Validar se desconto não é maior que o valor
            if (floatval($data['discount']) > floatval($data['amount'])) {
                $errors[] = "Desconto não pode ser maior que o valor da mensalidade";
            }
            
            if (!empty($errors)) {
                ApiResponse::badRequest("Dados inválidos", $errors);
            }
            
            // Verificar se estudante existe
            $studentCheck = $this->db->prepare("SELECT id, name FROM students WHERE id = :id");
            $studentCheck->execute(['id' => $data['student_id']]);
            $student = $studentCheck->fetch();
            
            if (!$student) {
                ApiResponse::badRequest("Estudante não encontrado");
            }
            
            $this->db->beginTransaction();
            
            try {
                // Calcular valor final
                $originalAmount = floatval($data['amount']);
                $discount = floatval($data['discount']);
                $finalAmount = $originalAmount - $discount;
                
                // Preparar dados
                $installmentData = [
                    'student_id' => $data['student_id'],
                    'amount' => $originalAmount,
                    'due_date' => $data['due_date'],
                    'status' => 'pending',
                    'discount' => $discount,
                    'discount_reason' => Validator::sanitizeString($data['discount_reason'] ?? 'Desconto aplicado'),
                    'notes' => Validator::sanitizeString($data['notes'] ?? ''),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Inserir mensalidade
                $sql = "INSERT INTO installments (student_id, amount, due_date, status, discount, discount_reason, notes, created_at, updated_at) 
                        VALUES (:student_id, :amount, :due_date, :status, :discount, :discount_reason, :notes, :created_at, :updated_at)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute($installmentData);
                
                $installmentId = $this->db->lastInsertId();
                
                // Log do desconto aplicado
                $logSql = "INSERT INTO discount_logs (installment_id, original_amount, discount_amount, final_amount, reason, applied_by, applied_at)
                          VALUES (:installment_id, :original_amount, :discount_amount, :final_amount, :reason, :applied_by, :applied_at)";
                
                $logStmt = $this->db->prepare($logSql);
                $logStmt->execute([
                    'installment_id' => $installmentId,
                    'original_amount' => $originalAmount,
                    'discount_amount' => $discount,
                    'final_amount' => $finalAmount,
                    'reason' => $installmentData['discount_reason'],
                    'applied_by' => $data['user'] ?? 'sistema',
                    'applied_at' => date('Y-m-d H:i:s')
                ]);
                
                $this->db->commit();
                
                // Buscar mensalidade criada com desconto
                $createdStmt = $this->db->prepare("
                    SELECT i.*, s.name as student_name,
                           (i.amount - i.discount) as final_amount
                    FROM installments i 
                    LEFT JOIN students s ON i.student_id = s.id 
                    WHERE i.id = :id
                ");
                $createdStmt->execute(['id' => $installmentId]);
                $installment = $createdStmt->fetch();
                
                // Formatar valores
                $installment['amount'] = number_format($installment['amount'], 2, '.', '');
                $installment['discount'] = number_format($installment['discount'], 2, '.', '');
                $installment['final_amount'] = number_format($installment['final_amount'], 2, '.', '');
                
                Logger::info("Mensalidade com desconto criada", [
                    'installment_id' => $installmentId,
                    'student_id' => $data['student_id'],
                    'original_amount' => $originalAmount,
                    'discount' => $discount,
                    'final_amount' => $finalAmount
                ]);
                
                ApiResponse::success($installment, "Mensalidade com desconto criada com sucesso", 201);
                
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            Logger::error("Erro ao criar mensalidade com desconto: " . $e->getMessage());
            ApiResponse::error("Erro ao criar mensalidade com desconto");
        }
    }
    
    /**
     * Atualizar mensalidade existente
     */
    private function updateInstallment($data) {
        try {
            $installmentId = $data['id'] ?? null;
            
            if (!$installmentId) {
                ApiResponse::badRequest("ID da mensalidade é obrigatório");
            }
            
            // Verificar se mensalidade existe
            $checkStmt = $this->db->prepare("SELECT id, status FROM installments WHERE id = :id");
            $checkStmt->execute(['id' => $installmentId]);
            $installment = $checkStmt->fetch();
            
            if (!$installment) {
                ApiResponse::notFound("Mensalidade não encontrada");
            }
            
            // Não permitir edição de mensalidades pagas
            if ($installment['status'] === 'paid') {
                ApiResponse::badRequest("Não é possível editar mensalidade já paga");
            }
            
            // Campos que podem ser atualizados
            $allowedFields = ['amount', 'due_date', 'discount', 'notes'];
            $updateData = [];
            $updateFields = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    switch ($field) {
                        case 'amount':
                        case 'discount':
                            if (!Validator::validateMoney($data[$field])) {
                                ApiResponse::badRequest("Valor inválido para {$field}");
                            }
                            $updateData[$field] = floatval($data[$field]);
                            break;
                            
                        case 'due_date':
                            if (!Validator::validateDate($data[$field])) {
                                ApiResponse::badRequest("Data de vencimento inválida");
                            }
                            $updateData[$field] = $data[$field];
                            break;
                            
                        default:
                            $updateData[$field] = Validator::sanitizeString($data[$field]);
                            break;
                    }
                    
                    $updateFields[] = "$field = :$field";
                }
            }
            
            if (empty($updateFields)) {
                ApiResponse::badRequest("Nenhum campo válido para atualizar");
            }
            
            // Validar se desconto não é maior que valor
            if (isset($updateData['amount']) || isset($updateData['discount'])) {
                $currentStmt = $this->db->prepare("SELECT amount, discount FROM installments WHERE id = :id");
                $currentStmt->execute(['id' => $installmentId]);
                $current = $currentStmt->fetch();
                
                $amount = $updateData['amount'] ?? $current['amount'];
                $discount = $updateData['discount'] ?? $current['discount'];
                
                if ($discount > $amount) {
                    ApiResponse::badRequest("Desconto não pode ser maior que o valor da mensalidade");
                }
            }
            
            // Adicionar timestamp de atualização
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $updateFields[] = "updated_at = :updated_at";
            
            // Atualizar no banco
            $sql = "UPDATE installments SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $updateData['id'] = $installmentId;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($updateData);
            
            // Buscar mensalidade atualizada
            $updatedStmt = $this->db->prepare("SELECT * FROM installments WHERE id = :id");
            $updatedStmt->execute(['id' => $installmentId]);
            $updated = $updatedStmt->fetch();
            
            Logger::info("Mensalidade atualizada", [
                'installment_id' => $installmentId,
                'fields' => array_keys($updateData)
            ]);
            
            ApiResponse::success($updated, "Mensalidade atualizada com sucesso");
            
        } catch (Exception $e) {
            Logger::error("Erro ao atualizar mensalidade: " . $e->getMessage());
            ApiResponse::error("Erro ao atualizar mensalidade");
        }
    }
    
    /**
     * Excluir mensalidade
     */
    private function deleteInstallment($data) {
        try {
            $installmentId = $data['id'] ?? null;
            
            if (!$installmentId) {
                ApiResponse::badRequest("ID da mensalidade é obrigatório");
            }
            
            // Verificar se mensalidade existe
            $checkStmt = $this->db->prepare("SELECT id, status, amount FROM installments WHERE id = :id");
            $checkStmt->execute(['id' => $installmentId]);
            $installment = $checkStmt->fetch();
            
            if (!$installment) {
                ApiResponse::notFound("Mensalidade não encontrada");
            }
            
            // Não permitir exclusão de mensalidades pagas
            if ($installment['status'] === 'paid') {
                ApiResponse::badRequest("Não é possível excluir mensalidade já paga");
            }
            
            // Excluir mensalidade
            $deleteStmt = $this->db->prepare("DELETE FROM installments WHERE id = :id");
            $deleteStmt->execute(['id' => $installmentId]);
            
            Logger::info("Mensalidade excluída", [
                'installment_id' => $installmentId,
                'amount' => $installment['amount']
            ]);
            
            ApiResponse::success(null, "Mensalidade excluída com sucesso");
            
        } catch (Exception $e) {
            Logger::error("Erro ao excluir mensalidade: " . $e->getMessage());
            ApiResponse::error("Erro ao excluir mensalidade");
        }
    }
    
    /**
     * Processar pagamento de mensalidade
     */
    private function payInstallment($data) {
        try {
            $installmentId = $data['id'] ?? null;
            $paymentMethod = $data['payment_method'] ?? 'dinheiro';
            $paymentDate = $data['payment_date'] ?? date('Y-m-d H:i:s');
            $notes = $data['notes'] ?? '';
            
            if (!$installmentId) {
                ApiResponse::badRequest("ID da mensalidade é obrigatório");
            }
            
            // Verificar se mensalidade existe e está pendente
            $checkStmt = $this->db->prepare("
                SELECT i.*, s.name as student_name 
                FROM installments i 
                LEFT JOIN students s ON i.student_id = s.id 
                WHERE i.id = :id
            ");
            $checkStmt->execute(['id' => $installmentId]);
            $installment = $checkStmt->fetch();
            
            if (!$installment) {
                ApiResponse::notFound("Mensalidade não encontrada");
            }
            
            if ($installment['status'] === 'paid') {
                ApiResponse::badRequest("Mensalidade já está paga");
            }
            
            $this->db->beginTransaction();
            
            try {
                // Atualizar status da mensalidade
                $updateStmt = $this->db->prepare("
                    UPDATE installments 
                    SET status = 'paid', 
                        paid_date = :paid_date, 
                        payment_method = :payment_method,
                        notes = CONCAT(COALESCE(notes, ''), ' - Pago: ', :notes),
                        updated_at = :updated_at
                    WHERE id = :id
                ");
                
                $updateStmt->execute([
                    'id' => $installmentId,
                    'paid_date' => $paymentDate,
                    'payment_method' => $paymentMethod,
                    'notes' => $notes,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                // Registrar log de pagamento
                $logStmt = $this->db->prepare("
                    INSERT INTO payment_logs (installment_id, student_id, amount, discount, final_amount, payment_method, paid_date, processed_by, processed_at)
                    VALUES (:installment_id, :student_id, :amount, :discount, :final_amount, :payment_method, :paid_date, :processed_by, :processed_at)
                ");
                
                $finalAmount = floatval($installment['amount']) - floatval($installment['discount'] ?? 0);
                
                $logStmt->execute([
                    'installment_id' => $installmentId,
                    'student_id' => $installment['student_id'],
                    'amount' => $installment['amount'],
                    'discount' => $installment['discount'] ?? 0,
                    'final_amount' => $finalAmount,
                    'payment_method' => $paymentMethod,
                    'paid_date' => $paymentDate,
                    'processed_by' => $data['user'] ?? 'sistema',
                    'processed_at' => date('Y-m-d H:i:s')
                ]);
                
                $this->db->commit();
                
                // Buscar mensalidade atualizada
                $paidStmt = $this->db->prepare("
                    SELECT i.*, s.name as student_name,
                           (i.amount - COALESCE(i.discount, 0)) as final_amount
                    FROM installments i 
                    LEFT JOIN students s ON i.student_id = s.id 
                    WHERE i.id = :id
                ");
                $paidStmt->execute(['id' => $installmentId]);
                $paidInstallment = $paidStmt->fetch();
                
                // Formatar valores
                $paidInstallment['amount'] = number_format($paidInstallment['amount'], 2, '.', '');
                $paidInstallment['discount'] = number_format($paidInstallment['discount'] ?? 0, 2, '.', '');
                $paidInstallment['final_amount'] = number_format($paidInstallment['final_amount'], 2, '.', '');
                $paidInstallment['paid_date'] = date('d/m/Y H:i', strtotime($paidInstallment['paid_date']));
                
                Logger::info("Pagamento processado", [
                    'installment_id' => $installmentId,
                    'student_name' => $installment['student_name'],
                    'amount' => $finalAmount,
                    'payment_method' => $paymentMethod
                ]);
                
                ApiResponse::success($paidInstallment, "Pagamento processado com sucesso");
                
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            Logger::error("Erro ao processar pagamento: " . $e->getMessage());
            ApiResponse::error("Erro ao processar pagamento");
        }
    }
    /**
     * PARTE 4/4 - DASHBOARD, RELATÓRIOS E FINALIZAÇÃO
     * Métodos para dashboard, relatórios, exportação e inicialização
     */
    
    /**
     * Obter dados do dashboard com estatísticas completas
     */
    private function getDashboardData($data) {
        try {
            $month = $data['month'] ?? date('m');
            $year = $data['year'] ?? date('Y');
            
            // Estatísticas gerais
            $generalStats = $this->db->prepare("
                SELECT 
                    COUNT(DISTINCT s.id) as total_students,
                    COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) as active_students,
                    COUNT(i.id) as total_installments,
                    COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as paid_installments,
                    COUNT(CASE WHEN i.status = 'pending' THEN 1 END) as pending_installments,
                    COUNT(CASE WHEN i.status = 'pending' AND i.due_date < CURDATE() THEN 1 END) as overdue_installments,
                    COALESCE(SUM(CASE WHEN i.status = 'paid' THEN (i.amount - COALESCE(i.discount, 0)) END), 0) as total_received,
                    COALESCE(SUM(CASE WHEN i.status = 'pending' THEN (i.amount - COALESCE(i.discount, 0)) END), 0) as total_pending,
                    COALESCE(SUM(CASE WHEN i.status = 'pending' AND i.due_date < CURDATE() THEN (i.amount - COALESCE(i.discount, 0)) END), 0) as total_overdue
                FROM students s
                LEFT JOIN installments i ON s.id = i.student_id
            ");
            $generalStats->execute();
            $stats = $generalStats->fetch();
            
            // Estatísticas do mês atual
            $monthlyStats = $this->db->prepare("
                SELECT 
                    COUNT(i.id) as monthly_installments,
                    COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as monthly_paid,
                    COUNT(CASE WHEN i.status = 'pending' THEN 1 END) as monthly_pending,
                    COUNT(CASE WHEN i.status = 'pending' AND i.due_date < CURDATE() THEN 1 END) as monthly_overdue,
                    COALESCE(SUM(CASE WHEN i.status = 'paid' THEN (i.amount - COALESCE(i.discount, 0)) END), 0) as monthly_received,
                    COALESCE(SUM(CASE WHEN i.status = 'pending' THEN (i.amount - COALESCE(i.discount, 0)) END), 0) as monthly_pending_amount
                FROM installments i
                WHERE MONTH(i.due_date) = :month AND YEAR(i.due_date) = :year
            ");
            $monthlyStats->execute(['month' => $month, 'year' => $year]);
            $monthlyData = $monthlyStats->fetch();
            
            // Receitas dos últimos 6 meses
            $revenueChart = $this->db->prepare("
                SELECT 
                    DATE_FORMAT(i.due_date, '%Y-%m') as month,
                    COALESCE(SUM(CASE WHEN i.status = 'paid' THEN (i.amount - COALESCE(i.discount, 0)) END), 0) as received,
                    COALESCE(SUM(CASE WHEN i.status = 'pending' THEN (i.amount - COALESCE(i.discount, 0)) END), 0) as pending
                FROM installments i
                WHERE i.due_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(i.due_date, '%Y-%m')
                ORDER BY month ASC
            ");
            $revenueChart->execute();
            $revenueData = $revenueChart->fetchAll();
            
            // Top cursos por receita
            $topCourses = $this->db->prepare("
                SELECT 
                    s.course,
                    COUNT(DISTINCT s.id) as students_count,
                    COUNT(i.id) as total_installments,
                    COALESCE(SUM(CASE WHEN i.status = 'paid' THEN (i.amount - COALESCE(i.discount, 0)) END), 0) as total_revenue
                FROM students s
                LEFT JOIN installments i ON s.id = i.student_id
                WHERE s.course IS NOT NULL AND s.course != ''
                GROUP BY s.course
                ORDER BY total_revenue DESC
                LIMIT 10
            ");
            $topCourses->execute();
            $coursesData = $topCourses->fetchAll();
            
            // Mensalidades vencendo nos próximos 7 dias
            $upcomingDue = $this->db->prepare("
                SELECT 
                    i.id, i.amount, i.due_date, i.discount,
                    s.name as student_name, s.email as student_email,
                    s.course as student_course,
                    (i.amount - COALESCE(i.discount, 0)) as final_amount,
                    DATEDIFF(i.due_date, CURDATE()) as days_until_due
                FROM installments i
                LEFT JOIN students s ON i.student_id = s.id
                WHERE i.status = 'pending' 
                AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                ORDER BY i.due_date ASC
                LIMIT 20
            ");
            $upcomingDue->execute();
            $upcomingInstallments = $upcomingDue->fetchAll();
            
            // Mensalidades em atraso
            $overdueInstallments = $this->db->prepare("
                SELECT 
                    i.id, i.amount, i.due_date, i.discount,
                    s.name as student_name, s.email as student_email,
                    s.course as student_course,
                    (i.amount - COALESCE(i.discount, 0)) as final_amount,
                    DATEDIFF(CURDATE(), i.due_date) as days_overdue
                FROM installments i
                LEFT JOIN students s ON i.student_id = s.id
                WHERE i.status = 'pending' AND i.due_date < CURDATE()
                ORDER BY i.due_date ASC
                LIMIT 20
            ");
            $overdueInstallments->execute();
            $overdueData = $overdueInstallments->fetchAll();
            
            // Métodos de pagamento mais utilizados
            $paymentMethods = $this->db->prepare("
                SELECT 
                    COALESCE(payment_method, 'Não informado') as method,
                    COUNT(*) as count,
                    SUM(amount - COALESCE(discount, 0)) as total_amount
                FROM installments 
                WHERE status = 'paid'
                GROUP BY payment_method
                ORDER BY count DESC
            ");
            $paymentMethods->execute();
            $paymentMethodsData = $paymentMethods->fetchAll();
            
            // Formatar dados monetários
            $formatMoney = function($value) {
                return number_format(floatval($value), 2, '.', '');
            };
            
            foreach (['total_received', 'total_pending', 'total_overdue'] as $field) {
                $stats[$field] = $formatMoney($stats[$field]);
            }
            
            foreach (['monthly_received', 'monthly_pending_amount'] as $field) {
                $monthlyData[$field] = $formatMoney($monthlyData[$field]);
            }
            
            foreach ($revenueData as &$item) {
                $item['received'] = $formatMoney($item['received']);
                $item['pending'] = $formatMoney($item['pending']);
            }
            
            foreach ($coursesData as &$course) {
                $course['total_revenue'] = $formatMoney($course['total_revenue']);
            }
            
            foreach ($upcomingInstallments as &$installment) {
                $installment['amount'] = $formatMoney($installment['amount']);
                $installment['discount'] = $formatMoney($installment['discount'] ?? 0);
                $installment['final_amount'] = $formatMoney($installment['final_amount']);
                $installment['due_date'] = date('d/m/Y', strtotime($installment['due_date']));
            }
            
            foreach ($overdueData as &$installment) {
                $installment['amount'] = $formatMoney($installment['amount']);
                $installment['discount'] = $formatMoney($installment['discount'] ?? 0);
                $installment['final_amount'] = $formatMoney($installment['final_amount']);
                $installment['due_date'] = date('d/m/Y', strtotime($installment['due_date']));
            }
            
            foreach ($paymentMethodsData as &$method) {
                $method['total_amount'] = $formatMoney($method['total_amount']);
            }
            
            $dashboardData = [
                'general_statistics' => $stats,
                'monthly_statistics' => $monthlyData,
                'revenue_chart' => $revenueData,
                'top_courses' => $coursesData,
                'upcoming_due' => $upcomingInstallments,
                'overdue_installments' => $overdueData,
                'payment_methods' => $paymentMethodsData,
                'generated_at' => date('d/m/Y H:i:s')
            ];
            
            Logger::info("Dashboard acessado", ['month' => $month, 'year' => $year]);
            ApiResponse::success($dashboardData, "Dados do dashboard carregados com sucesso");
            
        } catch (Exception $e) {
            Logger::error("Erro ao carregar dashboard: " . $e->getMessage());
            ApiResponse::error("Erro ao carregar dados do dashboard");
        }
    }
    
    /**
     * Gerar relatórios personalizados
     */
    private function getReports($data) {
        try {
            $reportType = $data['type'] ?? 'general';
            $startDate = $data['start_date'] ?? date('Y-m-01');
            $endDate = $data['end_date'] ?? date('Y-m-t');
            $studentId = $data['student_id'] ?? null;
            $course = $data['course'] ?? null;
            
            switch ($reportType) {
                case 'financial':
                    $report = $this->generateFinancialReport($startDate, $endDate, $course);
                    break;
                    
                case 'student':
                    $report = $this->generateStudentReport($studentId, $startDate, $endDate);
                    break;
                    
                case 'overdue':
                    $report = $this->generateOverdueReport($startDate, $endDate);
                    break;
                    
                case 'course':
                    $report = $this->generateCourseReport($startDate, $endDate);
                    break;
                    
                default:
                    $report = $this->generateGeneralReport($startDate, $endDate);
                    break;
            }
            
            Logger::info("Relatório gerado", [
                'type' => $reportType,
                'period' => "$startDate a $endDate"
            ]);
            
            ApiResponse::success($report, "Relatório gerado com sucesso");
            
        } catch (Exception $e) {
            Logger::error("Erro ao gerar relatório: " . $e->getMessage());
            ApiResponse::error("Erro ao gerar relatório");
        }
    }
    
    /**
     * Relatório financeiro detalhado
     */
    private function generateFinancialReport($startDate, $endDate, $course = null) {
        $whereClause = "WHERE i.due_date BETWEEN :start_date AND :end_date";
        $params = ['start_date' => $startDate, 'end_date' => $endDate];
        
        if ($course) {
            $whereClause .= " AND s.course LIKE :course";
            $params['course'] = "%$course%";
        }
        
        $sql = "
            SELECT 
                DATE_FORMAT(i.due_date, '%Y-%m') as month,
                COUNT(i.id) as total_installments,
                COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as paid_count,
                COUNT(CASE WHEN i.status = 'pending' THEN 1 END) as pending_count,
                SUM(i.amount) as gross_amount,
                SUM(COALESCE(i.discount, 0)) as total_discounts,
                SUM(i.amount - COALESCE(i.discount, 0)) as net_amount,
                SUM(CASE WHEN i.status = 'paid' THEN (i.amount - COALESCE(i.discount, 0)) END) as received_amount,
                SUM(CASE WHEN i.status = 'pending' THEN (i.amount - COALESCE(i.discount, 0)) END) as pending_amount
            FROM installments i
            LEFT JOIN students s ON i.student_id = s.id
            $whereClause
            GROUP BY DATE_FORMAT(i.due_date, '%Y-%m')
            ORDER BY month DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return [
            'type' => 'financial',
            'period' => ['start' => $startDate, 'end' => $endDate],
            'course_filter' => $course,
            'data' => $stmt->fetchAll()
        ];
    }
    
    /**
     * Relatório de estudante específico
     */
    private function generateStudentReport($studentId, $startDate, $endDate) {
        if (!$studentId) {
            throw new Exception("ID do estudante é obrigatório para relatório individual");
        }
        
        // Dados do estudante
        $studentStmt = $this->db->prepare("SELECT * FROM students WHERE id = :id");
        $studentStmt->execute(['id' => $studentId]);
        $student = $studentStmt->fetch();
        
        if (!$student) {
            throw new Exception("Estudante não encontrado");
        }
        
        // Mensalidades do período
        $installmentsStmt = $this->db->prepare("
            SELECT *,
                   (amount - COALESCE(discount, 0)) as final_amount,
                   CASE 
                       WHEN status = 'pending' AND due_date < CURDATE() THEN DATEDIFF(CURDATE(), due_date)
                       ELSE 0
                   END as days_overdue
            FROM installments 
            WHERE student_id = :student_id 
            AND due_date BETWEEN :start_date AND :end_date
            ORDER BY due_date ASC
        ");
        
        $installmentsStmt->execute([
            'student_id' => $studentId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        return [
            'type' => 'student',
            'period' => ['start' => $startDate, 'end' => $endDate],
            'student' => $student,
            'installments' => $installmentsStmt->fetchAll()
        ];
    }
    
    /**
     * Relatório de mensalidades em atraso
     */
    private function generateOverdueReport($startDate, $endDate) {
        $sql = "
            SELECT 
                i.*,
                s.name as student_name,
                s.email as student_email,
                s.phone as student_phone,
                s.course as student_course,
                (i.amount - COALESCE(i.discount, 0)) as final_amount,
                DATEDIFF(CURDATE(), i.due_date) as days_overdue
            FROM installments i
            LEFT JOIN students s ON i.student_id = s.id
            WHERE i.status = 'pending' 
            AND i.due_date < CURDATE()
            AND i.due_date BETWEEN :start_date AND :end_date
            ORDER BY i.due_date ASC, days_overdue DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        
        return [
            'type' => 'overdue',
            'period' => ['start' => $startDate, 'end' => $endDate],
            'overdue_installments' => $stmt->fetchAll()
        ];
    }
    
    /**
     * Relatório por curso
     */
    private function generateCourseReport($startDate, $endDate) {
        $sql = "
            SELECT 
                s.course,
                COUNT(DISTINCT s.id) as total_students,
                COUNT(i.id) as total_installments,
                COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as paid_installments,
                COUNT(CASE WHEN i.status = 'pending' THEN 1 END) as pending_installments,
                SUM(i.amount) as gross_revenue,
                SUM(COALESCE(i.discount, 0)) as total_discounts,
                SUM(CASE WHEN i.status = 'paid' THEN (i.amount - COALESCE(i.discount, 0)) END) as received_revenue,
                SUM(CASE WHEN i.status = 'pending' THEN (i.amount - COALESCE(i.discount, 0)) END) as pending_revenue
            FROM students s
            LEFT JOIN installments i ON s.id = i.student_id 
                AND i.due_date BETWEEN :start_date AND :end_date
            WHERE s.course IS NOT NULL AND s.course != ''
            GROUP BY s.course
            ORDER BY received_revenue DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        
        return [
            'type' => 'course',
            'period' => ['start' => $startDate, 'end' => $endDate],
            'courses' => $stmt->fetchAll()
        ];
    }
    
    /**
     * Relatório geral
     */
    private function generateGeneralReport($startDate, $endDate) {
        $financialData = $this->generateFinancialReport($startDate, $endDate);
        $overdueData = $this->generateOverdueReport($startDate, $endDate);
        $courseData = $this->generateCourseReport($startDate, $endDate);
        
        return [
            'type' => 'general',
            'period' => ['start' => $startDate, 'end' => $endDate],
            'financial_summary' => $financialData['data'],
            'overdue_summary' => $overdueData['overdue_installments'],
            'courses_summary' => $courseData['courses']
        ];
    }
    
    /**
     * Exportar dados para CSV/Excel
     */
    private function exportData($data) {
        try {
            $exportType = $data['export_type'] ?? 'students';
            $format = $data['format'] ?? 'csv';
            $filters = $data['filters'] ?? [];
            
            switch ($exportType) {
                case 'students':
                    $exportData = $this->exportStudents($filters);
                    $filename = "estudantes_" . date('Y-m-d_H-i-s');
                    break;
                    
                case 'installments':
                    $exportData = $this->exportInstallments($filters);
                    $filename = "mensalidades_" . date('Y-m-d_H-i-s');
                    break;
                    
                case 'financial':
                    $exportData = $this->exportFinancial($filters);
                    $filename = "financeiro_" . date('Y-m-d_H-i-s');
                    break;
                    
                default:
                    ApiResponse::badRequest("Tipo de exportação inválido");
            }
            
            if ($format === 'csv') {
                $csvData = $this->generateCSV($exportData);
                $response = [
                    'filename' => $filename . '.csv',
                    'content' => base64_encode($csvData),
                    'mime_type' => 'text/csv'
                ];
            } else {
                ApiResponse::badRequest("Formato não suportado");
            }
            
            Logger::info("Exportação realizada", [
                'type' => $exportType,
                'format' => $format,
                'records' => count($exportData)
            ]);
            
            ApiResponse::success($response, "Exportação realizada com sucesso");
            
        } catch (Exception $e) {
            Logger::error("Erro na exportação: " . $e->getMessage());
            ApiResponse::error("Erro ao exportar dados");
        }
    }
    
    /**
     * Exportar dados de estudantes
     */
    private function exportStudents($filters) {
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['course'])) {
            $whereConditions[] = "course LIKE :course";
            $params['course'] = "%{$filters['course']}%";
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT name, email, cpf, phone, course, status, created_at FROM students $whereClause ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Exportar dados de mensalidades
     */
    private function exportInstallments($filters) {
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "i.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $whereConditions[] = "i.due_date BETWEEN :start_date AND :end_date";
            $params['start_date'] = $filters['start_date'];
            $params['end_date'] = $filters['end_date'];
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "
            SELECT 
                s.name as estudante,
                s.course as curso,
                i.amount as valor,
                i.discount as desconto,
                (i.amount - COALESCE(i.discount, 0)) as valor_final,
                i.due_date as vencimento,
                i.paid_date as data_pagamento,
                i.status,
                i.payment_method as forma_pagamento
            FROM installments i
            LEFT JOIN students s ON i.student_id = s.id
            $whereClause
            ORDER BY i.due_date DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Exportar relatório financeiro
     */
    private function exportFinancial($filters) {
        $startDate = $filters['start_date'] ?? date('Y-m-01');
        $endDate = $filters['end_date'] ?? date('Y-m-t');
        
        return $this->generateFinancialReport($startDate, $endDate)['data'];
    }
    
    /**
     * Gerar conteúdo CSV
     */
    private function generateCSV($data) {
        if (empty($data)) {
            return "Nenhum dado encontrado\n";
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Cabeçalho
        fputcsv($output, array_keys($data[0]), ';');
        
        // Dados
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}

// Inicialização e execução da API
try {
    $api = new MensalidadeAPI();
    $api->handleRequest();
} catch (Exception $e) {
    Logger::error("Erro fatal na inicialização: " . $e->getMessage());
    ApiResponse::error("Erro interno do servidor");
}

/**
 * TABELAS SQL NECESSÁRIAS PARA O SISTEMA
 * Execute estes comandos no seu banco de dados MySQL
 */

/*
-- Tabela de estudantes
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    cpf VARCHAR(11) UNIQUE NOT NULL,
    phone VARCHAR(20),
    course VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_cpf (cpf),
    INDEX idx_status (status)
);

-- Tabela de mensalidades
CREATE TABLE IF NOT EXISTS installments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    discount_reason TEXT,
    due_date DATE NOT NULL,
    paid_date TIMESTAMP NULL,
    status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
    payment_method VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
);

-- Tabela de logs de desconto
CREATE TABLE IF NOT EXISTS discount_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    installment_id INT NOT NULL,
    original_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    final_amount DECIMAL(10,2) NOT NULL,
    reason TEXT,
    applied_by VARCHAR(255),
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (installment_id) REFERENCES installments(id) ON DELETE CASCADE,
    INDEX idx_installment_id (installment_id)
);

-- Tabela de logs de pagamento
CREATE TABLE IF NOT EXISTS payment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    installment_id INT NOT NULL,
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    final_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    paid_date TIMESTAMP NOT NULL,
    processed_by VARCHAR(255),
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (installment_id) REFERENCES installments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_installment_id (installment_id),
    INDEX idx_student_id (student_id),
    INDEX idx_paid_date (paid_date)
);
*/

?>