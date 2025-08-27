<?php
// variant_create.php
declare(strict_types=1);
session_start();

/* ====== DB CONFIG ====== */
$dsn  = 'sqlsrv:Server=NAUSWIASPSQL01;Database=Arbeitskleidung'; // z.B. tcp:NAUSWIASPSQL01,1433
$user = 'HSN_DB1';
$pass = 'HSNdb1';

/* ====== Helpers ====== */
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_check(string $t): bool { return hash_equals($_SESSION['csrf'] ?? '', $t); }
function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

/* ====== DB Connect ====== */
try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE         => PDO::ERRMODE_EXCEPTION,
    PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
  ]);
} catch (Throwable $e) { http_response_code(500); exit('DB-Verbindung fehlgeschlagen: '.e($e->getMessage())); }

/* ====== Input: material_id ====== */
$materialId = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;
if ($materialId <= 0) { http_response_code(400); exit('material_id fehlt.'); }

/* ====== Lookup: Material, Gruppe, Profil ====== */
$sqlMat = "SELECT m.MaterialID, m.MaterialName, m.Beschreibung, m.BasisSKU, m.IsActive,
                  mg.MaterialgruppeID, mg.Gruppenname
           FROM dbo.Material m
           JOIN dbo.Materialgruppe mg ON mg.MaterialgruppeID = m.MaterialgruppeID
           WHERE m.MaterialID = :id";
$st = $pdo->prepare($sqlMat); $st->execute([':id'=>$materialId]); $material = $st->fetch(PDO::FETCH_ASSOC);
if (!$material) { http_response_code(404); exit('Material nicht gefunden.'); }

/* ====== Merkmal-IDs (Farbe, Größe) ====== */
$attrStmt = $pdo->prepare("SELECT MerkmalID, MerkmalName FROM dbo.MatAttribute WHERE MerkmalName IN (N'Farbe', N'Größe')");
$attrStmt->execute();
$attrMap = [];
while ($r = $attrStmt->fetch(PDO::FETCH_ASSOC)) { $attrMap[$r['MerkmalName']] = (int)$r['MerkmalID']; }
$merkmalIdFarbe = $attrMap['Farbe'] ?? null;
$merkmalIdGroesse = $attrMap['Größe'] ?? null;

/* ====== Allowed Values: Farbe ====== */
$allowedColors = [];
if ($merkmalIdFarbe) {
  $st = $pdo->prepare("SELECT AllowedValue, SortOrder FROM dbo.MatAttributeAllowedValues WHERE MerkmalID = :id ORDER BY SortOrder, AllowedValue");
  $st->execute([':id'=>$merkmalIdFarbe]);
  $allowedColors = $st->fetchAll(PDO::FETCH_COLUMN, 0);
}

/* ====== Allowed Values: Größe je Gruppe (über View) ====== */
$allowedSizes = [];
$st = $pdo->prepare("SELECT Groesse FROM dbo.vAllowed_Groessen_ByGruppe WHERE MaterialgruppeID = :gid ORDER BY SortOrder, Groesse");
$st->execute([':gid'=>$material['MaterialgruppeID']]);
$allowedSizes = $st->fetchAll(PDO::FETCH_COLUMN, 0);
$groupHasSizeProfile = count($allowedSizes) > 0;

/* ====== Dynamische weiteren Attribute laden (optional) ====== */
/* Wir zeigen alle Attribute außer Farbe/Größe. Falls AllowedValues vorhanden → Dropdown, sonst Text. */
$attrAll = $pdo->query("
  SELECT a.MerkmalID, a.MerkmalName, a.Datentyp,
         (SELECT STRING_AGG(av.AllowedValue, '||') WITHIN GROUP (ORDER BY av.SortOrder, av.AllowedValue)
            FROM dbo.MatAttributeAllowedValues av WHERE av.MerkmalID = a.MerkmalID) AS AllowedValuesConcat
  FROM dbo.MatAttribute a
  WHERE a.MerkmalName NOT IN (N'Farbe', N'Größe')
  ORDER BY a.MerkmalName
")->fetchAll(PDO::FETCH_ASSOC);

/* ====== Form-Values ====== */
$values = [
  'VarianteName' => $_POST['VarianteName'] ?? '',
  'SKU'          => $_POST['SKU'] ?? '',
  'Barcode'      => $_POST['Barcode'] ?? '',
  'IsActive'     => isset($_POST['IsActive']) ? '1' : '0',
  'Farbe'        => $_POST['Farbe'] ?? '',
  'Groesse'      => $_POST['Groesse'] ?? '',
  'csrf'         => $_POST['csrf'] ?? '',
];
/* weitere Attribute Werte einsammeln */
$otherAttrValues = []; // [MerkmalID => value]
foreach ($attrAll as $a) {
  $key = 'attr_' . (int)$a['MerkmalID'];
  if (isset($_POST[$key])) {
    $otherAttrValues[(int)$a['MerkmalID']] = trim((string)$_POST[$key]);
  }
}

$errors = [];

/* ====== Handle POST ====== */
if (is_post()) {
  if (!csrf_check($values['csrf'])) { $errors['csrf'] = 'Sicherheits-Token ungültig. Bitte Seite neu laden.'; }
  if (trim($values['VarianteName']) === '') { $errors['VarianteName'] = 'Bitte Variantenbezeichnung angeben.'; }
  if (mb_strlen($values['VarianteName']) > 200) { $errors['VarianteName'] = 'Max. 200 Zeichen.'; }
  if ($values['SKU'] !== '' && mb_strlen($values['SKU']) > 100) { $errors['SKU'] = 'SKU max. 100 Zeichen.'; }
  if ($values['Barcode'] !== '' && mb_strlen($values['Barcode']) > 100) { $errors['Barcode'] = 'Barcode max. 100 Zeichen.'; }

  // Validierung Farbe (wenn AllowedValues gepflegt sind, muss Auswahl valide sein – sonst optional)
  if ($merkmalIdFarbe && $values['Farbe'] !== '' && !in_array($values['Farbe'], $allowedColors, true)) {
    $errors['Farbe'] = 'Ungültige Farbe.';
  }

  // Validierung Größe (nur wenn Gruppe ein Größenprofil hat)
  if ($groupHasSizeProfile) {
    if ($values['Groesse'] === '') {
      $errors['Groesse'] = 'Bitte Größe wählen.';
    } elseif (!in_array($values['Groesse'], $allowedSizes, true)) {
      $errors['Groesse'] = 'Ungültige Größe.';
    }
  }

  // weitere Attribute: wenn AllowedValues vorhanden, ebenfalls prüfen
  foreach ($attrAll as $a) {
    $mid = (int)$a['MerkmalID'];
    $k = 'attr_'.$mid;
    if (!array_key_exists($mid, $otherAttrValues)) continue;
    $val = $otherAttrValues[$mid];
    if ($val === '') continue; // optional
    if ($a['AllowedValuesConcat']) {
      $opts = explode('||', (string)$a['AllowedValuesConcat']);
      if (!in_array($val, $opts, true)) {
        $errors[$k] = 'Ungültiger Wert für „'.$a['MerkmalName'].'“.';
      }
    } else {
      // Datentyp grob prüfen
      if ($a['Datentyp'] === 'INT' && !is_numeric($val)) { $errors[$k] = 'Zahl erwartet.'; }
      if ($a['Datentyp'] === 'DECIMAL' && !is_numeric($val)) { $errors[$k] = 'Dezimalzahl erwartet.'; }
      if ($a['Datentyp'] === 'BOOL' && !in_array(strtolower($val), ['0','1','false','true'], true)) { $errors[$k] = 'Bool (0/1) erwartet.'; }
      if ($a['Datentyp'] === 'DATE' && (date_create($val) === false)) { $errors[$k] = 'Datum erwartet (YYYY-MM-DD).'; }
    }
  }

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // Variante anlegen
      $sqlVar = "INSERT INTO dbo.MatVarianten (MaterialID, VariantenBezeichnung, SKU, Barcode, IsActive, CreatedAt)
                 VALUES (:mid, :name, :sku, :barcode, :act, SYSUTCDATETIME());
                 SELECT SCOPE_IDENTITY() AS NewVarID;";
      $st = $pdo->prepare($sqlVar);
      $st->execute([
        ':mid'    => $materialId,
        ':name'   => $values['VarianteName'],
        ':sku'    => ($values['SKU'] === '' ? null : $values['SKU']),
        ':barcode'=> ($values['Barcode'] === '' ? null : $values['Barcode']),
        ':act'    => ($values['IsActive'] === '1' ? 1 : 0),
      ]);
      $varianteId = (int)$st->fetch(PDO::FETCH_ASSOC)['NewVarID'];

      // Attribute schreiben (Farbe/Größe + weitere)
      $insAttr = $pdo->prepare("
        INSERT INTO dbo.MatVariantenAttribute (VarianteID, MerkmalID, MerkmalWert)
        VALUES (:vid, :mid, :val)
      ");

      if ($merkmalIdFarbe && $values['Farbe'] !== '') {
        $insAttr->execute([':vid'=>$varianteId, ':mid'=>$merkmalIdFarbe, ':val'=>$values['Farbe']]);
      }
      if ($merkmalIdGroesse && $groupHasSizeProfile && $values['Groesse'] !== '') {
        $insAttr->execute([':vid'=>$varianteId, ':mid'=>$merkmalIdGroesse, ':val'=>$values['Groesse']]);
      }
      // weitere Attribute
      foreach ($otherAttrValues as $mid => $val) {
        if ($val === '') continue;
        $insAttr->execute([':vid'=>$varianteId, ':mid'=>$mid, ':val'=>$val]);
      }

      $pdo->commit();

      // Nach Speichern: Formular leeren & Erfolg melden
      $_SESSION['flash_success'] = 'Variante wurde angelegt.';
      header('Location: variant_create.php?material_id='.$materialId);
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors['_'] = 'Speichern fehlgeschlagen: '.$e->getMessage();
    }
  }
}

/* ====== Bestehende Varianten laden ====== */
$existing = $pdo->prepare("
  SELECT v.VarianteID, v.VariantenBezeichnung, v.SKU, v.Barcode, v.IsActive,
         MAX(CASE WHEN a.MerkmalName = N'Farbe' THEN va.MerkmalWert END) AS Farbe,
         MAX(CASE WHEN a.MerkmalName = N'Größe' THEN va.MerkmalWert END) AS Groesse
  FROM dbo.MatVarianten v
  LEFT JOIN dbo.MatVariantenAttribute va ON va.VarianteID = v.VarianteID
  LEFT JOIN dbo.MatAttribute a ON a.MerkmalID = va.MerkmalID
  WHERE v.MaterialID = :mid
  GROUP BY v.VarianteID, v.VariantenBezeichnung, v.SKU, v.Barcode, v.IsActive
  ORDER BY v.VarianteID DESC
");
$existing->execute([':mid'=>$materialId]);
$variants = $existing->fetchAll(PDO::FETCH_ASSOC);

/* ====== UI ====== */

require __DIR__.'/layout.php';
layout_header('Variante anlegen – '.$material['MaterialName']);
?>
  <div class="card">
    <h1>Variante anlegen</h1>
    <div class="subtitle">
      <strong>Material:</strong> <?= e($material['MaterialName']) ?>
      <span class="badge"><?= e($material['Gruppenname']) ?></span>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="alert alert-success"><?= e($_SESSION['flash_success']) ?></div>
      <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
    <?php if (!empty($errors['_'])): ?>
      <div class="alert alert-error"><?= e($errors['_']) ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="row">
        <div class="field">
          <label for="VarianteName">Variantenbezeichnung *</label>
          <input type="text" id="VarianteName" name="VarianteName" maxlength="200" required
                 value="<?= e($values['VarianteName']) ?>">
          <?php if (!empty($errors['VarianteName'])): ?><div class="error"><?= e($errors['VarianteName']) ?></div><?php endif; ?>
          <div class="hint">z. B. „Schwarz, 52“ oder „Rot, XL“</div>
        </div>

        <div class="field">
          <label for="SKU">SKU / Artikelnummer</label>
          <input type="text" id="SKU" name="SKU" maxlength="100" value="<?= e($values['SKU']) ?>">
          <?php if (!empty($errors['SKU'])): ?><div class="error"><?= e($errors['SKU']) ?></div><?php endif; ?>
        </div>
      </div>

      <div class="row">
        <div class="field">
          <label for="Barcode">Barcode</label>
          <input type="text" id="Barcode" name="Barcode" maxlength="100" value="<?= e($values['Barcode']) ?>">
          <?php if (!empty($errors['Barcode'])): ?><div class="error"><?= e($errors['Barcode']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label><input type="checkbox" name="IsActive" value="1" <?= ($values['IsActive']==='1'?'checked':'') ?>> Aktiv</label>
        </div>
      </div>

      <div class="row">
        <div class="field">
          <label for="Farbe">Farbe</label>
          <select id="Farbe" name="Farbe">
            <option value="">— bitte wählen —</option>
            <?php foreach ($allowedColors as $c): ?>
              <option value="<?= e($c) ?>" <?= ($values['Farbe'] === $c ? 'selected' : '') ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (!empty($errors['Farbe'])): ?><div class="error"><?= e($errors['Farbe']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="Groesse">Größe<?= $groupHasSizeProfile ? ' *' : '' ?></label>
          <?php if ($groupHasSizeProfile): ?>
            <select id="Groesse" name="Groesse" <?= $groupHasSizeProfile ? 'required' : '' ?>>
              <option value="">— bitte wählen —</option>
              <?php foreach ($allowedSizes as $s): ?>
                <option value="<?= e($s) ?>" <?= ($values['Groesse'] === $s ? 'selected' : '') ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="text" id="Groesse" name="Groesse" value="<?= e($values['Groesse']) ?>" placeholder="(keine Pflicht)">
          <?php endif; ?>
          <?php if (!empty($errors['Groesse'])): ?><div class="error"><?= e($errors['Groesse']) ?></div><?php endif; ?>
          <div class="hint">
            Größen kommen aus dem Profil der Gruppe „<?= e($material['Gruppenname']) ?>“.
          </div>
        </div>
      </div>

      <?php if (!empty($attrAll)): ?>
        <div class="row-1">
          <div class="field">
            <label>Weitere Merkmale</label>
            <div class="hint">Optional. Falls „Allowed Values“ gepflegt sind, werden Auswahllisten angezeigt.</div>
          </div>
        </div>
        <div class="row-3">
          <?php foreach ($attrAll as $a):
            $mid = (int)$a['MerkmalID']; $k='attr_'.$mid; $val = $otherAttrValues[$mid] ?? '';
            $opts = $a['AllowedValuesConcat'] ? explode('||', (string)$a['AllowedValuesConcat']) : [];
          ?>
            <div class="field">
              <label for="<?= e($k) ?>"><?= e($a['MerkmalName']) ?></label>
              <?php if (!empty($opts)): ?>
                <select id="<?= e($k) ?>" name="<?= e($k) ?>">
                  <option value="">— bitte wählen —</option>
                  <?php foreach ($opts as $o): ?>
                    <option value="<?= e($o) ?>" <?= ($val===$o?'selected':'') ?>><?= e($o) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <input type="text" id="<?= e($k) ?>" name="<?= e($k) ?>" value="<?= e($val) ?>">
              <?php endif; ?>
              <?php if (!empty($errors[$k])): ?><div class="error"><?= e($errors[$k]) ?></div><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="actions">
        <button class="btn btn-primary" type="submit">Variante speichern</button>
        <a class="btn btn-secondary" href="material_create.php">Neues Material</a>
      </div>
    </form>

    <h2 style="margin-top:2rem;font-size:1.15rem;">Vorhandene Varianten</h2>
    <?php if (empty($variants)): ?>
      <p class="hint">Noch keine Varianten vorhanden.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th><th>Bezeichnung</th><th>Farbe</th><th>Größe</th><th>SKU</th><th>Barcode</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($variants as $v): ?>
            <tr>
              <td><?= (int)$v['VarianteID'] ?></td>
              <td><?= e($v['VariantenBezeichnung']) ?></td>
              <td><?= e($v['Farbe'] ?? '') ?></td>
              <td><?= e($v['Groesse'] ?? '') ?></td>
              <td><?= e($v['SKU'] ?? '') ?></td>
              <td><?= e($v['Barcode'] ?? '') ?></td>
              <td><?= ($v['IsActive'] ? '<span class="pill on">aktiv</span>' : '<span class="pill off">inaktiv</span>') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <div style="margin-top:1rem">
      <a class="btn btn-secondary" href="materials_list.php">Zurück zur Materialliste</a>
    </div>
  </div>
<?php layout_footer(); ?>
