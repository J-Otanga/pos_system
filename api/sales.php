<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Auto-migrate database schema for customer tracking and balances
try {
    $pdo->exec("ALTER TABLE sales ADD COLUMN IF NOT EXISTS total_amount NUMERIC(10,2)");
    $pdo->exec("ALTER TABLE sales ADD COLUMN IF NOT EXISTS amount_paid NUMERIC(10,2)");
    $pdo->exec("ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_name VARCHAR(255)");
} catch (Exception $e) {
    // Schema up to date
}

// GET /api/sales.php
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM sales ORDER BY id DESC");
    $sales = $stmt->fetchAll();
    echo json_encode($sales);
    exit;
}

// POST /api/sales.php
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $description  = $data['description'] ?? null;
    $totalAmount  = $data['total_amount'] ?? $data['amount'] ?? null;
    $amountPaid   = $data['amount_paid'] ?? $totalAmount;
    $saleMethod   = $data['method'] ?? 'cash';
    $customerName = $data['customer_name'] ?? null;

    if (!$description || $totalAmount === null) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required sale fields"]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO sales (description, amount, total_amount, amount_paid, method, customer_name, date)
            VALUES (:description, :amount, :total_amount, :amount_paid, :method, :customer_name, :date)
        ");
        $stmt->execute([
            ':description'   => $description,
            ':amount'        => $totalAmount,
            ':total_amount'  => $totalAmount,
            ':amount_paid'   => $amountPaid,
            ':method'        => $saleMethod,
            ':customer_name' => $customerName,
            ':date'          => date('Y-m-d H:i:s')
        ]);

        if (!empty($data['product_id']) && !empty($data['quantity'])) {
            $stockStmt = $pdo->prepare("
                UPDATE products SET stock = stock - :quantity WHERE id = :id AND stock >= :quantity
            ");
            $stockStmt->execute([
                ':quantity' => (int)$data['quantity'],
                ':id'       => $data['product_id']
            ]);

            if ($stockStmt->rowCount() === 0) {
                throw new Exception("Insufficient stock available or product ID not found.");
            }
        }

        $pdo->commit();
        http_response_code(201);
        echo json_encode(["message" => "Sale recorded successfully"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(["error" => $e->getMessage()]);
    }
    exit;
}
?>