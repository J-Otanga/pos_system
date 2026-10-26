<?php
// api/auth.php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/db.php';


$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// GET active session check
if ($method === 'GET' && $action === 'me') {
    if (isset($_SESSION['user'])) {
        echo json_encode(["authenticated" => true, "user" => $_SESSION['user']]);
    } else {
        http_response_code(401);
        echo json_encode(["authenticated" => false, "error" => "Not logged in"]);
    }
    exit();
}

// GET logout
if ($method === 'GET' && $action === 'logout') {
    session_destroy();
    echo json_encode(["status" => "success", "message" => "Logged out successfully"]);
    exit();
}

// POST Actions
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // Register User
    if ($action === 'register') {
        $fullName = trim($input['full_name'] ?? '');
        $username = trim($input['username'] ?? '');
        $email    = trim($input['email'] ?? ($username . '@beautyshop.com'));
        $password = trim($input['password'] ?? '');
        $role     = trim($input['role'] ?? 'cashier');

        if (empty($fullName) || empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(["error" => "All fields are required."]);
            exit();
        }

        try {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $checkStmt->execute([$username, $email]);
            if ($checkStmt->fetch()) {
                http_response_code(400);
                echo json_encode(["error" => "Username or email already exists."]);
                exit();
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, username, email, role, password_hash) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$fullName, $username, $email, $role, $hashedPassword]);

            echo json_encode(["status" => "success", "message" => "Account created successfully!"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => "Database error: " . $e->getMessage()]);
        }
        exit();
    }

    // Login User
    if ($action === 'login') {
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(["error" => "Please enter both username and password."]);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user'] = [
                    'id'        => $user['id'],
                    'full_name' => $user['full_name'],
                    'username'  => $user['username'],
                    'email'     => $user['email'],
                    'role'      => $user['role']
                ];

                echo json_encode([
                    "status"  => "success",
                    "message" => "Login successful",
                    "user"    => $_SESSION['user']
                ]);
            } else {
                http_response_code(401);
                echo json_encode(["error" => "Invalid username or password."]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => "Database error: " . $e->getMessage()]);
        }
        exit();
    }
}

http_response_code(400);
echo json_encode(["error" => "Invalid action or request method"]);
?>