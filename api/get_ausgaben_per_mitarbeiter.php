<?php
require 'db.php';

$sql = "
SELECT m.id AS mitarbeiter_id, m.vorname, m.nachname,
  a.id AS ausgabe_id, a.datum,
  k.bezeichnung, ap.groesse, ap.menge
FROM ausgabe a
JOIN mitarbeiter m ON m.id = a.mitarbeiter_id
JOIN ausgabe_position ap ON ap.ausgabe_id = a.id
JOIN kleidung k ON k.id = ap.kleidung_id
ORDER BY m.nachname, m.vorname, a.datum DESC
";
$data = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($data);
