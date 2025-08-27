<?php
$serverName = "NAUSWIASPSQL01"; // oder "localhost\\SQLEXPRESS" bei SQL Express
$database = "Arbeitskleidung";
$username = "HSN_DB1";
$password = "HSNdb1";

try {
    $pdo = new PDO("sqlsrv:Server=$serverName;Database=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Verbindung erfolgreich
} catch (PDOException $e) {
    die("❌ Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}
