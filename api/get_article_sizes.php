<?php
require 'db.php';
header('Content-Type: application/json');

$artikelId = intval($_GET['artikel_id'] ?? 0);

$sql = "
  SELECT DISTINCT groesse
  FROM eingang_position
  WHERE kleidung_id = :id
  UNION
  SELECT DISTINCT groesse
  FROM ausgabe_position
  WHERE kleidung_id = :id
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $artikelId]);

echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
