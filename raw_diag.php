<?php
// raw_diag.php

$user = 'root';
$pass = ''; // Blank password as per .env
$host = '127.0.0.1';
$port = '3306';
$db   = 'studysprint';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
    $emails = ['cherniranym@gmail.com', 'dimassimeriem1512@gmail.com'];
    
    foreach ($emails as $email) {
        $stmt = $pdo->prepare("SELECT id, email, reset_token, reset_token_expires_at FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            printf("--- USER: %s ---\n", $row['email']);
            printf("ID: %d | TOKEN: %s | EXPIRES: %s\n", 
                $row['id'], 
                $row['reset_token'] ?? 'NULL',
                $row['reset_token_expires_at'] ?? 'NULL'
            );
        } else {
            echo "USER NOT FOUND: $email\n";
        }
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
