<?php
require 'db.php';

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM mitarbeiter WHERE id = ?");
$stmt->execute([$id]);
$datensatz = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($datensatz);
