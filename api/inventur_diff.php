<?php
require 'db.php';

$updates = json_decode(file_get_contents('php://input'), true)['aktualisierungen'] ?? [];

foreach ($updates as $u) {
  $artikel = (int)$u['artikel_id'];
  $groesse = $u['groesse'];
  $tatsaechlich = (int)$u['tatsaechlich'];

  $stmt = $pdo->prepare("
    SELECT
      COALESCE((SELECT SUM(menge) FROM eingang_position WHERE kleidung_id = ? AND groesse = ?), 0) -
      COALESCE((SELECT SUM(menge) FROM ausgabe_position WHERE kleidung_id = ? AND groesse = ?), 0)
  ");
  $stmt->execute([$artikel, $groesse, $artikel, $groesse]);
  $ist = (int)$stmt->fetchColumn();

  $diff = $tatsaechlich - $ist;
  if ($diff !== 0) {
    $pdo->prepare("
      INSERT INTO eingang_position (eingang_id, kleidung_id, groesse, menge)
      VALUES (NULL, ?, ?, ?)
    ")->execute([$artikel, $groesse, $diff]);
  }
}

echo json_encode(['success' => true]);
