<?php
// ==========================================================================
// BEAUTY STORE POS - CHANGE PASSWORD API
// File: api/change_password.php
// ==========================================================================

header("Content-Type: application/json");
require_once __DIR__ . '/auth_check.php';

// Ensure user is logged in (either cashier or admin)
checkAuth();

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed."]);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $currentPassword = trim($input['current_password'] ?? '');
    $newPassword     = trim($input['new_password'] ?? '');

    if (empty($currentPassword) || empty($newPassword)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Both current and new passwords are required."]);
        exit();
    }

    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "New password must be at least 6 characters long."]);
        exit();
    }

    $userId = $_SESSION['user']['id'];

    // Fetch user hash from database
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Incorrect current password."]);
        exit();
    }

    // Hash new password and update database
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET password_hash = ?, force_password_change = FALSE 
        WHERE id = ?
    ");
    $updateStmt->execute([$newHash, $userId]);

    // Update session flag
    $_SESSION['user']['force_password_change'] = false;

    echo json_encode([
        "status"  => "success", 
        "message" => "Password updated successfully."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>