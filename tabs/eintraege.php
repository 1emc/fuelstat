<?php
// tabs/eintraege.php – modernes Card-Layout mit Dark Theme

include_once '../includes/db_connect.php';
include_once '../includes/functions.php';

$jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : null;

$yearStmt = $conn->prepare("SELECT DISTINCT YEAR(datum) AS jahr FROM eintraege WHERE fahrzeug_id = ? ORDER BY jahr DESC");
$yearStmt->bind_param("i", $fahrzeug_id);
$yearStmt->execute();
$yearRes = $yearStmt->get_result();
$jahrListe = array_column($yearRes->fetch_all(MYSQLI_ASSOC), 'jahr');
$yearStmt->close();

$sql = $jahr
    ? "SELECT * FROM eintraege WHERE fahrzeug_id = ? AND YEAR(datum) = ? ORDER BY datum DESC, tachostand DESC"
    : "SELECT * FROM eintraege WHERE fahrzeug_id = ? ORDER BY datum DESC, tachostand DESC";
$stmt = $conn->prepare($sql);
$jahr ? $stmt->bind_param("ii", $fahrzeug_id, $jahr) : $stmt->bind_param("i", $fahrzeug_id);
$stmt->execute();
$res = $stmt->get_result();

$eintraege = [];
while ($row = $res->fetch_assoc()) {
    $key = date('Y-m', strtotime($row['datum']));
    $eintraege[$key][] = $row;
}
$stmt->close();

$farben = [
    'Tankfuellung' => 'primary','Versicherung'=>'success','Werkstatt'=>'warning',
    'Inspektion'=>'info','Reparatur'=>'danger','Reifen'=>'secondary',
    'TUV'=>'dark','Wartung'=>'warning','Dekor'=>'info','Verbrauch'=>'secondary'];
?>

<style>
body.dark {
    background-color: #1e1e1e;
    color: #f1f1f1;
}
body.dark .card {
    background-color: #2b2b2b;
    border: none;
    color: #fff;
}
body.dark .btn {
    border: none;
}
body.dark .badge {
    background-color: #444;
    color: #fff;
}
.card {
    border-radius: 1rem;
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);
}
</style>

<div class="mt-4">
  <div class="d-flex flex-column flex-sm-row align-items-stretch gap-2 mb-3">
    <a href="eintrag_hinzufuegen.php?fahrzeug_id=<?= $fahrzeug_id ?>" class="btn btn-success flex-shrink-0">
      <i class="fas fa-plus me-1"></i> Neuer&nbsp;Eintrag
    </a>
    <form id="jahrFilterForm" method="get" class="d-flex flex-sm-row flex-column gap-2 flex-grow-1">
      <input type="hidden" name="id" value="<?= $fahrzeug_id ?>">
      <select name="jahr" class="form-select w-auto">
        <option value="">Alle Jahre</option>
        <?php foreach ($jahrListe as $y): ?>
        <option value="<?= $y ?>" <?= $jahr===$y?'selected':'' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-outline-secondary flex-shrink-0">
        <i class="fas fa-eye me-1"></i> Anzeigen
      </button>
    </form>
  </div>

  <?php if (!$eintraege): ?>
    <div class="alert alert-warning">Keine Einträge vorhanden.</div>
  <?php else: ?>
    <?php foreach ($eintraege as $monat => $entries): ?>
      <h5 class="mt-4 text-light-emphasis"><?= date('F Y', strtotime($monat.'-01')) ?></h5>
      <?php foreach ($entries as $e): ?>
        <?php
        if ($e['kategorie']==='Tankfuellung') {
          $v = berechneVerbrauchEintrag($e,$fahrzeug_id);
          $prev = getPreviousVerbrauch($e,$fahrzeug_id);
          $trend = ($prev!==null&&$v!==null)?($v>$prev?'up':($v<$prev?'down':'neutral')):'neutral';
        } else { $v=null; $trend='neutral'; }
        $farbe = $farben[$e['kategorie']] ?? 'secondary';
        $icon = getCategoryIcon($e['kategorie']);
        ?>

        <div class="card mb-3 px-3 py-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fs-6 fw-semibold mb-1">
                <i class="fas <?= $icon ?> me-2 text-<?= $farbe ?>"></i>
                <?= htmlspecialchars($e['kategorie']) ?>
              </div>
              <div class="text-muted small">
                <?= number_format($e['tachostand'],0,',','.') ?> km<?= $e['standort'] ? ' · '.htmlspecialchars($e['standort']) : '' ?>
              </div>
              <?php if ($v !== null && $v !== 'zwischen'): ?>
              <div class="text-info small mt-1">
                <i class="fas fa-tint me-1"></i>
                <?= number_format($e['menge'],2,',','.') ?> l ·
                <?= number_format($v,2,',','.') ?> l/100km
                <?php if ($trend==='up'): ?><i class="fas fa-arrow-up text-danger"></i><?php elseif ($trend==='down'): ?><i class="fas fa-arrow-down text-success"></i><?php endif; ?>
              </div>
              <?php endif; ?>
            </div>
            <div class="text-end">
              <div class="badge rounded-pill bg-<?= $farbe ?> text-white px-3 py-2">
                <?= number_format($e['kosten']??0,2,',','.') ?> €
              </div>
              <div class="mt-2">
                <a href="eintrag_bearbeiten.php?id=<?= $e['id'] ?>&fahrzeug_id=<?= $fahrzeug_id ?>" class="btn btn-sm btn-outline-success me-1">
                  <i class="fas fa-pen"></i>
                </a>
                <form method="post" action="eintrag_loeschen.php" class="d-inline-block" onsubmit="return confirm('Eintrag vom <?= date('d.m.Y',strtotime($e['datum'])) ?> löschen?');">
                  <input type="hidden" name="id" value="<?= $e['id'] ?>">
                  <input type="hidden" name="fahrzeug_id" value="<?= $fahrzeug_id ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
