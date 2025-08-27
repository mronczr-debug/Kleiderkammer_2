<?php
require 'db.php';

$stmt = $pdo->query("
  SELECT 
    k.bezeichnung,
    ep.groesse,
    SUM(ep.menge) AS bestand
  FROM kleidung k
  JOIN eingang_position ep ON ep.kleidung_id = k.id
  GROUP BY k.bezeichnung, ep.groesse
  ORDER BY k.bezeichnung, ep.groesse
");

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="bestaende_export.csv"');

$out = fopen('php://output', 'w');
// CSV header row
fputcsv($out, ['Bezeichnung', 'Größe', 'Bestand'], ';');

// Daten
foreach ($data as $row) {
    fputcsv($out, [$row['bezeichnung'], $row['groesse'], $row['bestand']], ';');
}

fclose($out);
exit;
