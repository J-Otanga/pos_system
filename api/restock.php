<?php
// ==========================================================================
// BEAUTY STORE POS - RESTOCK API ENDPOINT
// File: api/restock.php
// ==========================================================================

header("Content-Type: application/json");
require_once "auth_check.php";

// Ensure user is logged in
checkAuth();

// Database configuration
$host = "localhost";
$port = "5432";
$dbname = "beautystore_db";
$user = "postgres";
$password = "your_pg_password";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// --- GET: FETCH RESTOCK AUDIT LOGS ---
if ($method === 'GET') {
    $query = "
        SELECT 
            r.id, 
            r.qty_added, 
            r.buying_price, 
            r.total_cost, 
            r.supplier_name, 
            r.notes, 
            r.created_at,
            p.name AS product_name,
            p.category,
            p.brand
        FROM restock_logs r
        JOIN products p ON r.product_id = p.id
        ORDER BY r.created_at DESC
        LIMIT 50
    ";
    
    $stmt = $pdo->query($query);
    $logs = $stmt->fetchAll();
    echo json_encode($logs);
    exit();
}

// --- POST: RECORD A NEW RESTOCK BATCH ---
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $productId    = $data['product_id'] ?? null;
    $qtyAdded     = (int)($data['qty_added'] ?? 0);
    $buyingPrice  = (float)($data['buying_price'] ?? 0.0);
    $supplierName = trim($data['supplier_name'] ?? '');
    $notes        = trim($data['notes'] ?? '');
    $userId       = $_SESSION['user_id'] ?? null;

    if (!$productId || $qtyAdded <= 0 || $buyingPrice < 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Valid product, quantity, and cost price are required."]);
        exit();
    }

    $totalCost = $qtyAdded * $buyingPrice;

    try {
        // Begin Transaction
        $pdo->beginTransaction();

        // 1. Update stock and wholesale cost price in products table
        $updateStmt = $pdo->prepare("
            UPDATE products 
            SET stock = stock + :qty, 
                buying_price = :buying_price 
            WHERE id = :id
        ");
        $updateStmt->execute([
            'qty' => $qtyAdded,
            'buying_price' => $buyingPrice,
            'id' => $productId
        ]);

        // 2. Insert record into audit logs
        $logStmt = $pdo->prepare("
            INSERT INTO restock_logs (product_id, qty_added, buying_price, total_cost, supplier_name, notes, restocked_by_user_id)
            VALUES (:product_id, :qty_added, :buying_price, :total_cost, :supplier_name, :notes, :user_id)
        ");
        $logStmt->execute([
            'product_id' => $productId,
            'qty_added' => $qtyAdded,
            'buying_price' => $buyingPrice,
            'total_cost' => $totalCost,
            'supplier_name' => $supplierName,
            'notes' => $notes,
            'user_id' => $userId
        ]);

        // Commit Transaction
        $pdo->commit();

        echo json_encode(["status" => "success", "message" => "Stock restocked successfully."]);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database transaction failed: " . $e->getMessage()]);
        exit();
    }
}