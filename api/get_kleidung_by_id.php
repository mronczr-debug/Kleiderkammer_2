<?php
require 'db.php';

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM kleidung WHERE id = ?");
$stmt->execute([$id]);

header('Content-Type: application/json');
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
