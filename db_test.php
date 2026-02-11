<?php
$configs = [
    ['host' => '127.0.0.1', 'port' => 3306],
    ['host' => 'localhost', 'port' => 3306],
    ['host' => '::1', 'port' => 3306],
];

echo "Testing Database Connections...\n";

foreach ($configs as $config) {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname=studysprint";
    echo "Trying DSN: $dsn ... ";
    try {
        $pdo = new PDO($dsn, 'root', '');
        echo "SUCCESS!\n";
    } catch (PDOException $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}
