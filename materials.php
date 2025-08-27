<?php
// materials.php
declare(strict_types=1);
session_start();

/* ========= DB CONFIG ========= */
$dsn  = 'sqlsrv:Server=NAUSWIASPSQL01;Database=Arbeitskleidung'; // z.B. tcp:NAUSWIASPSQL01,1433
$user = 'HSN_DB1';
$pass = 'HSNdb1';

/* ========= Helpers ========= */
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_check(string $t): bool { return hash_equals($_SESSION['csrf'] ?? '', $t); }
function flash(string $key, ?string $msg=null): ?string {
  if ($msg !== null) { $_SESSION['flash_'.$key] = $msg; return null; }
  $val = $_SESSION['flash_'.$key] ?? null; unset($_SESSION['flash_'.$key]); return $val;
}

/* ========= Connect ========= */
try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE         => PDO::ERRMODE_EXCEPTION,
    PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
  ]);
} catch (Throwable $e) { http_response_code(500); exit('DB-Verbindung fehlgeschlagen: '.e($e->getMessage())); }

/* ========= Lookups ========= */
$hersteller = $pdo->query("SELECT HerstellerID, Name FROM dbo.Hersteller ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
$gruppen    = $pdo->query("SELECT MaterialgruppeID, Gruppenname FROM dbo.Materialgruppe ORDER BY Gruppenname")->fetchAll(PDO::FETCH_ASSOC);

/* ========= Create-Submit ========= */
$createErrors = [];
if (($_POST['action'] ?? '') === 'create') {
  $val = [
    'csrf' => $_POST['csrf'] ?? '',
    'MaterialName' => trim((string)($_POST['MaterialName'] ?? '')),
    'Beschreibung' => trim((string)($_POST['Beschreibung'] ?? '')),
    'HerstellerID' => $_POST['HerstellerID'] ?? '',
    'MaterialgruppeID' => $_POST['MaterialgruppeID'] ?? '',
    'BasisSKU' => trim((string)($_POST['BasisSKU'] ?? '')),
    'IsActive' => isset($_POST['IsActive']) ? '1' : '0',
  ];
  if (!csrf_check($val['csrf'])) $createErrors['csrf'] = 'Sicherheits-Token ungültig.';
  if ($val['MaterialName'] === '') $createErrors['MaterialName'] = 'Bitte Materialname angeben.';
  if ($val['MaterialgruppeID'] === '' || !ctype_digit((string)$val['MaterialgruppeID'])) $createErrors['MaterialgruppeID'] = 'Bitte eine Materialgruppe wählen.';
  if ($val['HerstellerID'] !== '' && !ctype_digit((string)$val['HerstellerID'])) $createErrors['HerstellerID'] = 'Ungültiger Hersteller.';
  if (mb_strlen($val['MaterialName']) > 200) $createErrors['MaterialName'] = 'Max. 200 Zeichen.';
  if ($val['BasisSKU'] !== '' && mb_strlen($val['BasisSKU']) > 100) $createErrors['BasisSKU'] = 'BasisSKU max. 100 Zeichen.';

  if (!$createErrors) {
    try {
      $pdo->beginTransaction();
      $stmt = $pdo->prepare("
        INSERT INTO dbo.Material (MaterialName, Beschreibung, HerstellerID, MaterialgruppeID, BasisSKU, IsActive, CreatedAt)
        VALUES (:MaterialName, :Beschreibung, :HerstellerID, :MaterialgruppeID, :BasisSKU, :IsActive, SYSUTCDATETIME());
        SELECT SCOPE_IDENTITY() AS NewID;
      ");
      $stmt->execute([
        ':MaterialName' => $val['MaterialName'],
        ':Beschreibung' => ($val['Beschreibung'] === '' ? null : $val['Beschreibung']),
        ':HerstellerID' => ($val['HerstellerID'] === '' ? null : (int)$val['HerstellerID']),
        ':MaterialgruppeID' => (int)$val['MaterialgruppeID'],
        ':BasisSKU' => ($val['BasisSKU'] === '' ? null : $val['BasisSKU']),
        ':IsActive' => ($val['IsActive'] === '1' ? 1 : 0),
      ]);
      $newId = (int)$stmt->fetch(PDO::FETCH_ASSOC)['NewID'];
      $pdo->commit();
      flash('success', 'Material wurde angelegt.');
      header('Location: materials.php#row-'.$newId);
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $createErrors['_'] = 'Speichern fehlgeschlagen: '.$e->getMessage();
    }
  }
}

/* ========= Filter & Paging ========= */
$q        = trim((string)($_GET['q'] ?? ''));
$grp      = $_GET['grp'] ?? '';
$man      = $_GET['man'] ?? '';
$active   = $_GET['active'] ?? ''; // '', '1', '0'
$page     = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 15;
$offset   = ($page-1) * $pageSize;

$where = [];
$params = [];
if ($q !== '') {
  $where[] = "(m.MaterialName LIKE :qs OR m.Beschreibung LIKE :qs OR ISNULL(h.Name,'') LIKE :qs)";
  $params[':qs'] = '%'.$q.'%';
}
if ($grp !== '' && ctype_digit((string)$grp)) {
  $where[] = "m.MaterialgruppeID = :grp";
  $params[':grp'] = (int)$grp;
}
if ($man !== '' && ctype_digit((string)$man)) {
  $where[] = "m.HerstellerID = :man";
  $params[':man'] = (int)$man;
}
if ($active === '0' || $active === '1') {
  $where[] = "m.IsActive = :act";
  $params[':act'] = (int)$active;
}
$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* Count */
$stmtCnt = $pdo->prepare("
  SELECT COUNT(*) AS cnt
  FROM dbo.Material m
  LEFT JOIN dbo.Hersteller h ON h.HerstellerID = m.HerstellerID
  $whereSql
");
$stmtCnt->execute($params);
$total = (int)$stmtCnt->fetch(PDO::FETCH_ASSOC)['cnt'];
$pages = max(1, (int)ceil($total / $pageSize));

/* Rows */
$sqlRows = "
  WITH base AS (
    SELECT
      m.MaterialID, m.MaterialName, m.Beschreibung, m.BasisSKU, m.IsActive,
      h.Name AS HerstellerName,
      g.Gruppenname AS Materialgruppe,
      (SELECT COUNT(*) FROM dbo.MatVarianten v WHERE v.MaterialID = m.MaterialID) AS VariantenCount
    FROM dbo.Material m
    LEFT JOIN dbo.Hersteller h     ON h.HerstellerID = m.HerstellerID
    LEFT JOIN dbo.Materialgruppe g ON g.MaterialgruppeID = m.MaterialgruppeID
    $whereSql
  )
  SELECT * FROM base
  ORDER BY MaterialName ASC
  OFFSET :off ROWS FETCH NEXT :ps ROWS ONLY;
";
$stmt = $pdo->prepare($sqlRows);
foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->bindValue(':ps', $pageSize, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ========= UI ========= */
$flashSuccess = flash('success');

require __DIR__.'/layout.php';
layout_header('Materialien – Übersicht & Anlage');
?>
  <div class="split">
    <!-- Liste -->
    <div class="card">
      <h1>Materialien</h1>

      <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><?= e($flashSuccess) ?></div>
      <?php endif; ?>

      <form method="get" class="filters">
        <div>
          <label for="q">Suche</label>
          <input type="text" id="q" name="q" placeholder="Name, Beschreibung, Hersteller..." value="<?= e($q) ?>">
        </div>
        <div>
          <label for="grp">Gruppe</label>
          <select id="grp" name="grp">
            <option value="">— alle —</option>
            <?php foreach ($gruppen as $g): ?>
              <option value="<?= (int)$g['MaterialgruppeID'] ?>" <?= ($grp!==''
                && (int)$grp===(int)$g['MaterialgruppeID']?'selected':'') ?>><?= e($g['Gruppenname']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="man">Hersteller</label>
          <select id="man" name="man">
            <option value="">— alle —</option>
            <?php foreach ($hersteller as $h): ?>
              <option value="<?= (int)$h['HerstellerID'] ?>" <?= ($man!==''
                && (int)$man===(int)$h['HerstellerID']?'selected':'') ?>><?= e($h['Name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="active">Status</label>
          <select id="active" name="active">
            <option value="">— alle —</option>
            <option value="1" <?= ($active==='1'?'selected':'') ?>>aktiv</option>
            <option value="0" <?= ($active==='0'?'selected':'') ?>>inaktiv</option>
          </select>
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end">
          <button class="btn btn-primary" type="submit">Filtern</button>
          <a class="btn btn-secondary" href="materials.php">Zurücksetzen</a>
        </div>
      </form>

      <div class="hint" style="margin-bottom:8px">
        <?= $total ?> Treffer • Seite <?= $page ?> von <?= $pages ?>
      </div>

      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Gruppe</th>
            <th>Hersteller</th>
            <th class="muted">SKU</th>
            <th>Varianten</th>
            <th>Status</th>
            <th>Aktion</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="muted">Keine Materialien gefunden.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr id="row-<?= (int)$r['MaterialID'] ?>">
                <td><?= (int)$r['MaterialID'] ?></td>
                <td>
                  <div><strong><?= e($r['MaterialName']) ?></strong></div>
                  <?php if (!empty($r['Beschreibung'])): ?><div class="muted"><?= e($r['Beschreibung']) ?></div><?php endif; ?>
                </td>
                <td><?= e($r['Materialgruppe'] ?? '') ?></td>
                <td><?= e($r['HerstellerName'] ?? '') ?></td>
                <td class="muted"><?= e($r['BasisSKU'] ?? '') ?></td>
                <td><?= (int)$r['VariantenCount'] ?></td>
                <td><?= ($r['IsActive'] ? '<span class="pill on">aktiv</span>' : '<span class="pill off">inaktiv</span>') ?></td>
                <td>
                  <a class="btn btn-secondary" href="variant_create.php?material_id=<?= (int)$r['MaterialID'] ?>">Varianten</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if ($pages > 1): ?>
        <div class="pagination">
          <?php
            $qs = function(array $overrides=[]) use($q,$grp,$man,$active){ 
              return http_build_query(array_merge(['q'=>$q,'grp'=>$grp,'man'=>$man,'active'=>$active],$overrides)); 
            };
          ?>
          <a class="btn btn-secondary" href="?<?= $qs(['page'=>1]) ?>" <?= $page==1?'style="opacity:.5;pointer-events:none"':'' ?>>« Erste</a>
          <a class="btn btn-secondary" href="?<?= $qs(['page'=>max(1,$page-1)]) ?>" <?= $page==1?'style="opacity:.5;pointer-events:none"':'' ?>>‹ Zurück</a>
          <span class="muted">Seite <?= $page ?>/<?= $pages ?></span>
          <a class="btn btn-secondary" href="?<?= $qs(['page'=>min($pages,$page+1)]) ?>" <?= $page==$pages?'style="opacity:.5;pointer-events:none"':'' ?>>Weiter ›</a>
          <a class="btn btn-secondary" href="?<?= $qs(['page'=>$pages]) ?>" <?= $page==$pages?'style="opacity:.5;pointer-events:none"':'' ?>>Letzte »</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Anlage -->
    <div class="card">
      <h2>Neues Material anlegen</h2>
      <?php if (!empty($createErrors['_'])): ?>
        <div class="alert alert-error"><?= e($createErrors['_']) ?></div>
      <?php endif; ?>
      <form method="post" class="grid1">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">

        <div class="grid2">
          <div>
            <label for="MaterialName">Materialname *</label>
            <input type="text" id="MaterialName" name="MaterialName" maxlength="200" required value="<?= e($_POST['MaterialName'] ?? '') ?>">
            <?php if (!empty($createErrors['MaterialName'])): ?><div class="alert alert-error"><?= e($createErrors['MaterialName']) ?></div><?php endif; ?>
          </div>
          <div>
            <label for="BasisSKU">Basis-SKU</label>
            <input type="text" id="BasisSKU" name="BasisSKU" maxlength="100" value="<?= e($_POST['BasisSKU'] ?? '') ?>">
            <?php if (!empty($createErrors['BasisSKU'])): ?><div class="alert alert-error"><?= e($createErrors['BasisSKU']) ?></div><?php endif; ?>
          </div>
        </div>

        <div class="grid2">
          <div>
            <label for="HerstellerID">Hersteller</label>
            <select id="HerstellerID" name="HerstellerID">
              <option value="">— bitte wählen —</option>
              <?php foreach ($hersteller as $h): ?>
                <option value="<?= (int)$h['HerstellerID'] ?>" <?= (($_POST['HerstellerID'] ?? '')==$h['HerstellerID']?'selected':'') ?>><?= e($h['Name']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (!empty($createErrors['HerstellerID'])): ?><div class="alert alert-error"><?= e($createErrors['HerstellerID']) ?></div><?php endif; ?>
            <div class="hint">Fehlt der Hersteller? <a href="hersteller_create.php">Neu anlegen</a></div>
          </div>
          <div>
            <label for="MaterialgruppeID">Materialgruppe *</label>
            <select id="MaterialgruppeID" name="MaterialgruppeID" required>
              <option value="">— bitte wählen —</option>
              <?php foreach ($gruppen as $g): ?>
                <option value="<?= (int)$g['MaterialgruppeID'] ?>" <?= (($_POST['MaterialgruppeID'] ?? '')==$g['MaterialgruppeID']?'selected':'') ?>><?= e($g['Gruppenname']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (!empty($createErrors['MaterialgruppeID'])): ?><div class="alert alert-error"><?= e($createErrors['MaterialgruppeID']) ?></div><?php endif; ?>
            <div class="hint">Die Gruppe steuert das Größenprofil (z. B. „… (DE)“ = 42–64).</div>
          </div>
        </div>

        <div>
          <label for="Beschreibung">Beschreibung</label>
          <textarea id="Beschreibung" name="Beschreibung"><?= e($_POST['Beschreibung'] ?? '') ?></textarea>
        </div>

        <div>
          <label><input type="checkbox" name="IsActive" value="1" <?= (isset($_POST['IsActive'])?'checked':'') ?>> Aktiv</label>
        </div>

        <?php if (!empty($createErrors['csrf'])): ?>
          <div class="alert alert-error"><?= e($createErrors['csrf']) ?></div>
        <?php endif; ?>

        <div style="display:flex;gap:8px;align-items:center">
          <button class="btn btn-primary" type="submit">Anlegen</button>
          <a class="btn btn-secondary" href="materials.php">Zurücksetzen</a>
        </div>
      </form>

      <div class="hint" style="margin-top:.6rem">
        Nach dem Anlegen kannst du über <em>„Varianten“</em> die Farbe/Größe & weitere Merkmale pflegen.
      </div>
    </div>
  </div>
<?php layout_footer(); ?>
