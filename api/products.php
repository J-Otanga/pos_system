<?php
// api/products.php

header("Content-Type: application/json");
require_once "../config/db.php";

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Fetch all products matching your database schema
        $stmt = $pdo->query("SELECT id, name, brand, category, buying_price, selling_price, stock, alert_level, created_at FROM products ORDER BY created_at DESC");
        $products = $stmt->fetchAll();
        echo json_encode($products);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;

        $id = !empty($data['id']) ? trim($data['id']) : null;
        $name = trim($data['name'] ?? '');
        $brand = trim($data['brand'] ?? '');
        $category = trim($data['category'] ?? '');
        $buying_price = floatval($data['cost_price'] ?? $data['buying_price'] ?? 0);
        $selling_price = floatval($data['selling_price'] ?? 0);
        $stock = intval($data['stock_quantity'] ?? $data['stock'] ?? 0);
        $alert_level = intval($data['reorder_level'] ?? $data['alert_level'] ?? 5);

        if (empty($name) || empty($category)) {
            http_response_code(400);
            echo json_encode(["error" => "Product name and category are required."]);
            exit();
        }

        // Check if product exists for Update vs Insert
        $exists = false;
        if ($id) {
            $checkStmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $checkStmt->execute([$id]);
            $exists = (bool) $checkStmt->fetch();
        }

        if ($exists) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, brand = ?, category = ?, buying_price = ?, selling_price = ?, stock = ?, alert_level = ? 
                WHERE id = ?
            ");
            $stmt->execute([$name, $brand, $category, $buying_price, $selling_price, $stock, $alert_level, $id]);
            echo json_encode(["status" => "success", "message" => "Product updated successfully"]);
        } else {
            // Generate a unique varchar ID if none was passed
            if (!$id) {
                $id = 'PROD-' . strtoupper(substr(uniqid(), -8));
            }

            $stmt = $pdo->prepare("
                INSERT INTO products (id, name, brand, category, buying_price, selling_price, stock, alert_level) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id, $name, $brand, $category, $buying_price, $selling_price, $stock, $alert_level]);

            echo json_encode([
                "status" => "success", 
                "message" => "Product added successfully", 
                "id" => $id
            ]);
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(["error" => "Product ID is required for deletion."]);
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(["status" => "success", "message" => "Product deleted successfully"]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}