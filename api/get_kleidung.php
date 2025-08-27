<?php
require 'db.php';

$stmt = $pdo->query("SELECT * FROM kleidung ORDER BY bezeichnung");
$kleidung = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($kleidung);
