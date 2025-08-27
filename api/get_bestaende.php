<?php
require 'db.php';
header('Content-Type: application/json');

$sql = "
  SELECT 
    k.id AS artikel_id,
    k.bezeichnung,
    COALESCE(e.groesse, a.groesse) AS groesse,
    ISNULL(SUM(e.menge), 0) AS eingang,
    ISNULL(SUM(a.menge), 0) AS ausgang,
    ISNULL(SUM(e.menge), 0) - ISNULL(SUM(a.menge), 0) AS bestand
  FROM kleidung k
  LEFT JOIN eingang_position e ON e.kleidung_id = k.id
  LEFT JOIN ausgabe_position a ON a.kleidung_id = k.id AND a.groesse = e.groesse
  GROUP BY k.id, k.bezeichnung, COALESCE(e.groesse, a.groesse)
  ORDER BY k.bezeichnung, groesse
";

$stmt = $pdo->query($sql);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
