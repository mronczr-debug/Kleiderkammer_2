<?php
require 'db.php';

$pdo->beginTransaction();

try {
  // 1. Kopf speichern
  $stmt = $pdo->prepare("INSERT INTO eingang (datum, lieferant) VALUES (?, ?)");
  $stmt->execute([$_POST['datum'], $_POST['lieferant']]);
  $eingang_id = $pdo->lastInsertId();

  // 2. Positionen speichern + Bestand erhöhen
  foreach ($_POST['artikel_id'] as $i => $artikel_id) {
    $groesse = $_POST['groesse'][$i];
    $menge = $_POST['menge'][$i];

    $stmt2 = $pdo->prepare("
      INSERT INTO eingang_position (eingang_id, kleidung_id, groesse, menge)
      VALUES (?, ?, ?, ?)
    ");
    $stmt2->execute([$eingang_id, $artikel_id, $groesse, $menge]);

    // Bestand erhöhen
    $stmt3 = $pdo->prepare("UPDATE kleidung SET bestand = bestand + ? WHERE id = ?");
    $stmt3->execute([$menge, $artikel_id]);
  }

  $pdo->commit();
  echo json_encode(['success' => true]);

} catch (Exception $e) {
  $pdo->rollBack();
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
