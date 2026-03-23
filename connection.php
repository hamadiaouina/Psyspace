<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = getenv('DB_HOST');
$DB_PORT = getenv('DB_PORT');
$DB_NAME = getenv('DB_NAME');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS'); // peut être false si non défini

// Defaults
$DB_HOST = ($DB_HOST !== false && $DB_HOST !== '') ? $DB_HOST : '127.0.0.1';
$DB_PORT = ($DB_PORT !== false && $DB_PORT !== '') ? (int)$DB_PORT : 3306;
$DB_NAME = ($DB_NAME !== false) ? $DB_NAME : '';
$DB_USER = ($DB_USER !== false) ? $DB_USER : '';
$DB_PASS = ($DB_PASS !== false) ? $DB_PASS : ''; // si pas défini => vide (pas de mot de passe)

// Trim pour éviter les " " (espaces) qui font using password: YES
$DB_NAME = trim($DB_NAME);
$DB_USER = trim($DB_USER);
// ne trim pas forcément DB_PASS en prod, mais en local ça aide:
$DB_PASS = rtrim($DB_PASS, "\r\n");

// Logs (attention: ne loggue JAMAIS le password)
error_log('ENV DB_HOST=' . ($DB_HOST ?: '(empty)'));
error_log('ENV DB_PORT=' . ($DB_PORT ?: '(empty)'));
error_log('ENV DB_NAME=' . ($DB_NAME ?: '(empty)'));
error_log('ENV DB_USER=' . ($DB_USER ?: '(empty)'));
error_log('ENV DB_PASS_LEN=' . strlen($DB_PASS));

if ($DB_NAME === '' || $DB_USER === '') {
    http_response_code(500);
    exit('Server misconfigured: missing DB_NAME/DB_USER environment variables.');
}

// Tentative de connexion
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
$conn->set_charset('utf8mb4');