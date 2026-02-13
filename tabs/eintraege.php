<?php
include_once __DIR__ . '/../includes/db_connect.php';
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../includes/api_helpers.php';

$jahr = isset($_GET['jahr']) ? (int)$_GET['jahr'] : null;

$merged = getMergedEintraegeFromApi($api, $fahrzeug_id);
$jahrListe = $merged['jahrListe'];
$eintraegeRaw = $merged['eintraege'];

if ($jahr !== null) {
    $eintraege = [];
    foreach ($eintraegeRaw as $monat => $entries) {
        if ((int)substr($monat, 0, 4) === $jahr) {
            $eintraege[$monat] = $entries;
        }
    }
} else {
    $eintraege = $eintraegeRaw;
}

$farben = [
    'Tankfuellung' => 'primary',
    'Versicherung' => 'success',
    'Werkstatt'    => 'warning',
    'Inspektion'   => 'info',
    'Reparatur'    => 'danger',
    'Reifen'       => 'secondary',
    'TUV'          => 'dark',
    'Wartung'      => 'warning',
    'Dekor'        => 'info',
    'Verbrauch'    => 'secondary',
    'Fahrt'        => 'info',
];
?>

<style>
body.dark { background-color: #1e1e1e; color: #f1f1f1; }
body.dark .card { background-color: #2b2b2b; border: none; color: #fff; }
body.dark .btn { border: none; }
body.dark .badge { background-color: #444; color: #fff; }
.card { border-radius: 1rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15); }
</style>

<div class="mt-4">
  <div class="d-flex flex-column flex-sm-row align-items-stretch gap-2 mb-3">
    <a href="../pages/eintrag_hinzufuegen.php?fahrzeug_id=<?= htmlspecialchars(urlencode($fahrzeug_id)) ?>" class="btn btn-success flex-shrink-0">
      <i class="fas fa-plus me-1"></i> Neuer&nbsp;Eintrag
    </a>
    <form id="jahrFilterForm" method="get" class="d-flex flex-sm-row flex-column gap-2 flex-grow-1">
      <input type="hidden" name="id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
      <select name="jahr" class="form-select w-auto">
        <option value="">Alle Jahre</option>
        <?php foreach ($jahrListe as $y): ?>
        <option value="<?= $y ?>" <?= $jahr === $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-outline-secondary flex-shrink-0">
        <i class="fas fa-eye me-1"></i> Anzeigen
      </button>
    </form>
  </div>

  <?php if (empty($eintraege)): ?>
    <div class="alert alert-warning">Keine Einträge vorhanden.</div>
  <?php else: ?>
    <?php foreach ($eintraege as $monat => $entries): ?>
      <h5 class="mt-4 text-light-emphasis"><?= date('F Y', strtotime($monat . '-01')) ?></h5>
      <?php foreach ($entries as $e):
        $v = ($e['kategorie'] === 'Tankfuellung' && isset($e['l_per_100km'])) ? $e['l_per_100km'] : null;
        $farbe = $farben[$e['kategorie']] ?? 'secondary';
        $icon = getCategoryIcon($e['kategorie']);
        $type = $e['type'] ?? 'entry';
      ?>
        <div class="card mb-3 px-3 py-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fs-6 fw-semibold mb-1">
                <i class="fas <?= $icon ?> me-2 text-<?= $farbe ?>"></i>
                <?= htmlspecialchars(getKategorieName($e['kategorie'])) ?>
              </div>
              <div class="text-muted small">
                <?= number_format((int)($e['tachostand'] ?? 0), 0, ',', '.') ?> km<?= !empty($e['standort']) ? ' · ' . htmlspecialchars($e['standort']) : '' ?>
              </div>
              <?php if ($e['kategorie'] === 'Tankfuellung' && $v !== null): ?>
              <div class="text-info small mt-1">
                <i class="fas fa-tint me-1"></i>
                <?= number_format((float)($e['menge'] ?? 0), 2, ',', '.') ?> l ·
                <?= number_format($v, 2, ',', '.') ?> l/100km
              </div>
              <?php endif; ?>
            </div>
            <div class="text-end">
              <div class="badge rounded-pill bg-<?= $farbe ?> text-white px-3 py-2">
                <?= number_format((float)($e['kosten'] ?? 0), 2, ',', '.') ?> €
              </div>
              <div class="mt-2">
                <a href="../pages/eintrag_bearbeiten.php?id=<?= htmlspecialchars(urlencode($e['id'])) ?>&fahrzeug_id=<?= htmlspecialchars(urlencode($fahrzeug_id)) ?>&type=<?= $type ?>" class="btn btn-sm btn-outline-success me-1">
                  <i class="fas fa-pen"></i>
                </a>
                <form method="post" action="../pages/eintrag_loeschen.php" class="d-inline-block" onsubmit="return confirm('Eintrag vom <?= htmlspecialchars(date('d.m.Y', strtotime($e['datum']))) ?> löschen?');">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($e['id']) ?>">
                  <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
                  <input type="hidden" name="type" value="<?= $type ?>">
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
