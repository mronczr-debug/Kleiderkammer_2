<?php
require 'db.php';

$stmt = $pdo->prepare("
  UPDATE mitarbeiter SET 
    vorname = ?, 
    nachname = ?, 
    abteilung = ?, 
    kleidergroesse = ?, 
    schuhgroesse = ?, 
    eintrittsdatum = ?, 
    typ = ?
  WHERE id = ?
");

$stmt->execute([
  $_POST['vorname'],
  $_POST['nachname'],
  $_POST['abteilung'],
  $_POST['kleidergroesse'],
  $_POST['schuhgroesse'],
  $_POST['eintrittsdatum'],
  $_POST['typ'],
  $_POST['id']
]);

echo json_encode(['success' => true]);
