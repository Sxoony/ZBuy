<?php

$host     = 'localhost';
$port     = 3306;          // Default MySQL port
$dbname   = 'itecadb';    // Your database name (as shown in phpMyAdmin)
$username = 'root';        // Default XAMPP username
$password = '';            // Default XAMPP password is empty

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
 //echo "Connected to local MySQL successfully!";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// $stmt = $pdo->query("SELECT * FROM users");
// $rows = $stmt->fetchAll();
// print_r($rows);
?>
