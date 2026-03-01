<?php
require_once __DIR__ . '/vendor/autoload.php';
use App\Service\ContentSanitizer;

$sanitizer = new ContentSanitizer();
$input = '<a href="javascript:alert(1)" onclick="bad()">Click me</a>';
$output = $sanitizer->sanitizeRich($input);
echo "DEBUG_START\n";
echo "[" . $output . "]\n";
echo "DEBUG_END\n";
