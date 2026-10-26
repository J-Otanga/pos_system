<?php
// ==========================================================================
// BEAUTY STORE POS - USER MANAGEMENT API
// File: api/users.php
// ==========================================================================

header("Content-Type: application/json");
require_once __DIR__ . '/auth_check.php';

// Guard: Admin access only
checkAuth('admin');

require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        // ------------------------------------------------------------------
        // GET: Fetch all user records
        // ------------------------------------------------------------------
        case 'GET':
            $stmt = $pdo->query("
                SELECT id, full_name, username, email, role, status, force_password_change, created_at 
                FROM users 
                ORDER BY id ASC
            ");
            $users = $stmt->fetchAll();
            echo json_encode(["status" => "success", "data" => $users]);
            break;

        // ------------------------------------------------------------------
        // POST: Create a new account
        // ------------------------------------------------------------------
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            $fullName = trim($input['full_name'] ?? '');
            $username = trim($input['username'] ?? '');
            $email    = trim($input['email'] ?? '');
            $role     = trim($input['role'] ?? 'cashier');
            $userStatus = trim($input['status'] ?? 'active');
            $password = trim($input['password'] ?? '');

            if (empty($fullName) || empty($username) || empty($email) || empty($password)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "All required fields must be filled out."]);
                exit();
            }

            // Check duplicate username/email
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $checkStmt->execute([$username, $email]);
            if ($checkStmt->fetch()) {
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "Username or Email is already taken."]);
                exit();
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, username, email, role, status, password_hash, force_password_change)
                VALUES (?, ?, ?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([$fullName, $username, $email, $role, $userStatus, $hashedPassword]);

            echo json_encode(["status" => "success", "message" => "Staff account created successfully."]);
            break;

        // ------------------------------------------------------------------
        // PUT: Update an existing account
        // ------------------------------------------------------------------
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);

            $id       = (int)($input['id'] ?? 0);
            $fullName = trim($input['full_name'] ?? '');
            $username = trim($input['username'] ?? '');
            $email    = trim($input['email'] ?? '');
            $role     = trim($input['role'] ?? 'cashier');
            $userStatus = trim($input['status'] ?? 'active');
            $password = trim($input['password'] ?? ''); // Optional password reset

            if (!$id || empty($fullName) || empty($username) || empty($email)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Invalid account payload."]);
                exit();
            }

            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, username = ?, email = ?, role = ?, status = ?, password_hash = ?, force_password_change = TRUE 
                    WHERE id = ?
                ");
                $stmt->execute([$fullName, $username, $email, $role, $userStatus, $hashedPassword, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, username = ?, email = ?, role = ?, status = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$fullName, $username, $email, $role, $userStatus, $id]);
            }

            echo json_encode(["status" => "success", "message" => "Account updated successfully."]);
            break;

        // ------------------------------------------------------------------
        // DELETE: Remove account
        // ------------------------------------------------------------------
        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);

            if (!$id) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Missing user ID."]);
                exit();
            }

            if ($id === (int)$_SESSION['user']['id']) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "You cannot delete your own logged-in admin account."]);
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(["status" => "success", "message" => "User deleted successfully."]);
            break;

        default:
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Method not allowed."]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>