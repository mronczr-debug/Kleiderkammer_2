<?php
require 'db.php';

$stmt = $pdo->prepare("
  UPDATE kleidung SET 
    bezeichnung = ?, 
    groesse = ?, 
    gruppe = ?
  WHERE id = ?
");

$stmt->execute([
  $_POST['bezeichnung'],
  $_POST['groesse'],
  $_POST['gruppe'],
  $_POST['id']
]);

echo json_encode(['success' => true]);
