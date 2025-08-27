<?php
require 'db.php';
$pdo->beginTransaction();

$stmt = $pdo->prepare("INSERT INTO lagerbewegungen (kleidung_id, typ, menge, bemerkung) VALUES (?, ?, ?, ?)");
$stmt->execute([
  $_POST['kleidung_id'],
  $_POST['typ'], // 'eingang' oder 'ausgang'
  $_POST['menge'],
  $_POST['bemerkung'] ?? null
]);

// Bestand anpassen
if ($_POST['typ'] === 'eingang') {
  $stmt2 = $pdo->prepare("UPDATE kleidung SET bestand = bestand + ? WHERE id = ?");
} else {
  $stmt2 = $pdo->prepare("UPDATE kleidung SET bestand = bestand - ? WHERE id = ?");
}
$stmt2->execute([$_POST['menge'], $_POST['kleidung_id']]);

$pdo->commit();
