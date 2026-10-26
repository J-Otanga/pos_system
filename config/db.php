<?php
$host     = "localhost";
$port     = "5432";
$dbname   = "beauty_shop";
$user     = "postgres";        // Replace with your Postgres username
$password = "1234";   // Replace with your Postgres password

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    header("Content-Type: application/json");
    http_response_code(500);
    echo json_encode(["error" => "Database Connection Failed: " . $e->getMessage()]);
    exit;
}
?>