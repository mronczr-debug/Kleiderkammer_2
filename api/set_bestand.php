<?php
require 'db.php';

$artikel_id = $_POST['artikel_id'];
$groesse = $_POST['groesse'];
$neuer_bestand = $_POST['neuer_bestand'];

// Bestand auf Zielwert setzen: 
// 1. Aktuellen Ist-Bestand berechnen
$stmt = $pdo->prepare("
  SELECT SUM(menge) AS ist_bestand 
  FROM eingang_position 
  WHERE kleidung_id = ? AND groesse = ?
");
$stmt->execute([$artikel_id, $groesse]);
$ist_bestand = (int) $stmt->fetchColumn();

// 2. Differenz ermitteln
$differenz = $neuer_bestand - $ist_bestand;
if ($differenz !== 0) {
  // 3. Korrekturposition als Wareneingang buchen (kann negativ sein)
  $stmt2 = $pdo->prepare("
    INSERT INTO eingang_position (eingang_id, kleidung_id, groesse, menge)
    VALUES (NULL, ?, ?, ?)
  ");
  $stmt2->execute([$artikel_id, $groesse, $differenz]);
}

echo json_encode(['success' => true]);
