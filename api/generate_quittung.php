<?php
require 'db.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;

$ausgabe_id = $_GET['id'] ?? 0;

// Daten abrufen
$stmt = $pdo->prepare("
  SELECT a.id, a.datum, m.vorname, m.nachname, a.unterschrift
  FROM ausgabe a
  JOIN mitarbeiter m ON m.id = a.mitarbeiter_id
  WHERE a.id = ?
");
$stmt->execute([$ausgabe_id]);
$kopf = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
  SELECT k.bezeichnung, ap.groesse, ap.menge
  FROM ausgabe_position ap
  JOIN kleidung k ON k.id = ap.kleidung_id
  WHERE ap.ausgabe_id = ?
");
$stmt->execute([$ausgabe_id]);
$positionen = $stmt->fetchAll(PDO::FETCH_ASSOC);

// HTML aufbauen
$html = "
<h1>Quittung Ausgabe #{$kopf['id']}</h1>
<p><strong>Mitarbeiter:</strong> {$kopf['nachname']}, {$kopf['vorname']}</p>
<p><strong>Datum:</strong> {$kopf['datum']}</p>
<table border='1' cellpadding='5' cellspacing='0'>
  <tr><th>Artikel</th><th>Größe</th><th>Menge</th></tr>";
foreach ($positionen as $pos) {
  $html .= "<tr><td>{$pos['bezeichnung']}</td><td>{$pos['groesse']}</td><td>{$pos['menge']}</td></tr>";
}
$html .= "</table>";

if (!empty($kopf['unterschrift'])) {
  $html .= "<p><strong>Unterschrift:</strong><br><img src=\"{$kopf['unterschrift']}\" style=\"max-width:200px;\"></p>";
}

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("quittung_ausgabe_{$kopf['id']}.pdf", ["Attachment" => false]);
