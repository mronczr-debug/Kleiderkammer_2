<?php
require 'db.php';
header('Content-Type: application/json');

// Eingabedaten
$mitarbeiter_id = $_POST['mitarbeiter_id'] ?? null;
$datum = $_POST['datum'] ?? date("Y-m-d");
$unterschrift = $_POST['unterschrift'] ?? null;
$artikel_ids = $_POST['artikel_id'] ?? [];
$groessen = $_POST['groesse'] ?? [];
$mengen = $_POST['menge'] ?? [];

if (!$mitarbeiter_id || !$artikel_ids || count($artikel_ids) === 0) {
  http_response_code(400);
  echo json_encode(["error" => "Ungültige Eingabe"]);
  exit;
}

// Vorbereitung: Positionen durchgehen und Bestände prüfen
foreach ($artikel_ids as $i => $artikel_id) {
  $groesse = $groessen[$i];
  $menge = intval($mengen[$i]);

  $stmt = $pdo->prepare("
    SELECT 
      ISNULL(SUM(e.menge), 0) - ISNULL(SUM(a.menge), 0) AS bestand
    FROM kleidung k
    LEFT JOIN eingang_position e ON e.kleidung_id = k.id AND e.groesse = :groesse
    LEFT JOIN ausgabe_position a ON a.kleidung_id = k.id AND a.groesse = :groesse
    WHERE k.id = :artikel_id
  ");
  $stmt->execute([
    'artikel_id' => $artikel_id,
    'groesse' => $groesse
  ]);
  $bestand = intval($stmt->fetchColumn());

  if ($menge > $bestand) {
    http_response_code(400);
    echo json_encode([
      "error" => "Nicht genügend Bestand für Artikel ID $artikel_id, Größe '$groesse'. Aktuell: $bestand, benötigt: $menge"
    ]);
    exit;
  }
}

// Speichern der Ausgabe (Kopf)
$stmt = $pdo->prepare("
  INSERT INTO ausgabe (mitarbeiter_id, datum, unterschrift) 
  VALUES (:mitarbeiter_id, :datum, :unterschrift)
");
$stmt->execute([
  'mitarbeiter_id' => $mitarbeiter_id,
  'datum' => $datum,
  'unterschrift' => $unterschrift
]);
$ausgabe_id = $pdo->lastInsertId();

// Speichern der Positionen
$stmt = $pdo->prepare("
  INSERT INTO ausgabe_position (ausgabe_id, kleidung_id, groesse, menge)
  VALUES (:ausgabe_id, :kleidung_id, :groesse, :menge)
");

foreach ($artikel_ids as $i => $artikel_id) {
  $stmt->execute([
    'ausgabe_id' => $ausgabe_id,
    'kleidung_id' => $artikel_id,
    'groesse' => $groessen[$i],
    'menge' => intval($mengen[$i])
  ]);
}

echo json_encode(["success" => true, "ausgabe_id" => $ausgabe_id]);
