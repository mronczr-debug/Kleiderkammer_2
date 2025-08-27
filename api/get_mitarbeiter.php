<?php
require 'db.php';

$stmt = $pdo->query("SELECT * FROM mitarbeiter ORDER BY nachname");
$mitarbeiter = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($mitarbeiter);
