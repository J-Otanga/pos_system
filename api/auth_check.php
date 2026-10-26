<?php
// api/auth_check.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkAuth($requiredRole = null) {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized. Please log in."]);
        exit();
    }

    if ($requiredRole !== null) {
        $userRole = strtolower($_SESSION['user']['role'] ?? '');
        $targetRole = strtolower($requiredRole);

        if ($userRole !== $targetRole) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Forbidden. Admin privileges required."]);
            exit();
        }
    }
}
?>