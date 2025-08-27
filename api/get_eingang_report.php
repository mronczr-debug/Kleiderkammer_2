<?php
require 'db.php';

$stmt = $pdo->query("
  SELECT 
    e.id AS eingang_id,
    e.datum,
    e.lieferant,
    kp.id AS position_id,
    k.bezeichnung,
    kp.groesse,
    kp.menge
  FROM eingang e
    JOIN eingang_position kp ON kp.eingang_id = e.id
    JOIN kleidung k ON k.id = kp.kleidung_id
  ORDER BY e.datum DESC, e.id, kp.id
");

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($data);
