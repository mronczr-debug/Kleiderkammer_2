<?php
require 'db.php';

$stmt = $pdo->prepare("
  INSERT INTO mitarbeiter (vorname, nachname, abteilung, kleidergroesse, schuhgroesse, eintrittsdatum, typ)
  VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
  $_POST['vorname'],
  $_POST['nachname'],
  $_POST['abteilung'],
  $_POST['kleidergroesse'],
  $_POST['schuhgroesse'],
  $_POST['eintrittsdatum'],
  $_POST['typ']
]);

echo json_encode(['success' => true]);
