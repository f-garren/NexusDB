<?php
require_once 'config.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$db = getDB();

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $db->prepare("SELECT id, name, phone, city, state FROM customers WHERE name LIKE ? OR phone LIKE ? ORDER BY name LIMIT 10");
$search_term = "%$query%";
$stmt->execute([$search_term, $search_term]);
$results = $stmt->fetchAll();

echo json_encode($results);

