<?php
/**
 * API.PHP - SISTEMA DE MENSALIDADES COM DESCONTO
 * PARTE 1/4 - CONFIGURAÇÃO E ESTRUTURA BASE
 * Versão: 4.0 - SISTEMA COMPLETO COM DESCONTO
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

// Incluir bootstrap do sistema
require_once 'bootstrap.php';

// Configuração do banco de dados
class DatabaseConfig {
    const HOST = 'localhost';
    const DB_NAME = 'bankdb';
    const USERNAME = 'bankuser';
    const PASSWORD = 'lKVX4Ew0u7I89hAUuDCm';
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
    
    /**
     * NOVO: Validar dados de desconto
     */
    public static function validateDiscount($discountData, $installmentValue) {
        $errors = [];
        
        if (empty($discountData['type']) || !in_array($discountData['type'], ['FIXED', 'PERCENTAGE'])) {
            $errors[] = 'Tipo de desconto inválido';
        }
        
        $discountValue = floatval($discountData['value'] ?? 0);
        if ($discountValue <= 0) {
            $errors[] = 'Valor do desconto deve ser maior que zero';
        }
        
        if ($discountData['type'] === 'FIXED' && $discountValue >= $installmentValue) {
            $errors[] = 'Desconto fixo não pode ser maior ou igual ao valor da parcela';
        }
        
        if ($discountData['type'] === 'PERCENTAGE' && $discountValue >= 100) {
            $errors[] = 'Desconto percentual não pode ser maior ou igual a 100%';
        }
        
        $validDeadlines = ['DUE_DATE', 'BEFORE_DUE_DATE', '3_DAYS_BEFORE', '5_DAYS_BEFORE'];
        if (!in_array($discountData['deadline'], $validDeadlines)) {
            $errors[] = 'Prazo de desconto inválido';
        }
        
        return $errors;
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

/**
 * NOVA CLASSE: Gerenciador de Desconto
 */
class DiscountManager {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
        $this->ensureDiscountTables();
    }
    
    /**
     * Garantir que tabelas de desconto existem
     */
    private function ensureDiscountTables() {
        try {
            // Criar tabela de descontos se não existir
            $sql = "CREATE TABLE IF NOT EXISTS installment_discounts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                installment_id VARCHAR(100) NOT NULL,
                discount_type ENUM('FIXED', 'PERCENTAGE') NOT NULL,
                discount_value DECIMAL(10,2) NOT NULL,
                discount_deadline ENUM('DUE_DATE', 'BEFORE_DUE_DATE', '3_DAYS_BEFORE', '5_DAYS_BEFORE') NOT NULL,
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_installment_id (installment_id),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
            
            // Adicionar colunas de desconto na tabela installments se não existirem
            $columns = [
                'has_discount' => "ALTER TABLE installments ADD COLUMN has_discount BOOLEAN DEFAULT 0",
                'discount_type' => "ALTER TABLE installments ADD COLUMN discount_type ENUM('FIXED', 'PERCENTAGE') NULL",
                'discount_value' => "ALTER TABLE installments ADD COLUMN discount_value DECIMAL(10,2) NULL"
            ];
            
            foreach ($columns as $column => $sql) {
                try {
                    $check = $this->db->query("SHOW COLUMNS FROM installments LIKE '{$column}'");
                    if ($check->rowCount() == 0) {
                        $this->db->exec($sql);
                    }
                } catch (Exception $e) {
                    // Coluna já existe ou erro na estrutura
                }
            }
            
        } catch (Exception $e) {
            Logger::error("Erro ao criar estrutura de desconto: " . $e->getMessage());
        }
    }
    
    /**
     * Preparar dados do desconto para API do ASAAS
     */
    public function prepareAsaasDiscount($discountData, $installmentValue) {
        $discount = [];
        
        if ($discountData['type'] === 'FIXED') {
            $discount['value'] = floatval($discountData['value']);
        } else {
            // Para percentual, calcular valor baseado na parcela
            $discount['value'] = ($installmentValue * floatval($discountData['value'])) / 100;
        }
        
        // Configurar prazo do desconto
        switch ($discountData['deadline']) {
            case 'DUE_DATE':
                $discount['dueDateLimitDays'] = 0;
                break;
            case 'BEFORE_DUE_DATE':
                $discount['dueDateLimitDays'] = -1;
                break;
            case '3_DAYS_BEFORE':
                $discount['dueDateLimitDays'] = -3;
                break;
            case '5_DAYS_BEFORE':
                $discount['dueDateLimitDays'] = -5;
                break;
            default:
                $discount['dueDateLimitDays'] = 0;
        }
        
        return $discount;
    }
    
    /**
     * Salvar informações do desconto no banco
     */
    public function saveDiscountInfo($installmentId, $discountData) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO installment_discounts (
                    installment_id, discount_type, discount_value, 
                    discount_deadline, is_active, created_at
                ) VALUES (?, ?, ?, ?, 1, NOW())
            ");
            
            return $stmt->execute([
                $installmentId,
                $discountData['type'],
                $discountData['value'],
                $discountData['deadline']
            ]);
            
        } catch (PDOException $e) {
            Logger::error("Erro ao salvar desconto: " . $e->getMessage());
            return false;
        }
    }
}

// Classe principal da API - ATUALIZADA COM DESCONTO
class MensalidadeAPI {
    private $db;
    private $discountManager;
    private $allowedActions = [
        'get_students',
        'get_student',
        'create_student',
        'update_student',
        'delete_student',
        'get_installments',
        'get_student_installments',
        'create_installment',
        'create_installment_with_discount', // NOVO: Ação principal com desconto
        'update_installment',
        'delete_installment',
        'pay_installment',
        'get_dashboard_data',
        'export_data',
        'get_reports',
        'get_discount_report', // NOVO: Relatório de descontos
        'generate_payment_book_with_discount', // NOVO: Carnê com desconto
        'get_installment_with_discount', // NOVO: Buscar mensalidade com desconto
        'test_connection'
    ];
    
    public function __construct() {
        try {
            $this->db = DatabaseConfig::getInstance()->getConnection();
            $this->discountManager = new DiscountManager();
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
            
            // Obter ação - verificar tanto POST quanto GET
            $action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? null;
            
            if (!$action) {
                ApiResponse::badRequest('Ação não especificada');
            }
            
            if (!in_array($action, $this->allowedActions)) {
                Logger::warning("Ação não reconhecida: {$action}");
                ApiResponse::badRequest("Ação não reconhecida: {$action}");
            }
            
            // Executar ação
            $data = array_merge($_POST, $input ?? [], $_GET);
            $this->executeAction($action, $data);
            
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
                
            // NOVO: Ação principal para mensalidade com desconto
            case 'create_installment_with_discount':
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
                
            // NOVOS: Métodos de desconto
            case 'get_discount_report':
                $this->getDiscountReport($data);
                break;
            case 'generate_payment_book_with_discount':
                $this->generatePaymentBookWithDiscount($data);
                break;
            case 'get_installment_with_discount':
                $this->getInstallmentWithDiscount($data);
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
                
            // Teste de conexão
            case 'test_connection':
                $this->testConnection($data);
                break;
                
            default:
                ApiResponse::badRequest("Ação não implementada: {$action}");
        }
    }

/**
     * PARTE 2/4 - MÉTODOS DE ESTUDANTES E MENSALIDADES BÁSICAS
     * Todos os métodos CRUD para estudantes e mensalidades tradicionais
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
     * Criar mensalidade tradicional (sem desconto)
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
     * Teste de conexão
     */
    private function testConnection($data) {
        try {
            // Testar conexão com banco
            $this->db->query("SELECT 1");
            
            // Testar ASAAS se disponível
            $asaasStatus = 'não testado';
            try {
                if (class_exists('AsaasConfig')) {
                    $asaas = AsaasConfig::getInstance();
                    $response = $asaas->listAccounts(1, 0);
                    $asaasStatus = 'OK - ' . ($response['totalCount'] ?? 0) . ' contas';
                }
            } catch (Exception $e) {
                $asaasStatus = 'Erro: ' . $e->getMessage();
            }
            
            $testResults = [
                'database' => 'OK',
                'asaas' => $asaasStatus,
                'timestamp' => date('Y-m-d H:i:s'),
                'environment' => defined('ASAAS_ENVIRONMENT') ? ASAAS_ENVIRONMENT : 'undefined',
                'php_version' => PHP_VERSION
            ];
            
            Logger::info("Teste de conexão executado", $testResults);
            ApiResponse::success($testResults, "Conexões testadas com sucesso");
            
        } catch (Exception $e) {
            Logger::error("Erro no teste de conexão: " . $e->getMessage());
            ApiResponse::error("Erro ao testar conexões: " . $e->getMessage());
        }
    }
    /**
     * PARTE 3/4 - SISTEMA DE MENSALIDADES COM DESCONTO
     * Método principal e funcionalidades avançadas de desconto
     */
    
    /**
     * ===== MÉTODO PRINCIPAL: CRIAR MENSALIDADE COM DESCONTO =====
     */
/**
 * FUNÇÃO CORRIGIDA - createInstallmentWithDiscount
 * Substitua a função existente por esta versão
 */
private function createInstallmentWithDiscount($data) {
    try {
        Logger::info("Iniciando criação de mensalidade com desconto", ['data_keys' => array_keys($data)]);
        Logger::info("DEBUG - Dados recebidos:", [
            'action' => 'create_installment_with_discount',
            'payment_data' => $data['payment'] ?? 'MISSING',
            'installment_data' => $data['installment'] ?? 'MISSING', 
            'discount_data' => $data['discount'] ?? 'MISSING',
            'usuario_logado' => $_SESSION['usuario_email'] ?? 'NO_SESSION'
        ]);
        // Extrair dados dos formulários
        $paymentData = $data['payment'] ?? [];
        $installmentData = $data['installment'] ?? [];
        $splitsData = $data['splits'] ?? [];
        $discountData = $data['discount'] ?? [];
        
        // Validar dados básicos do pagamento
        $requiredPaymentFields = ['customer', 'billingType', 'description', 'dueDate'];
        $missing = Validator::validateRequired($paymentData, $requiredPaymentFields);
        
        if (!empty($missing)) {
            ApiResponse::badRequest("Campos obrigatórios do pagamento faltando", $missing);
            return;
        }
        
        // Validar dados do parcelamento
        $installmentCount = (int)($installmentData['installmentCount'] ?? 0);
        $installmentValue = floatval($installmentData['installmentValue'] ?? 0);
        
        if ($installmentCount < 2 || $installmentCount > 24) {
            ApiResponse::badRequest('Número de parcelas deve ser entre 2 e 24');
            return;
        }
        
        if ($installmentValue <= 0) {
            ApiResponse::badRequest('Valor da parcela deve ser maior que zero');
            return;
        }
        
        // Validar data de vencimento
        $dueDate = $paymentData['dueDate'];
        if (strtotime($dueDate) < strtotime(date('Y-m-d'))) {
            ApiResponse::badRequest('Data de vencimento não pode ser anterior a hoje');
            return;
        }
        
        // Processar desconto se habilitado
        $processedDiscountData = null;
        $discountForAsaas = null;
        
        if (!empty($discountData['enabled']) && $discountData['enabled'] === 'true') {
            Logger::info("Processando desconto", ['discount_data' => $discountData]);
            
            $processedDiscountData = [
                'enabled' => true,
                'type' => $discountData['type'] ?? 'FIXED',
                'value' => floatval($discountData['value'] ?? 0),
                'deadline' => $discountData['deadline'] ?? 'DUE_DATE'
            ];
            
            // Validar dados do desconto
            if (class_exists('Validator') && method_exists('Validator', 'validateDiscount')) {
                $discountErrors = Validator::validateDiscount($processedDiscountData, $installmentValue);
                if (!empty($discountErrors)) {
                    ApiResponse::badRequest("Dados de desconto inválidos", $discountErrors);
                    return;
                }
            } else {
                // Validação manual se o método não existir
                if ($processedDiscountData['value'] <= 0) {
                    ApiResponse::badRequest("Valor do desconto deve ser maior que zero");
                    return;
                }
                
                if ($processedDiscountData['type'] === 'FIXED' && $processedDiscountData['value'] >= $installmentValue) {
                    ApiResponse::badRequest("Desconto fixo não pode ser maior ou igual ao valor da parcela");
                    return;
                }
                
                if ($processedDiscountData['type'] === 'PERCENTAGE' && $processedDiscountData['value'] >= 100) {
                    ApiResponse::badRequest("Desconto percentual não pode ser maior ou igual a 100%");
                    return;
                }
            }
            
            // Preparar desconto para ASAAS
            if (isset($this->discountManager) && method_exists($this->discountManager, 'prepareAsaasDiscount')) {
                $discountForAsaas = $this->discountManager->prepareAsaasDiscount($processedDiscountData, $installmentValue);
                $paymentData['discount'] = $discountForAsaas;
            } else {
                // Preparar desconto manualmente se o método não existir
                $discount = [];
                if ($processedDiscountData['type'] === 'FIXED') {
                    $discount['value'] = floatval($processedDiscountData['value']);
                } else {
                    $discount['value'] = ($installmentValue * floatval($processedDiscountData['value'])) / 100;
                }
                
                // Configurar prazo do desconto
                switch ($processedDiscountData['deadline']) {
                    case 'DUE_DATE':
                        $discount['dueDateLimitDays'] = 0;
                        break;
                    case 'BEFORE_DUE_DATE':
                        $discount['dueDateLimitDays'] = -1;
                        break;
                    case '3_DAYS_BEFORE':
                        $discount['dueDateLimitDays'] = -3;
                        break;
                    case '5_DAYS_BEFORE':
                        $discount['dueDateLimitDays'] = -5;
                        break;
                    default:
                        $discount['dueDateLimitDays'] = 0;
                }
                
                $paymentData['discount'] = $discount;
            }
            
            Logger::info("Desconto preparado para ASAAS", ['discount_asaas' => $paymentData['discount']]);
        }
        
        // Processar splits
        $processedSplits = [];
        $totalPercentage = 0;
        $totalFixedValue = 0;
        
        foreach ($splitsData as $split) {
            if (!empty($split['walletId'])) {
                $splitData = ['walletId' => $split['walletId']];
                
                if (!empty($split['percentualValue']) && floatval($split['percentualValue']) > 0) {
                    $percentage = floatval($split['percentualValue']);
                    if ($percentage > 100) {
                        ApiResponse::badRequest('Percentual de split não pode ser maior que 100%');
                        return;
                    }
                    $splitData['percentualValue'] = $percentage;
                    $totalPercentage += $percentage;
                }
                
                if (!empty($split['fixedValue']) && floatval($split['fixedValue']) > 0) {
                    $fixedValue = floatval($split['fixedValue']);
                    if ($fixedValue >= $installmentValue) {
                        ApiResponse::badRequest('Valor fixo do split não pode ser maior ou igual ao valor da parcela');
                        return;
                    }
                    $splitData['fixedValue'] = $fixedValue;
                    $totalFixedValue += $fixedValue;
                }
                
                $processedSplits[] = $splitData;
            }
        }
        
        // Validar splits
        if (!empty($processedSplits)) {
            if ($totalPercentage > 100) {
                ApiResponse::badRequest('A soma dos percentuais não pode exceder 100%');
                return;
            }
            
            if ($totalFixedValue >= $installmentValue) {
                ApiResponse::badRequest('A soma dos valores fixos não pode ser maior ou igual ao valor da parcela');
                return;
            }
        }
        
        Logger::info("Dados validados, criando no ASAAS", [
            'installment_count' => $installmentCount,
            'installment_value' => $installmentValue,
            'has_discount' => !empty($processedDiscountData),
            'splits_count' => count($processedSplits)
        ]);
        
        // Criar mensalidade via API ASAAS
        try {
            // Usar configuração dinâmica existente ou fallback
            $asaas = null;
            if (class_exists('DynamicAsaasConfig')) {
                try {
                    $dynamicConfig = new DynamicAsaasConfig();
                    $asaas = $dynamicConfig->getInstance();
                } catch (Exception $e) {
                    Logger::warning("DynamicAsaasConfig falhou, usando AsaasConfig: " . $e->getMessage());
                }
            }
            
            if (!$asaas) {
                if (class_exists('AsaasConfig')) {
                    $asaas = AsaasConfig::getInstance();
                } else {
                    throw new Exception('Nenhuma configuração ASAAS disponível');
                }
            }
            
            // Verificar se o método existe
            if (!method_exists($asaas, 'createInstallmentPaymentWithSplit')) {
                throw new Exception('Método createInstallmentPaymentWithSplit não encontrado na classe ASAAS');
            }
            
            // Criar parcelamento via ASAAS
            $result = $asaas->createInstallmentPaymentWithSplit($paymentData, $processedSplits, $installmentData);
            
            Logger::info("Mensalidade criada no ASAAS", ['installment_id' => $result['installment'] ?? 'N/A']);
            
        } catch (Exception $e) {
            Logger::error("Erro na API ASAAS: " . $e->getMessage());
            ApiResponse::error('Erro ao criar mensalidade: ' . $e->getMessage());
            return;
        }
        
        // Salvar no banco de dados local
        if (property_exists($this, 'db') && $this->db) {
            $this->db->beginTransaction();
        }
        
        try {
            // Salvar informações do parcelamento principal
            $installmentRecord = [
                'installment_id' => $result['installment'] ?? uniqid('inst_'),
                'polo_id' => $_SESSION['polo_id'] ?? null,
                'customer_id' => $result['customer'] ?? $paymentData['customer'],
                'installment_count' => $installmentCount,
                'installment_value' => $installmentValue,
                'total_value' => $installmentCount * $installmentValue,
                'first_due_date' => $paymentData['dueDate'],
                'billing_type' => $paymentData['billingType'],
                'description' => $paymentData['description'],
                'has_splits' => !empty($processedSplits),
                'splits_count' => count($processedSplits),
                'created_by' => $_SESSION['usuario_id'] ?? null,
                'first_payment_id' => $result['id'] ?? uniqid('pay_'),
                'has_discount' => !empty($processedDiscountData),
                'discount_type' => isset($processedDiscountData['type']) ? $processedDiscountData['type'] : null,
                'discount_value' => isset($processedDiscountData['value']) ? $processedDiscountData['value'] : null
            ];
            
            // Inserir registro de parcelamento
            if (method_exists($this, 'saveInstallmentRecord')) {
                $this->saveInstallmentRecord($installmentRecord);
            } else {
                // Fallback: salvar diretamente no banco
                $this->saveInstallmentRecordDirect($installmentRecord);
            }
            
            // Salvar informações detalhadas do desconto se houver
            if (!empty($processedDiscountData)) {
                if (isset($this->discountManager) && method_exists($this->discountManager, 'saveDiscountInfo')) {
                    $this->discountManager->saveDiscountInfo($result['installment'] ?? $installmentRecord['installment_id'], $processedDiscountData);
                } else {
                    // Fallback: salvar desconto diretamente
                    $this->saveDiscountInfoDirect($installmentRecord['installment_id'], $processedDiscountData);
                }
            }
            
            // Salvar splits se houver
            if (!empty($processedSplits)) {
                if (method_exists($this, 'savePaymentSplits')) {
                    $this->savePaymentSplits($result['id'] ?? $installmentRecord['first_payment_id'], $processedSplits);
                } else {
                    // Fallback: salvar splits diretamente
                    $this->savePaymentSplitsDirect($installmentRecord['first_payment_id'], $processedSplits);
                }
            }
            
            if (property_exists($this, 'db') && $this->db) {
                $this->db->commit();
            }
            Logger::info("Dados salvos no banco local");
            
        } catch (Exception $e) {
            if (property_exists($this, 'db') && $this->db) {
                $this->db->rollBack();
            }
            Logger::error("Erro ao salvar no banco: " . $e->getMessage());
            // Não falhar por erro de banco, mensalidade já foi criada no ASAAS
            Logger::warning("Mensalidade criada no ASAAS mas não salva localmente");
        }
        
        // Calcular informações para resposta
        $totalValue = $installmentCount * $installmentValue;
        $discountPerInstallment = 0;
        $totalSavings = 0;
        $finalInstallmentValue = $installmentValue;
        
        if ($processedDiscountData && $processedDiscountData['enabled']) {
            if ($processedDiscountData['type'] === 'FIXED') {
                $discountPerInstallment = $processedDiscountData['value'];
            } else {
                $discountPerInstallment = ($installmentValue * $processedDiscountData['value']) / 100;
            }
            $totalSavings = $discountPerInstallment * $installmentCount;
            $finalInstallmentValue = $installmentValue - $discountPerInstallment;
        }
        
        // Preparar mensagem de sucesso detalhada
        $successMessage = "✅ Mensalidade com desconto criada com sucesso!<br>";
        $successMessage .= "<strong>{$installmentCount} parcelas de R$ " . number_format($installmentValue, 2, ',', '.') . "</strong><br>";
        $successMessage .= "Total original: R$ " . number_format($totalValue, 2, ',', '.') . "<br>";
        
        if ($totalSavings > 0) {
            $successMessage .= "Valor com desconto: R$ " . number_format($finalInstallmentValue, 2, ',', '.') . " por parcela<br>";
            $successMessage .= "<span class='text-success'>💰 Economia total: R$ " . number_format($totalSavings, 2, ',', '.') . "</span><br>";
        }
        
        $successMessage .= "Primeiro vencimento: " . date('d/m/Y', strtotime($paymentData['dueDate']));
        
        if (!empty($result['invoiceUrl'])) {
            $successMessage .= "<br><a href='{$result['invoiceUrl']}' target='_blank' class='btn btn-sm btn-outline-primary mt-2'><i class='bi bi-eye'></i> Ver 1ª Parcela</a>";
        }
        
        $responseData = [
            'installment_data' => $result,
            'discount_info' => $processedDiscountData,
            'total_savings' => $totalSavings,
            'discount_per_installment' => $discountPerInstallment,
            'final_installment_value' => $finalInstallmentValue,
            'installment_count' => $installmentCount,
            'total_value' => $totalValue,
            'splits_applied' => count($processedSplits)
        ];
        
        Logger::info("Mensalidade com desconto criada com sucesso", [
            'installment_id' => $result['installment'] ?? $installmentRecord['installment_id'],
            'total_savings' => $totalSavings,
            'discount_type' => $processedDiscountData['type'] ?? 'none'
        ]);
        
        ApiResponse::success($responseData, $successMessage, 201);
        
    } catch (Exception $e) {
        Logger::error("Erro ao criar mensalidade com desconto: " . $e->getMessage());
        ApiResponse::error($e->getMessage());
    }
}

/**
 * MÉTODOS AUXILIARES - Adicione estes se não existirem
 */
private function saveInstallmentRecordDirect($installmentRecord) {
    try {
        // Garantir que a tabela existe
        $this->ensureInstallmentsTableExists();
        
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO installments (
                installment_id, polo_id, customer_id, installment_count, 
                installment_value, total_value, first_due_date, billing_type, 
                description, has_splits, splits_count, created_by, 
                first_payment_id, has_discount, discount_type, discount_value, 
                status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', NOW()
            )
        ");
        
        return $stmt->execute([
            $installmentRecord['installment_id'],
            $installmentRecord['polo_id'],
            $installmentRecord['customer_id'],
            $installmentRecord['installment_count'],
            $installmentRecord['installment_value'],
            $installmentRecord['total_value'],
            $installmentRecord['first_due_date'],
            $installmentRecord['billing_type'],
            $installmentRecord['description'],
            $installmentRecord['has_splits'] ? 1 : 0,
            $installmentRecord['splits_count'],
            $installmentRecord['created_by'],
            $installmentRecord['first_payment_id'],
            $installmentRecord['has_discount'] ? 1 : 0,
            $installmentRecord['discount_type'],
            $installmentRecord['discount_value']
        ]);
    } catch (Exception $e) {
        Logger::error("Erro ao salvar installment record: " . $e->getMessage());
        throw $e;
    }
}

private function saveDiscountInfoDirect($installmentId, $discountData) {
    try {
        // Garantir que a tabela existe
        $this->ensureDiscountTableExists();
        
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO installment_discounts (
                installment_id, discount_type, discount_value, 
                discount_deadline, is_active, created_at
            ) VALUES (?, ?, ?, ?, 1, NOW())
        ");
        
        return $stmt->execute([
            $installmentId,
            $discountData['type'],
            $discountData['value'],
            $discountData['deadline']
        ]);
    } catch (Exception $e) {
        Logger::error("Erro ao salvar desconto: " . $e->getMessage());
        // Não falhar por erro de desconto
        return false;
    }
}

private function savePaymentSplitsDirect($paymentId, $splits) {
    try {
        // Garantir que a tabela existe
        $this->ensureSplitsTableExists();
        
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO payment_splits (payment_id, wallet_id, split_type, percentage_value, fixed_value) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($splits as $split) {
            $splitType = isset($split['fixedValue']) ? 'FIXED' : 'PERCENTAGE';
            $percentageValue = isset($split['percentualValue']) ? $split['percentualValue'] : null;
            $fixedValue = isset($split['fixedValue']) ? $split['fixedValue'] : null;
            
            $stmt->execute([
                $paymentId,
                $split['walletId'],
                $splitType,
                $percentageValue,
                $fixedValue
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        Logger::error("Erro ao salvar splits: " . $e->getMessage());
        return false;
    }
}

private function ensureInstallmentsTableExists() {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS installments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            installment_id VARCHAR(100) NOT NULL UNIQUE,
            polo_id INT NULL,
            customer_id VARCHAR(100) NOT NULL,
            installment_count INT NOT NULL,
            installment_value DECIMAL(10,2) NOT NULL,
            total_value DECIMAL(10,2) NOT NULL,
            first_due_date DATE NOT NULL,
            billing_type VARCHAR(20) NOT NULL,
            description TEXT,
            has_splits BOOLEAN DEFAULT 0,
            splits_count INT DEFAULT 0,
            created_by INT,
            first_payment_id VARCHAR(100),
            has_discount BOOLEAN DEFAULT 0,
            discount_type ENUM('FIXED', 'PERCENTAGE') NULL,
            discount_value DECIMAL(10,2) NULL,
            status ENUM('ACTIVE', 'CANCELLED', 'COMPLETED') DEFAULT 'ACTIVE',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->getConnection()->exec($sql);
        return true;
    } catch (Exception $e) {
        Logger::error("Erro ao criar tabela installments: " . $e->getMessage());
        return false;
    }
}

private function ensureDiscountTableExists() {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS installment_discounts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            installment_id VARCHAR(100) NOT NULL,
            discount_type ENUM('FIXED', 'PERCENTAGE') NOT NULL,
            discount_value DECIMAL(10,2) NOT NULL,
            discount_deadline ENUM('DUE_DATE', 'BEFORE_DUE_DATE', '3_DAYS_BEFORE', '5_DAYS_BEFORE') NOT NULL,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->getConnection()->exec($sql);
        return true;
    } catch (Exception $e) {
        Logger::error("Erro ao criar tabela installment_discounts: " . $e->getMessage());
        return false;
    }
}

private function ensureSplitsTableExists() {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS payment_splits (
            id INT PRIMARY KEY AUTO_INCREMENT,
            payment_id VARCHAR(100) NOT NULL,
            wallet_id VARCHAR(100) NOT NULL,
            split_type ENUM('PERCENTAGE', 'FIXED') NOT NULL,
            percentage_value DECIMAL(5,2) NULL,
            fixed_value DECIMAL(10,2) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->getConnection()->exec($sql);
        return true;
    } catch (Exception $e) {
        Logger::error("Erro ao criar tabela payment_splits: " . $e->getMessage());
        return false;
    }
}
    
    /**
     * Salvar registro de parcelamento no banco
     */
    private function saveInstallmentRecord($installmentRecord) {
        // Garantir que tabela installments existe
        $this->ensureInstallmentsTable();
        
        $sql = "INSERT INTO installments (
            installment_id, polo_id, customer_id, installment_count, 
            installment_value, total_value, first_due_date, billing_type, 
            description, has_splits, splits_count, created_by, 
            first_payment_id, has_discount, discount_type, discount_value, 
            status, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', NOW()
        )";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $installmentRecord['installment_id'],
            $installmentRecord['polo_id'],
            $installmentRecord['customer_id'],
            $installmentRecord['installment_count'],
            $installmentRecord['installment_value'],
            $installmentRecord['total_value'],
            $installmentRecord['first_due_date'],
            $installmentRecord['billing_type'],
            $installmentRecord['description'],
            $installmentRecord['has_splits'] ? 1 : 0,
            $installmentRecord['splits_count'],
            $installmentRecord['created_by'],
            $installmentRecord['first_payment_id'],
            $installmentRecord['has_discount'] ? 1 : 0,
            $installmentRecord['discount_type'],
            $installmentRecord['discount_value']
        ]);
    }
    
    /**
     * Garantir que tabela installments existe
     */
    private function ensureInstallmentsTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS installments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                installment_id VARCHAR(100) NOT NULL UNIQUE,
                polo_id INT NULL,
                customer_id VARCHAR(100) NOT NULL,
                installment_count INT NOT NULL,
                installment_value DECIMAL(10,2) NOT NULL,
                total_value DECIMAL(10,2) NOT NULL,
                first_due_date DATE NOT NULL,
                billing_type VARCHAR(20) NOT NULL,
                description TEXT,
                has_splits BOOLEAN DEFAULT 0,
                splits_count INT DEFAULT 0,
                created_by INT,
                first_payment_id VARCHAR(100),
                has_discount BOOLEAN DEFAULT 0,
                discount_type ENUM('FIXED', 'PERCENTAGE') NULL,
                discount_value DECIMAL(10,2) NULL,
                status ENUM('ACTIVE', 'CANCELLED', 'COMPLETED') DEFAULT 'ACTIVE',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_installment_id (installment_id),
                INDEX idx_polo_id (polo_id),
                INDEX idx_customer_id (customer_id),
                INDEX idx_status (status),
                INDEX idx_has_discount (has_discount),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
        } catch (Exception $e) {
            Logger::error("Erro ao criar tabela installments: " . $e->getMessage());
        }
    }
    
    /**
     * Salvar splits de pagamento
     */
    private function savePaymentSplits($paymentId, $splits) {
        try {
            // Criar tabela se não existir
            $this->ensurePaymentSplitsTable();
            
            // Remover splits existentes
            $deleteStmt = $this->db->prepare("DELETE FROM payment_splits WHERE payment_id = ?");
            $deleteStmt->execute([$paymentId]);
            
            // Inserir novos splits
            $insertStmt = $this->db->prepare("
                INSERT INTO payment_splits (payment_id, wallet_id, split_type, percentage_value, fixed_value) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($splits as $split) {
                $splitType = isset($split['fixedValue']) ? 'FIXED' : 'PERCENTAGE';
                $percentageValue = isset($split['percentualValue']) ? $split['percentualValue'] : null;
                $fixedValue = isset($split['fixedValue']) ? $split['fixedValue'] : null;
                
                $insertStmt->execute([
                    $paymentId,
                    $split['walletId'],
                    $splitType,
                    $percentageValue,
                    $fixedValue
                ]);
            }
            
            return true;
        } catch (PDOException $e) {
            Logger::error("Erro ao salvar splits: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Garantir que tabela payment_splits existe
     */
    private function ensurePaymentSplitsTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS payment_splits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                payment_id VARCHAR(100) NOT NULL,
                wallet_id VARCHAR(100) NOT NULL,
                split_type ENUM('PERCENTAGE', 'FIXED') NOT NULL,
                percentage_value DECIMAL(5,2) NULL,
                fixed_value DECIMAL(10,2) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_payment_id (payment_id),
                INDEX idx_wallet_id (wallet_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
        } catch (Exception $e) {
            Logger::error("Erro ao criar tabela payment_splits: " . $e->getMessage());
        }
    }
    
    /**
     * Buscar mensalidade com informações de desconto
     */
    private function getInstallmentWithDiscount($data) {
        try {
            $installmentId = $data['installment_id'] ?? null;
            
            if (!$installmentId) {
                ApiResponse::badRequest("ID da mensalidade é obrigatório");
            }
            
            // Buscar informações da mensalidade
            $installmentSql = "SELECT * FROM installments WHERE installment_id = ?";
            $installmentStmt = $this->db->prepare($installmentSql);
            $installmentStmt->execute([$installmentId]);
            $installment = $installmentStmt->fetch();
            
            if (!$installment) {
                ApiResponse::notFound("Mensalidade não encontrada");
            }
            
            // Buscar informações de desconto se houver
            $discountInfo = null;
            if ($installment['has_discount']) {
                $discountInfo = $this->discountManager->getInstallmentDiscount($installmentId);
            }
            
            // Buscar parcelas do ASAAS
            $payments = [];
            try {
                if (class_exists('DynamicAsaasConfig')) {
                    $dynamicConfig = new DynamicAsaasConfig();
                    $asaas = $dynamicConfig->getInstance();
                } else {
                    $asaas = AsaasConfig::getInstance();
                }
                
                $paymentsResponse = $asaas->getInstallmentPayments($installmentId);
                $payments = $paymentsResponse['data'] ?? [];
                
            } catch (Exception $e) {
                Logger::warning("Erro ao buscar parcelas no ASAAS: " . $e->getMessage());
            }
            
            $responseData = [
                'installment' => $installment,
                'discount_info' => $discountInfo,
                'payments' => $payments,
                'summary' => [
                    'total_payments' => count($payments),
                    'has_discount' => (bool)$installment['has_discount'],
                    'discount_type' => $installment['discount_type'],
                    'discount_value' => $installment['discount_value']
                ]
            ];
            
            ApiResponse::success($responseData, "Mensalidade encontrada");
            
        } catch (Exception $e) {
            Logger::error("Erro ao buscar mensalidade: " . $e->getMessage());
            ApiResponse::error("Erro ao buscar mensalidade");
        }
    }
    
    /**
     * Gerar carnê em PDF com informações de desconto
     */
    private function generatePaymentBookWithDiscount($data) {
        try {
            $installmentId = $data['installment_id'] ?? null;
            
            if (!$installmentId) {
                ApiResponse::badRequest("ID da mensalidade é obrigatório");
            }
            
            // Verificar se mensalidade existe
            $installmentCheck = $this->db->prepare("SELECT * FROM installments WHERE installment_id = ?");
            $installmentCheck->execute([$installmentId]);
            $installment = $installmentCheck->fetch();
            
            if (!$installment) {
                ApiResponse::notFound("Mensalidade não encontrada");
            }
            
            // Gerar carnê via ASAAS
            try {
                if (class_exists('DynamicAsaasConfig')) {
                    $dynamicConfig = new DynamicAsaasConfig();
                    $asaas = $dynamicConfig->getInstance();
                } else {
                    $asaas = AsaasConfig::getInstance();
                }
                
                $carneResult = $asaas->generateInstallmentPaymentBook($installmentId);
                
                if ($carneResult['success']) {
                    // Salvar PDF temporariamente para download
                    $fileName = "carne_mensalidade_{$installmentId}_" . date('Y-m-d_H-i-s') . ".pdf";
                    $filePath = __DIR__ . '/downloads/' . $fileName;
                    
                    // Criar diretório se não existir
                    $downloadDir = dirname($filePath);
                    if (!is_dir($downloadDir)) {
                        mkdir($downloadDir, 0755, true);
                    }
                    
                    file_put_contents($filePath, $carneResult['pdf_content']);
                    
                    $responseData = [
                        'file_name' => $fileName,
                        'download_url' => 'downloads/' . $fileName,
                        'file_size' => filesize($filePath),
                        'installment_info' => [
                            'installment_id' => $installmentId,
                            'has_discount' => (bool)$installment['has_discount'],
                            'discount_type' => $installment['discount_type']
                        ]
                    ];
                    
                    Logger::info("Carnê gerado com desconto", ['installment_id' => $installmentId, 'file' => $fileName]);
                    ApiResponse::success($responseData, "Carnê gerado com sucesso");
                } else {
                    throw new Exception("Falha ao gerar carnê no ASAAS");
                }
                
            } catch (Exception $e) {
                Logger::error("Erro ao gerar carnê: " . $e->getMessage());
                throw new Exception("Erro ao gerar carnê: " . $e->getMessage());
            }
            
        } catch (Exception $e) {
            Logger::error("Erro ao processar geração de carnê: " . $e->getMessage());
            ApiResponse::error($e->getMessage());
        }
    }
    
    /**
     * Obter relatório de descontos aplicados
     */
    private function getDiscountReport($data) {
        try {
            $startDate = $data['start_date'] ?? date('Y-m-01');
            $endDate = $data['end_date'] ?? date('Y-m-d');
            $poloId = $data['polo_id'] ?? ($_SESSION['polo_id'] ?? null);
            
            $whereClause = "WHERE i.created_at BETWEEN ? AND ? AND i.has_discount = 1";
            $params = [$startDate, $endDate];
            
            if ($poloId) {
                $whereClause .= " AND i.polo_id = ?";
                $params[] = $poloId;
            }
            
            $sql = "
                SELECT 
                    i.*,
                    id.discount_type as detail_discount_type,
                    id.discount_value as detail_discount_value,
                    id.discount_deadline,
                    c.name as customer_name,
                    (i.installment_value * i.installment_count) as total_original_value,
                    CASE 
                        WHEN i.discount_type = 'FIXED' THEN 
                            (i.discount_value * i.installment_count)
                        ELSE 
                            ((i.installment_value * i.discount_value / 100) * i.installment_count)
                    END as total_discount_amount
                FROM installments i
                LEFT JOIN installment_discounts id ON i.installment_id = id.installment_id
                LEFT JOIN customers c ON i.customer_id = c.id
                {$whereClause}
                ORDER BY i.created_at DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
            
            // Calcular totais
            $totalInstallments = count($reportData);
            $totalOriginalValue = 0;
            $totalDiscountAmount = 0;
            
            foreach ($reportData as &$row) {
                $totalOriginalValue += $row['total_original_value'];
                $totalDiscountAmount += $row['total_discount_amount'];
                
                // Formatar valores
                $row['total_original_value'] = number_format($row['total_original_value'], 2, '.', '');
                $row['total_discount_amount'] = number_format($row['total_discount_amount'], 2, '.', '');
                $row['installment_value'] = number_format($row['installment_value'], 2, '.', '');
            }
            
            $summary = [
                'total_installments' => $totalInstallments,
                'total_original_value' => number_format($totalOriginalValue, 2, '.', ''),
                'total_discount_amount' => number_format($totalDiscountAmount, 2, '.', ''),
                'average_discount_per_installment' => $totalInstallments > 0 ? 
                    number_format($totalDiscountAmount / $totalInstallments, 2, '.', '') : '0.00',
                'discount_percentage' => $totalOriginalValue > 0 ? 
                    round(($totalDiscountAmount / $totalOriginalValue) * 100, 2) : 0
            ];
            
            $responseData = [
                'report' => $reportData,
                'summary' => $summary,
                'period' => ['start' => $startDate, 'end' => $endDate],
                'polo_context' => $poloId ? ($_SESSION['polo_nome'] ?? 'Polo ID: ' . $poloId) : 'Todos os polos'
            ];
            
            Logger::info("Relatório de descontos gerado", [
                'total_installments' => $totalInstallments,
                'period' => "$startDate a $endDate"
            ]);
            
            ApiResponse::success($responseData, "Relatório de descontos gerado com sucesso");
            
        } catch (Exception $e) {
            Logger::error("Erro ao gerar relatório de descontos: " . $e->getMessage());
            ApiResponse::error("Erro ao gerar relatório de descontos");
        }
    }
    /**
     * PARTE 4/4 - DASHBOARD, RELATÓRIOS E FINALIZAÇÃO
     * Métodos para dashboard, relatórios, exportação e funcionalidades complementares
     */
    
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
            
            // Calcular estatísticas
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
                
                // Registrar log de pagamento se tabela existir
                try {
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
                        'processed_by' => $_SESSION['usuario_id'] ?? 'sistema',
                        'processed_at' => date('Y-m-d H:i:s')
                    ]);
                } catch (Exception $e) {
                    // Log opcional - não interromper o processo
                    Logger::warning("Erro ao criar log de pagamento: " . $e->getMessage());
                }
                
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
                    'student_name' => $installment['student_name'] ?? 'N/A',
                    'amount' => $finalAmount ?? 0,
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
     * Obter dados do dashboard com estatísticas completas incluindo descontos
     */
    private function getDashboardData($data) {
        try {
            $month = $data['month'] ?? date('m');
            $year = $data['year'] ?? date('Y');
            $poloId = $_SESSION['polo_id'] ?? null;
            
            // Adicionar filtro de polo se não for master
            $poloFilter = '';
            $poloParams = [];
            if ($poloId && !($_SESSION['usuario_tipo'] ?? '') === 'master') {
                $poloFilter = 'WHERE polo_id = :polo_id';
                $poloParams['polo_id'] = $poloId;
            }
            
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
                    COALESCE(SUM(CASE WHEN i.status = 'pending' AND i.due_date < CURDATE() THEN (i.amount - COALESCE(i.discount, 0)) END), 0) as total_overdue,
                    COUNT(CASE WHEN i.has_discount = 1 THEN 1 END) as installments_with_discount,
                    COALESCE(SUM(CASE WHEN i.has_discount = 1 THEN 
                        CASE WHEN i.discount_type = 'FIXED' THEN i.discount_value * i.installment_count
                        ELSE (i.installment_value * i.discount_value / 100) * i.installment_count END
                    END), 0) as total_discount_applied
                FROM students s
                LEFT JOIN installments i ON s.id = i.student_id
                " . ($poloFilter ? str_replace('polo_id', 's.polo_id', $poloFilter) : '')
            );
            $generalStats->execute($poloParams);
            $stats = $generalStats->fetch();
            
            // Estatísticas do mês atual
            $monthlyStats = $this->db->prepare("
                SELECT 
                    COUNT(i.id) as monthly_installments,
                    COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as monthly_paid,
                    COUNT(CASE WHEN i.status = 'pending' THEN 1 END) as monthly_pending,
                    COUNT(CASE WHEN i.status = 'pending' AND i.due_date < CURDATE() THEN 1 END) as monthly_overdue,
                    COALESCE(SUM(CASE WHEN i.status = 'paid' THEN (i.amount - COALESCE(i.discount, 0)) END), 0) as monthly_received,
                    COALESCE(SUM(CASE WHEN i.status = 'pending' THEN (i.amount - COALESCE(i.discount, 0)) END), 0) as monthly_pending_amount,
                    COUNT(CASE WHEN i.has_discount = 1 THEN 1 END) as monthly_with_discount
                FROM installments i
                WHERE MONTH(i.due_date) = :month AND YEAR(i.due_date) = :year
                " . ($poloId ? 'AND i.polo_id = :polo_id' : '')
            );
            $monthlyParams = array_merge(['month' => $month, 'year' => $year], $poloParams);
            $monthlyStats->execute($monthlyParams);
            $monthlyData = $monthlyStats->fetch();
            
            // Receitas dos últimos 6 meses
            $revenueChart = $this->db->prepare("
                SELECT 
                    DATE_FORMAT(i.due_date, '%Y-%m') as month,
                    COALESCE(SUM(CASE WHEN i.status = 'paid' THEN (i.amount - COALESCE(i.discount, 0)) END), 0) as received,
                    COALESCE(SUM(CASE WHEN i.status = 'pending' THEN (i.amount - COALESCE(i.discount, 0)) END), 0) as pending
                FROM installments i
                WHERE i.due_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                " . ($poloId ? 'AND i.polo_id = :polo_id' : '') . "
                GROUP BY DATE_FORMAT(i.due_date, '%Y-%m')
                ORDER BY month ASC
            ");
            $revenueChart->execute($poloParams);
            $revenueData = $revenueChart->fetchAll();
            
            // Top cursos por receita (buscar da tabela installments via customers)
            $topCourses = $this->db->prepare("
                SELECT 
                    c.course,
                    COUNT(DISTINCT c.id) as students_count,
                    COUNT(i.id) as total_installments,
                    COALESCE(SUM(CASE WHEN i.status = 'paid' THEN (i.amount - COALESCE(i.discount, 0)) END), 0) as total_revenue
                FROM customers c
                LEFT JOIN installments i ON c.id = i.customer_id
                WHERE c.course IS NOT NULL AND c.course != ''
                " . ($poloId ? 'AND c.polo_id = :polo_id' : '') . "
                GROUP BY c.course
                ORDER BY total_revenue DESC
                LIMIT 10
            ");
            $topCourses->execute($poloParams);
            $coursesData = $topCourses->fetchAll();
            
            // Mensalidades vencendo nos próximos 7 dias
            $upcomingDue = $this->db->prepare("
                SELECT 
                    i.id, i.amount, i.due_date, i.discount, i.has_discount,
                    c.name as customer_name, c.email as customer_email,
                    c.course as customer_course,
                    (i.amount - COALESCE(i.discount, 0)) as final_amount,
                    DATEDIFF(i.due_date, CURDATE()) as days_until_due
                FROM installments i
                LEFT JOIN customers c ON i.customer_id = c.id
                WHERE i.status = 'pending' 
                AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                " . ($poloId ? 'AND i.polo_id = :polo_id' : '') . "
                ORDER BY i.due_date ASC
                LIMIT 20
            ");
            $upcomingDue->execute($poloParams);
            $upcomingInstallments = $upcomingDue->fetchAll();
            
            // Mensalidades em atraso
            $overdueInstallments = $this->db->prepare("
                SELECT 
                    i.id, i.amount, i.due_date, i.discount, i.has_discount,
                    c.name as customer_name, c.email as customer_email,
                    c.course as customer_course,
                    (i.amount - COALESCE(i.discount, 0)) as final_amount,
                    DATEDIFF(CURDATE(), i.due_date) as days_overdue
                FROM installments i
                LEFT JOIN customers c ON i.customer_id = c.id
                WHERE i.status = 'pending' AND i.due_date < CURDATE()
                " . ($poloId ? 'AND i.polo_id = :polo_id' : '') . "
                ORDER BY i.due_date ASC
                LIMIT 20
            ");
            $overdueInstallments->execute($poloParams);
            $overdueData = $overdueInstallments->fetchAll();
            
            // Métodos de pagamento mais utilizados
            $paymentMethods = $this->db->prepare("
                SELECT 
                    COALESCE(payment_method, 'Não informado') as method,
                    COUNT(*) as count,
                    SUM(amount - COALESCE(discount, 0)) as total_amount
                FROM installments 
                WHERE status = 'paid'
                " . ($poloId ? 'AND polo_id = :polo_id' : '') . "
                GROUP BY payment_method
                ORDER BY count DESC
            ");
            $paymentMethods->execute($poloParams);
            $paymentMethodsData = $paymentMethods->fetchAll();
            
            // Formatar dados monetários
            $formatMoney = function($value) {
                return number_format(floatval($value), 2, '.', '');
            };
            
            foreach (['total_received', 'total_pending', 'total_overdue', 'total_discount_applied'] as $field) {
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
                'generated_at' => date('d/m/Y H:i:s'),
                'context' => [
                    'polo_id' => $poloId,
                    'user_type' => $_SESSION['usuario_tipo'] ?? 'unknown',
                    'month' => $month,
                    'year' => $year
                ]
            ];
            
            Logger::info("Dashboard acessado", ['month' => $month, 'year' => $year, 'polo_id' => $poloId]);
            ApiResponse::success($dashboardData, "Dados do dashboard carregados com sucesso");
            
        } catch (Exception $e) {
            Logger::error("Erro ao carregar dashboard: " . $e->getMessage());
            ApiResponse::error("Erro ao carregar dados do dashboard");
        }
    }
    
    /**
     * Gerar relatórios personalizados incluindo desconto
     */
    private function getReports($data) {
        try {
            $reportType = $data['type'] ?? 'general';
            $startDate = $data['start_date'] ?? date('Y-m-01');
            $endDate = $data['end_date'] ?? date('Y-m-t');
            $poloId = $data['polo_id'] ?? ($_SESSION['polo_id'] ?? null);
            $studentId = $data['student_id'] ?? null;
            $course = $data['course'] ?? null;
            
            switch ($reportType) {
                case 'financial':
                    $report = $this->generateFinancialReport($startDate, $endDate, $course, $poloId);
                    break;
                    
                case 'student':
                    $report = $this->generateStudentReport($studentId, $startDate, $endDate);
                    break;
                    
                case 'overdue':
                    $report = $this->generateOverdueReport($startDate, $endDate, $poloId);
                    break;
                    
                case 'course':
                    $report = $this->generateCourseReport($startDate, $endDate, $poloId);
                    break;
                    
                case 'discount':
                    $report = $this->generateDiscountReport($startDate, $endDate, $poloId);
                    break;
                    
                default:
                    $report = $this->generateGeneralReport($startDate, $endDate, $poloId);
                    break;
            }
            
            Logger::info("Relatório gerado", [
                'type' => $reportType,
                'period' => "$startDate a $endDate",
                'polo_id' => $poloId
            ]);
            
            ApiResponse::success($report, "Relatório gerado com sucesso");
            
        } catch (Exception $e) {
            Logger::error("Erro ao gerar relatório: " . $e->getMessage());
            ApiResponse::error("Erro ao gerar relatório");
        }
    }
    
    /**
     * Relatório financeiro com informações de desconto
     */
    private function generateFinancialReport($startDate, $endDate, $course = null, $poloId = null) {
        $whereClause = "WHERE i.due_date BETWEEN :start_date AND :end_date";
        $params = ['start_date' => $startDate, 'end_date' => $endDate];
        
        if ($course) {
            $whereClause .= " AND c.course LIKE :course";
            $params['course'] = "%$course%";
        }
        
        if ($poloId) {
            $whereClause .= " AND i.polo_id = :polo_id";
            $params['polo_id'] = $poloId;
        }
        
        $sql = "SELECT 
            DATE_FORMAT(i.due_date, '%Y-%m') as month,
            COUNT(i.id) as total_installments,
            COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as paid_count,
            COUNT(CASE WHEN i.status = 'pending' THEN 1 END) as pending_count,
            SUM(i.amount) as gross_amount,
            SUM(COALESCE(i.discount, 0)) as total_discounts,
            SUM(i.amount - COALESCE(i.discount, 0)) as net_amount,
            SUM(CASE WHEN i.status = 'paid' THEN (i.amount - COALESCE(i.discount, 0)) END) as received_amount,
            SUM(CASE WHEN i.status = 'pending' THEN (i.amount - COALESCE(i.discount, 0)) END) as pending_amount,
            COUNT(CASE WHEN i.has_discount = 1 THEN 1 END) as installments_with_discount
        FROM installments i
        LEFT JOIN customers c ON i.customer_id = c.id
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
        'polo_id' => $poloId,
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
private function generateOverdueReport($startDate, $endDate, $poloId = null) {
    $whereClause = "WHERE i.status = 'pending' AND i.due_date < CURDATE() AND i.due_date BETWEEN :start_date AND :end_date";
    $params = ['start_date' => $startDate, 'end_date' => $endDate];
    
    if ($poloId) {
        $whereClause .= " AND i.polo_id = :polo_id";
        $params['polo_id'] = $poloId;
    }
    
    $sql = "
        SELECT 
            i.*,
            c.name as customer_name,
            c.email as customer_email,
            c.phone as customer_phone,
            c.course as customer_course,
            (i.amount - COALESCE(i.discount, 0)) as final_amount,
            DATEDIFF(CURDATE(), i.due_date) as days_overdue
        FROM installments i
        LEFT JOIN customers c ON i.customer_id = c.id
        $whereClause
        ORDER BY i.due_date ASC, days_overdue DESC
    ";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    
    return [
        'type' => 'overdue',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'polo_id' => $poloId,
        'overdue_installments' => $stmt->fetchAll()
    ];
}

/**
 * Relatório por curso
 */
private function generateCourseReport($startDate, $endDate, $poloId = null) {
    $whereClause = "WHERE i.due_date BETWEEN :start_date AND :end_date";
    $params = ['start_date' => $startDate, 'end_date' => $endDate];
    
    if ($poloId) {
        $whereClause .= " AND c.polo_id = :polo_id";
        $params['polo_id'] = $poloId;
    }
    
    $sql = "
        SELECT 
            c.course,
            COUNT(DISTINCT c.id) as total_students,
            COUNT(i.id) as total_installments,
            COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as paid_installments,
            COUNT(CASE WHEN i.status = 'pending' THEN 1 END) as pending_installments,
            SUM(i.amount) as gross_revenue,
            SUM(COALESCE(i.discount, 0)) as total_discounts,
            SUM(CASE WHEN i.status = 'paid' THEN (i.amount - COALESCE(i.discount, 0)) END) as received_revenue,
            SUM(CASE WHEN i.status = 'pending' THEN (i.amount - COALESCE(i.discount, 0)) END) as pending_revenue,
            COUNT(CASE WHEN i.has_discount = 1 THEN 1 END) as installments_with_discount
        FROM customers c
        LEFT JOIN installments i ON c.id = i.customer_id AND i.due_date BETWEEN :start_date AND :end_date
        WHERE c.course IS NOT NULL AND c.course != '' 
        " . ($poloId ? "AND c.polo_id = :polo_id" : "") . "
        GROUP BY c.course
        ORDER BY received_revenue DESC
    ";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    
    return [
        'type' => 'course',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'polo_id' => $poloId,
        'courses' => $stmt->fetchAll()
    ];
}

/**
 * Relatório específico de descontos
 */
private function generateDiscountReport($startDate, $endDate, $poloId = null) {
    $whereClause = "WHERE i.created_at BETWEEN :start_date AND :end_date AND i.has_discount = 1";
    $params = ['start_date' => $startDate, 'end_date' => $endDate];
    
    if ($poloId) {
        $whereClause .= " AND i.polo_id = :polo_id";
        $params['polo_id'] = $poloId;
    }
    
    $sql = "
        SELECT 
            i.*,
            c.name as customer_name,
            c.email as customer_email,
            c.course as customer_course,
            (i.installment_value * i.installment_count) as total_original_value,
            CASE 
                WHEN i.discount_type = 'FIXED' THEN 
                    (i.discount_value * i.installment_count)
                ELSE 
                    ((i.installment_value * i.discount_value / 100) * i.installment_count)
            END as total_discount_amount,
            ROUND(
                CASE 
                    WHEN i.discount_type = 'FIXED' THEN 
                        (i.discount_value / i.installment_value) * 100
                    ELSE 
                        i.discount_value
                END, 2
            ) as discount_percentage
        FROM installments i
        LEFT JOIN customers c ON i.customer_id = c.id
        $whereClause
        ORDER BY i.created_at DESC
    ";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    $discountData = $stmt->fetchAll();
    
    // Calcular totais
    $totalOriginalValue = 0;
    $totalDiscountAmount = 0;
    
    foreach ($discountData as $row) {
        $totalOriginalValue += $row['total_original_value'];
        $totalDiscountAmount += $row['total_discount_amount'];
    }
    
    return [
        'type' => 'discount',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'polo_id' => $poloId,
        'discount_installments' => $discountData,
        'summary' => [
            'total_installments_with_discount' => count($discountData),
            'total_original_value' => $totalOriginalValue,
            'total_discount_amount' => $totalDiscountAmount,
            'average_discount_percentage' => $totalOriginalValue > 0 ? 
                round(($totalDiscountAmount / $totalOriginalValue) * 100, 2) : 0
        ]
    ];
}

/**
 * Relatório geral
 */
private function generateGeneralReport($startDate, $endDate, $poloId = null) {
    $financialData = $this->generateFinancialReport($startDate, $endDate, null, $poloId);
    $overdueData = $this->generateOverdueReport($startDate, $endDate, $poloId);
    $courseData = $this->generateCourseReport($startDate, $endDate, $poloId);
    $discountData = $this->generateDiscountReport($startDate, $endDate, $poloId);
    
    return [
        'type' => 'general',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'polo_id' => $poloId,
        'financial_summary' => $financialData['data'],
        'overdue_summary' => $overdueData['overdue_installments'],
        'courses_summary' => $courseData['courses'],
        'discount_summary' => $discountData
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
        $poloId = $filters['polo_id'] ?? ($_SESSION['polo_id'] ?? null);
        
        switch ($exportType) {
            case 'students':
                $exportData = $this->exportStudents($filters, $poloId);
                $filename = "estudantes_" . date('Y-m-d_H-i-s');
                break;
                
            case 'installments':
                $exportData = $this->exportInstallments($filters, $poloId);
                $filename = "mensalidades_" . date('Y-m-d_H-i-s');
                break;
                
            case 'financial':
                $exportData = $this->exportFinancial($filters, $poloId);
                $filename = "financeiro_" . date('Y-m-d_H-i-s');
                break;
                
            case 'discount':
                $exportData = $this->exportDiscounts($filters, $poloId);
                $filename = "descontos_" . date('Y-m-d_H-i-s');
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
            'records' => count($exportData),
            'polo_id' => $poloId
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
private function exportStudents($filters, $poloId = null) {
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
    
    if ($poloId) {
        $whereConditions[] = "polo_id = :polo_id";
        $params['polo_id'] = $poloId;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "SELECT name, email, cpf, phone, course, status, created_at FROM students $whereClause ORDER BY name";
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Exportar dados de mensalidades com desconto
 */
private function exportInstallments($filters, $poloId = null) {
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
    
    if ($poloId) {
        $whereConditions[] = "i.polo_id = :polo_id";
        $params['polo_id'] = $poloId;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "
        SELECT 
            c.name as estudante,
            c.course as curso,
            i.amount as valor,
            i.discount as desconto,
            (i.amount - COALESCE(i.discount, 0)) as valor_final,
            i.due_date as vencimento,
            i.paid_date as data_pagamento,
            i.status,
            i.payment_method as forma_pagamento,
            CASE WHEN i.has_discount THEN 'Sim' ELSE 'Não' END as tem_desconto,
            i.discount_type as tipo_desconto,
            i.discount_value as valor_desconto
        FROM installments i
        LEFT JOIN customers c ON i.customer_id = c.id
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
private function exportFinancial($filters, $poloId = null) {
    $startDate = $filters['start_date'] ?? date('Y-m-01');
    $endDate = $filters['end_date'] ?? date('Y-m-t');
    
    return $this->generateFinancialReport($startDate, $endDate, null, $poloId)['data'];
}

/**
 * Exportar dados de descontos
 */
private function exportDiscounts($filters, $poloId = null) {
    $startDate = $filters['start_date'] ?? date('Y-m-01');
    $endDate = $filters['end_date'] ?? date('Y-m-t');
    
    $discountReport = $this->generateDiscountReport($startDate, $endDate, $poloId);
    return $discountReport['discount_installments'];
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


// Inicialização e execução da API
try {
$api = new MensalidadeAPI();
$api->handleRequest();
} catch (Exception $e) {
Logger::error("Erro fatal na inicialização: " . $e->getMessage());
ApiResponse::error("Erro interno do servidor");
}

/**
* TABELAS SQL NECESSÁRIAS PARA O SISTEMA COMPLETO COM DESCONTO
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

-- Tabela de clientes (para integração ASAAS)
CREATE TABLE IF NOT EXISTS customers (
id VARCHAR(100) PRIMARY KEY,
polo_id INT NULL,
name VARCHAR(255) NOT NULL,
email VARCHAR(255) NOT NULL,
cpf_cnpj VARCHAR(20),
mobile_phone VARCHAR(20),
address TEXT,
course VARCHAR(255),
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

INDEX idx_polo_id (polo_id),
INDEX idx_email (email),
INDEX idx_cpf_cnpj (cpf_cnpj),
INDEX idx_course (course)
);

-- Tabela de mensalidades tradicionais
CREATE TABLE IF NOT EXISTS installments (
id INT AUTO_INCREMENT PRIMARY KEY,
student_id INT,
amount DECIMAL(10,2) NOT NULL,
discount DECIMAL(10,2) DEFAULT 0,
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

-- Tabela de parcelamentos ASAAS (NOVA - COM DESCONTO)
CREATE TABLE IF NOT EXISTS installments (
id INT AUTO_INCREMENT PRIMARY KEY,
installment_id VARCHAR(100) NOT NULL UNIQUE,
polo_id INT NULL,
customer_id VARCHAR(100) NOT NULL,
installment_count INT NOT NULL,
installment_value DECIMAL(10,2) NOT NULL,
total_value DECIMAL(10,2) NOT NULL,
first_due_date DATE NOT NULL,
billing_type VARCHAR(20) NOT NULL,
description TEXT,
has_splits BOOLEAN DEFAULT 0,
splits_count INT DEFAULT 0,
created_by INT,
first_payment_id VARCHAR(100),
has_discount BOOLEAN DEFAULT 0,
discount_type ENUM('FIXED', 'PERCENTAGE') NULL,
discount_value DECIMAL(10,2) NULL,
status ENUM('ACTIVE', 'CANCELLED', 'COMPLETED') DEFAULT 'ACTIVE',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

INDEX idx_installment_id (installment_id),
INDEX idx_polo_id (polo_id),
INDEX idx_customer_id (customer_id),
INDEX idx_status (status),
INDEX idx_has_discount (has_discount),
INDEX idx_created_at (created_at)
);

-- Tabela de detalhes de desconto
CREATE TABLE IF NOT EXISTS installment_discounts (
id INT AUTO_INCREMENT PRIMARY KEY,
installment_id VARCHAR(100) NOT NULL,
discount_type ENUM('FIXED', 'PERCENTAGE') NOT NULL,
discount_value DECIMAL(10,2) NOT NULL,
discount_deadline ENUM('DUE_DATE', 'BEFORE_DUE_DATE', '3_DAYS_BEFORE', '5_DAYS_BEFORE') NOT NULL,
is_active BOOLEAN DEFAULT 1,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

INDEX idx_installment_id (installment_id),
INDEX idx_is_active (is_active)
);

-- Tabela de parcelas individuais
CREATE TABLE IF NOT EXISTS installment_payments (
id INT AUTO_INCREMENT PRIMARY KEY,
installment_id VARCHAR(100) NOT NULL,
payment_id VARCHAR(100) NOT NULL,
installment_number INT NOT NULL,
due_date DATE NOT NULL,
value DECIMAL(10,2) NOT NULL,
status VARCHAR(20) DEFAULT 'PENDING',
paid_date DATETIME NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

INDEX idx_installment_id (installment_id),
INDEX idx_payment_id (payment_id),
INDEX idx_due_date (due_date),
INDEX idx_status (status)
);

-- Tabela de splits de pagamento
CREATE TABLE IF NOT EXISTS payment_splits (
id INT PRIMARY KEY AUTO_INCREMENT,
payment_id VARCHAR(100) NOT NULL,
wallet_id VARCHAR(100) NOT NULL,
split_type ENUM('PERCENTAGE', 'FIXED') NOT NULL,
percentage_value DECIMAL(5,2) NULL,
fixed_value DECIMAL(10,2) NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

INDEX idx_payment_id (payment_id),
INDEX idx_wallet_id (wallet_id)
);

-- Tabela de logs de pagamento
CREATE TABLE IF NOT EXISTS payment_logs (
id INT AUTO_INCREMENT PRIMARY KEY,
installment_id INT,
student_id INT,
amount DECIMAL(10,2) NOT NULL,
discount DECIMAL(10,2) DEFAULT 0,
final_amount DECIMAL(10,2) NOT NULL,
payment_method VARCHAR(50),
paid_date TIMESTAMP NOT NULL,
processed_by VARCHAR(255),
processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

INDEX idx_installment_id (installment_id),
INDEX idx_student_id (student_id),
INDEX idx_paid_date (paid_date)
);
*/