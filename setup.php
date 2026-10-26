<?php
require_once __DIR__ . '/config/db.php';

try {
    // Create products table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id VARCHAR(50) PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            brand VARCHAR(100) NOT NULL,
            category VARCHAR(100) NOT NULL,
            buying_price NUMERIC(10, 2) NOT NULL,
            selling_price NUMERIC(10, 2) NOT NULL,
            stock INT DEFAULT 0,
            alert_level INT DEFAULT 3,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Create sales table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sales (
            id SERIAL PRIMARY KEY,
            description VARCHAR(255) NOT NULL,
            amount NUMERIC(10, 2) NOT NULL,
            method VARCHAR(50) NOT NULL,
            date VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    echo "✅ Database tables successfully created in PostgreSQL!";
} catch (PDOException $e) {
    echo "❌ Error creating tables: " . $e->getMessage();
}
?>