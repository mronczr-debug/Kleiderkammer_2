<?php
function layout_header(string $title): void {
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header>
    <h1>HSN Kleiderkammer</h1>
  </header>
  <div class="layout">
    <nav>
      <ul>
        <li><a href="index.html">Home</a></li>
        <li><a href="materials.php">Materialien</a></li>
      </ul>
    </nav>
    <main>
<?php
}

function layout_footer(): void {
?>
    </main>
  </div>
</body>
</html>
<?php
}
?>
