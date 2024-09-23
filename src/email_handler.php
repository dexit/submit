<?php
require './vendor/autoload.php';
// Include the PHPMailer library
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include the configuration file
$config = json_decode(file_get_contents('config.json'), true);

// Initialize the PHPMailer object
$mail = new PHPMailer(true);

// Set the SMTP settings
$mail->isSMTP();
$mail->Host = $config['smtp_host'];
$mail->Port = $config['smtp_port'];
$mail->SMTPAuth = true;
$mail->Username = $config['smtp_username'];
$mail->Password = $config['smtp_password'];
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

// Set the email address and name
$mail->setFrom($config['smtp_from'], 'Email Validator');

// Define the email validation function
function validateEmail($email) {
    // Validate the email format using regex
    if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
        return false;
    }

    // Validate the TLD
    $parts = explode('.', $email);
    $tld = end($parts);
    if (empty($tld) || strlen($tld) < 2) {
        return false;
    }

    // Validate the domain name
    $domain = explode('@', $email)[1];
    if (checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA')) {
        return true;
    }

    // Validate the MX record
    if (checkdnsrr($domain, 'MX')) {
        return true;
    }

    // Perform SMTP validation
    try {
        $mail->addAddress($email);
        $mail->validateAddress($email);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Define the CSV upload and processing function
function processCSV($csvFile, $emailColumnName) {
    // Initialize the CSV file handle
    $file = fopen($csvFile, 'r');

    // Get the CSV header
    $header = fgetcsv($file);

    // Find the email column index
    $emailColumnIndex = array_search($emailColumnName, $header);

    // Initialize the results array
    $results = [];

    // Initialize the row number
    $rowNumber = 1;

    // Process each row in the CSV file
    while (($row = fgetcsv($file))!== false) {
        // Increment the row number
        $rowNumber++;

        // Get the email address
        $email = $row[$emailColumnIndex];

        // Validate the email address
        $validationResults = [
            'row' => $rowNumber,
            'email' => $email,
            'regex' => validateEmail($email)? 'Pass' : 'Fail',
            'tld' => validateEmail($email)? 'Pass' : 'Fail',
            'domain' => validateEmail($email)? 'Pass' : 'Fail',
            'mx' => validateEmail($email)? 'Pass' : 'Fail',
            'smtp' => validateEmail($email)? 'Pass' : 'Fail',
        ];

        // Add the validation results to the results array
        $results[] = $validationResults;

        // Update the progress bar and text
        updateProgress($rowNumber);
    }

    // Close the CSV file handle
    fclose($file);

    // Return the results array
    return $results;
}

// Define the update progress function
function updateProgress($rowNumber) {
    // Update the progress bar and text
    $progressBar = $('#progressBar');
    $progressBarText = $('#progressBarText');
    $progressBar.css('width', ($rowNumber / totalRows) * 100 + '%');
    $progressBarText.text('Processing row ' + $rowNumber + ' of ' + totalRows);
}

// Define the total number of rows in the CSV file
$totalRows = 0;

// Define the current row number
$currentRow = 1;

// Define the interval for updating the progress bar
$intervalId = setInterval(function() {
    // Update the progress bar and text
    updateProgress($currentRow);

    // Increment the current row number
    $currentRow++;

    // If we've reached the end of the CSV file, clear the interval
    if ($currentRow > $totalRows) {
        clearInterval($intervalId);
    }
}, 100); // Update every 100ms

// Process the CSV file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    $csvFile = $_FILES['csvFile']['tmp_name'];
    $emailColumnName = $_POST['emailColumnName'];

    // Initialize the results array
    $results = [];

    // Get the total number of rows in the CSV file
    $file = fopen($csvFile, 'r');
    $header = fgetcsv($file);
    $totalRows = count(fgetcsv($file)) - 1;
    fclose($file);

    // Process the CSV file
    $results = processCSV($csvFile, $emailColumnName);

    // Clear the interval
    clearInterval($intervalId);

    // Populate the results table with the response data
    $table = $('#resultsTable').DataTable();
    $table->clear()->rows->add($results)->draw();

    // Update the progress bar and text
    $progressBar = $('#progressBar');
    $progressBarText = $('#progressBarText');
    $progressBar.css('width', '100%');
    $progressBarText.text('Processing complete!');
} else {
    // Validate the email address
    $email = $_POST['email'];
    $validationResults = [
        'email' => $email,
        'regex' => validateEmail($email)? 'Pass' : 'Fail',
        'tld' => validateEmail($email)? 'Pass' : 'Fail',
        'domain' => validateEmail($email)? 'Pass' : 'Fail',
        'mx' => validateEmail($email)? 'Pass' : 'Fail',
        'smtp' => validateEmail($email)? 'Pass' : 'Fail',
    ];

    // Populate the results table with the response data
    $table = $('#resultsTable').DataTable();
    $table->clear()->rows->add($validationResults)->draw();
}

?>