<?php
require 'db.php';

$stmt = $pdo->prepare("
  INSERT INTO kleidung (bezeichnung, groesse, gruppe)
  VALUES (?, ?, ?)
");

$stmt->execute([
  $_POST['bezeichnung'],
  $_POST['groesse'],
  $_POST['gruppe']
]);

echo json_encode(['success' => true]);
