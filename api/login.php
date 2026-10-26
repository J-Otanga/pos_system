<?php
// api/login.php
session_start();
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Please enter username/email and password."]);
    exit();
}

try {
    // LOWER() makes username/email lookup case-insensitive (e.g. "Sarah" matches "sarah")
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {

        // Check if account is inactive
        if (isset($user['status']) && $user['status'] === 'inactive') {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Account is inactive. Contact administrator."]);
            exit();
        }

        $_SESSION['user'] = [
            'id'                    => $user['id'],
            'full_name'             => $user['full_name'],
            'username'              => $user['username'],
            'role'                  => strtolower($user['role']),
            'force_password_change' => (bool)$user['force_password_change']
        ];

        echo json_encode([
            "status"                => "success",
            "message"               => "Login successful",
            "force_password_change" => (bool)$user['force_password_change'],
            "user"                  => $_SESSION['user']
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid username or password."]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>