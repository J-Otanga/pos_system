<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Utility: Mask email address (e.g. sarah.mtu@gmail.com -> s***h.m*u@g***l.com)
function maskEmail($email) {
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '***@***.com';
    }
    list($user, $domain) = explode('@', $email);
    
    // Mask username
    $userLen = strlen($user);
    if ($userLen <= 2) {
        $maskedUser = $user[0] . '***';
    } else {
        $maskedUser = $user[0] . str_repeat('*', max(1, $userLen - 2)) . $user[$userLen - 1];
    }

    // Mask domain
    $domainParts = explode('.', $domain);
    $domainName = $domainParts[0];
    $tld = implode('.', array_slice($domainParts, 1));
    $domainLen = strlen($domainName);
    
    $maskedDomain = ($domainLen <= 2) 
        ? $domainName[0] . '***' 
        : $domainName[0] . str_repeat('*', max(1, $domainLen - 2)) . $domainName[$domainLen - 1];

    return $maskedUser . '@' . $maskedDomain . '.' . $tld;
}

// GET: Fetch masked email for a specific username
if ($method === 'GET') {
    $username = $_GET['username'] ?? 'admin';

    $stmt = $pdo->prepare("SELECT email FROM users WHERE username = :username OR role = 'admin' LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user || empty($user['email'])) {
        http_response_code(404);
        echo json_encode(["error" => "No email found for this account. Please contact system admin."]);
        exit;
    }

    echo json_encode([
        "username" => $username,
        "masked_email" => maskEmail($user['email']),
        "has_email" => true
    ]);
    exit;
}

// POST: Generate reset link OR execute password reset
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? 'request_reset';

    // ACTION 1: Request Reset Link
    if ($action === 'request_reset') {
        $username = $data['username'] ?? 'admin';

        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE username = :username OR role = 'admin' LIMIT 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if (!$user || empty($user['email'])) {
            http_response_code(404);
            echo json_encode(["error" => "User account or email not configured."]);
            exit;
        }

        // Generate 64-character token valid for 1 hour
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Delete old tokens for this user
        $delStmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
        $delStmt->execute([':user_id' => $user['id']]);

        // Save token
        $insStmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
        $insStmt->execute([
            ':user_id'    => $user['id'],
            ':token'      => $token,
            ':expires_at' => $expiresAt
        ]);

        $resetLink = "http://localhost:8000/reset_password.html?token=" . $token;

        // In production, integrate PHPMailer / mail() to send $resetLink to $user['email']
        
        echo json_encode([
            "message" => "Reset link generated successfully!",
            "masked_email" => maskEmail($user['email']),
            "reset_link_dev" => $resetLink // Returned for local testing
        ]);
        exit;
    }

    // ACTION 2: Perform Password Reset
    if ($action === 'reset_password') {
        $token = $data['token'] ?? null;
        $newPassword = $data['new_password'] ?? null;

        if (!$token || !$newPassword) {
            http_response_code(400);
            echo json_encode(["error" => "Token and new password are required."]);
            exit;
        }

        // Validate token
        $stmt = $pdo->prepare("
            SELECT user_id FROM password_resets 
            WHERE token = :token AND expires_at > NOW() 
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid or expired reset token."]);
            exit;
        }

        // Update User Password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
        $updateStmt->execute([
            ':password' => $hashedPassword,
            ':id'       => $reset['user_id']
        ]);

        // Consume Token
        $delStmt = $pdo->prepare("DELETE FROM password_resets WHERE token = :token");
        $delStmt->execute([':token' => $token]);

        echo json_encode(["message" => "Password reset successfully! You can now log in."]);
        exit;
    }
}
?>