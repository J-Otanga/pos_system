<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database Connection Credentials
$host = "localhost";
$port = "5432";
$dbname = "beauty_shop"; // Your PostgreSQL DB name
$user = "postgres";      // Your PostgreSQL Username
$password = "1234"; // Your PostgreSQL Password

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// 1. GET: Fetch all active debts (remaining balance > 0)
if ($method === 'GET') {
    try {
        $stmt = $pdo->query("SELECT * FROM debts WHERE amount > 0 ORDER BY id DESC");
        $debts = $stmt->fetchAll();
        echo json_encode($debts);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

// 2. POST: Create a new debt entry (from record_sale.html)
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $customerName = $input['customer_name'] ?? $input['name'] ?? 'Unknown Customer';
    $phone = $input['phone'] ?? $input['customer_phone'] ?? 'N/A';
    $item = $input['item_description'] ?? $input['product_purchased'] ?? 'General Sale';
    $amount = floatval($input['amount'] ?? 0);
    $originalAmount = floatval($input['original_amount'] ?? $amount);
    $deposit = floatval($input['deposit'] ?? 0);
    $date = $input['date'] ?? date('Y-m-d');

    try {
        $stmt = $pdo->prepare("
            INSERT INTO debts (customer_name, phone, item_description, amount, original_amount, deposit, date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$customerName, $phone, $item, $amount, $originalAmount, $deposit, $date]);

        echo json_encode(["success" => true, "message" => "Debt record created"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

// 3. PUT: Apply a partial payment or clear full balance (from debts.html)
elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? $input['debt_id'] ?? null;
    $paymentAmount = floatval($input['payment_amount'] ?? $input['amount_paid'] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing debt ID parameter"]);
        exit();
    }

    try {
        // Deduct remaining balance and increment cumulative deposit
        $stmt = $pdo->prepare("
            UPDATE debts 
            SET amount = GREATEST(0, amount - ?), 
                deposit = deposit + ? 
            WHERE id = ?
        ");
        $stmt->execute([$paymentAmount, $paymentAmount, $id]);

        echo json_encode(["success" => true, "message" => "Payment recorded successfully"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}