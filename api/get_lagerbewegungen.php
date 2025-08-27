<?php
require 'db.php';
$stmt = $pdo->query("
  SELECT lb.*, k.bezeichnung, k.groesse
  FROM lagerbewegungen lb
  JOIN kleidung k ON lb.kleidung_id = k.id
  ORDER BY lb.datum DESC
");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
