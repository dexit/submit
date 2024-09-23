<?php

require './../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\JsonHandler;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$configFile = $_ENV['CONFIG'];
if (!file_exists($configFile)) {
    die("Error: Configuration file '$configFile' not found.");
}
$config = json_decode(file_get_contents($configFile), true);

$logger = new Logger('email_validator');
$logType = $_ENV['LOGGER'];

if ($logType === 'json' || $logType === 'both') {
    $logger->pushHandler(new JsonHandler('logs/' . $_ENV['LOG_FILE'], Logger::DEBUG));
}

if ($logType === 'mysql' || $logType === 'both') {
    require 'MySQLHandler.php';
    try {
        $pdo = new PDO("mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $createLogsTableQuery = "CREATE TABLE IF NOT EXISTS `logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `channel` VARCHAR(255) NOT NULL,
            `level` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `context` TEXT,
            `datetime` DATETIME NOT NULL
        )";
        $pdo->exec($createLogsTableQuery);
        $logger->pushHandler(new MySQLHandler($pdo, 'logs', [], Logger::DEBUG));
    } catch (PDOException $e) {
        $logger->error("PDO connection failed: " . $e->getMessage());
        die("PDO connection failed: " . $e->getMessage());
    }
}

function apiResponse($status, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
    ]);
    exit;
}

function logMessage($message) {
    global $logger;
    $logger->info($message);
}

function validateEmailFormat($email) {
    return preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email);
}

function validateTLD($email) {
    $parts = explode('.', $email);
    $tld = end($parts);
    return !empty($tld) && strlen($tld) >= 2;
}

function validateDomain($email) {
    $domain = explode('@', $email)[1];
    return checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
}

function validateMXRecord($email) {
    $domain = explode('@', $email)[1];
    return checkdnsrr($domain, 'MX');
}

function validateSMTP($email) {
    global $config;
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->Port = $config['smtp_port'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom($config['smtp_from'], 'Email Validator');
        $mail->addAddress($email);
        return $mail->validateAddress($email);
    } catch (Exception $e) {
        logMessage("SMTP Validation Error for $email: " . $e->getMessage());
        return false;
    }
}

function processCSV($csvFile, $emailColumnName) {
    global $logger;
    $file = fopen($csvFile, 'r');
    if (!$file) {
        apiResponse('error', 'Failed to open CSV file.');
    }

    $header = fgetcsv($file);
    $emailColumnIndex = array_search($emailColumnName, $header);

    if ($emailColumnIndex === false) {
        apiResponse('error', "Email column '$emailColumnName' not found in CSV.");
    }

    $results = [];
    $rowNumber = 1;
    while (($row = fgetcsv($file)) !== false) {
        $rowNumber++;
        $email = $row[$emailColumnIndex];

        $validationResults = [
            'row' => $rowNumber,
            'email' => $email,
            'format' => validateEmailFormat($email) ? 'Pass' : 'Fail',
            'tld' => validateTLD($email) ? 'Pass' : 'Fail',
            'domain' => validateDomain($email) ? 'Pass' : 'Fail',
            'mx' => validateMXRecord($email) ? 'Pass' : 'Fail',
            'smtp' => validateSMTP($email) ? 'Pass' : 'Fail',
        ];

        $results[] = $validationResults;
    }

    fclose($file);
    return $results;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csvFile'])) {
        $csvFile = $_FILES['csvFile']['tmp_name'];
        $emailColumnName = $_POST['emailColumnName'];
        $results = processCSV($csvFile, $emailColumnName);
        apiResponse('success', 'CSV processed.', $results);
    } elseif (isset($_POST['email'])) {
        $email = $_POST['email'];
        $validationResults = [
            'email' => $email,
            'format' => validateEmailFormat($email) ? 'Pass' : 'Fail',
            'tld' => validateTLD($email) ? 'Pass' : 'Fail',
            'domain' => validateDomain($email) ? 'Pass' : 'Fail',
            'mx' => validateMXRecord($email) ? 'Pass' : 'Fail',
            'smtp' => validateSMTP($email) ? 'Pass' : 'Fail',
        ];
        apiResponse('success', 'Email validated.', $validationResults);
    } else {
        apiResponse('error', 'Invalid request.');
    }
}

?>