<?php
$serverName = getenv('DB_SERVER');
$database   = getenv('DB_DATABASE');
$username   = getenv('DB_USERNAME');
$password   = getenv('DB_PASSWORD');

if (!$serverName || !$database || !$username || !$password) {
    die('❌ Datenbankzugangsdaten fehlen (Umgebungsvariablen setzen).');
}

try {
    $pdo = new PDO("sqlsrv:Server=$serverName;Database=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Verbindung erfolgreich
} catch (PDOException $e) {
    die("❌ Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}
