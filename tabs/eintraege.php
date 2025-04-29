<?php
// tabs/eintraege.php – modernes Card‑Layout

include_once '../includes/db_connect.php';
include_once '../includes/functions.php';

// Fahrzeug‑ID & optionaler Jahres‑Filter
$jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : null;

// 1) verfügbare Jahre
$yearStmt = $conn->prepare("SELECT DISTINCT YEAR(datum) AS jahr FROM eintraege WHERE fahrzeug_id = ? ORDER BY jahr DESC");
$yearStmt->bind_param("i", $fahrzeug_id);
$yearStmt->execute();
$yearRes = $yearStmt->get_result();
$jahrListe = array_column($yearRes->fetch_all(MYSQLI_ASSOC), 'jahr');
$yearStmt->close();

// 2) Einträge abrufen
$sql = $jahr
    ? "SELECT * FROM eintraege WHERE fahrzeug_id = ? AND YEAR(datum) = ? ORDER BY datum DESC"
    : "SELECT * FROM eintraege WHERE fahrzeug_id = ? ORDER BY datum DESC";
$stmt = $conn->prepare($sql);
$jahr ? $stmt->bind_param("ii", $fahrzeug_id, $jahr)
      : $stmt->bind_param("i",  $fahrzeug_id);
$stmt->execute();
$res = $stmt->get_result();

// 3) Gruppieren nach Monat
$eintraege = [];
while ($row = $res->fetch_assoc()) {
    $key = date('Y-m', strtotime($row['datum']));
    $eintraege[$key][] = $row;
}
$stmt->close();

// Farbpalette
$farben = [
    'Tankfuellung'=>'primary','Versicherung'=>'success','Werkstatt'=>'warning',
    'Inspektion'=>'info','Reparatur'=>'danger','Reifen'=>'secondary',
    'TUV'=>'dark','Wartung'=>'warning','Dekor'=>'info','Verbrauch'=>'secondary'];
?>

<style>
  /* Deaktiviert Hover‑Animation (z. B. Anheben/Scale) für Karten in dieser Ansicht */
  .card:hover {
    transform: none !important;
    box-shadow: var(--bs-card-box-shadow, 0 .125rem .25rem rgba(0,0,0,.075)) !important;
  }
  .card {
    transition: none !important; /* auch Soft-Fade/Scale ausschalten */
  }
</style>

<div class="mt-4">
    <!-- Toolbar -->
	<div class="d-flex flex-column flex-sm-row align-items-stretch gap-2 mb-3">

		<!-- 1) Neuer-Eintrag-Button -->
		<a href="eintrag_hinzufuegen.php?fahrzeug_id=<?= $fahrzeug_id ?>"
		   class="btn btn-success flex-shrink-0">
			<i class="fas fa-plus me-1"></i>
			Neuer&nbsp;Eintrag
		</a>

		<!-- 2 + 3) Jahr-Dropdown + Anzeigen-Button -->
		<form id="jahrFilterForm"
			  method="get"
			  class="d-flex flex-sm-row flex-column gap-2 flex-grow-1">
			<input type="hidden" name="id" value="<?= $fahrzeug_id ?>">

			<!-- Jahr-Liste -->
			<select name="jahr"
					class="form-select w-auto">
				<option value="">Alle Jahre</option>
				<?php foreach ($jahrListe as $y): ?>
					<option value="<?= $y ?>" <?= $jahr===$y?'selected':'' ?>>
						<?= $y ?>
					</option>
				<?php endforeach; ?>
			</select>

			<!-- Anzeigen-Button -->
			<button class="btn btn-outline-secondary flex-shrink-0">
				<i class="fas fa-eye me-1"></i>
				Anzeigen
			</button>
		</form>
	</div>


    <?php if (!$eintraege): ?>
        <div class="alert alert-warning">Keine Einträge vorhanden.</div>
    <?php else: ?>
        <?php foreach ($eintraege as $monat => $entries): ?>
            <h5 class="mt-4"><?= date('F Y', strtotime($monat.'-01')) ?></h5>

            <?php foreach ($entries as $e): ?>
                <?php
                    // Verbrauch & Trend
                    if ($e['kategorie']==='Tankfuellung') {
                        $v      = berechneVerbrauchEintrag($e,$fahrzeug_id);
                        $prev   = getPreviousVerbrauch($e,$fahrzeug_id);
                        $trend  = ($prev!==null&&$v!==null)?($v>$prev?'up':($v<$prev?'down':'neutral')):'neutral';
                    } else { $v=null; $trend='neutral'; }

                    $farbe = $farben[$e['kategorie']] ?? 'secondary';
                    $icon  = getCategoryIcon($e['kategorie']);
                ?>
				
                <div class="card mb-3 position-relative">
                    <!-- edit/delete -->
                    <div class="position-absolute top-0 end-0 m-2 d-flex">
						<small class="text-muted pe-2"></small>
                        <a href="eintrag_bearbeiten.php?id=<?= $e['id'] ?>&fahrzeug_id=<?= $fahrzeug_id ?>" class="btn btn-secondary btn-sm me-1"><i class="fas fa-pen"></i></a>
                        <form method="post" action="eintrag_loeschen.php" onsubmit="return confirm('Eintrag vom <?= date('d.m.Y',strtotime($e['datum'])) ?> löschen?');">
                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                            <input type="hidden" name="fahrzeug_id" value="<?= $fahrzeug_id ?>">
                            <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                    <!-- Kosten-Badge unten rechts -->
                    <span class="badge rounded-pill bg-<?= $farbe ?> position-absolute bottom-0 end-0 m-2">
                        <?= number_format($e['kosten']??0,2,',','.') ?> €
                    </span>

                    <div class="card-body p-3">
                        <div class="d-flex">
                            <!-- Icon-Kreis -->
                            <div class="me-3">
                                <span class="d-inline-flex justify-content-center align-items-center bg-<?= $farbe ?> text-white rounded-circle" style="width:42px;height:42px;">
                                    <i class="fas <?= $icon ?> text-white"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <h6 class="mb-1"><?= htmlspecialchars($e['kategorie']) ?></h6>
                                </div>
                                <small class="text-muted">
                                    <?= number_format($e['tachostand'],0,',','.') ?> km<?= $e['standort']? ' · '.htmlspecialchars($e['standort']):'' ?>
                                </small><br>
                                <?php if($v!==null&&$v!=='zwischen'): ?>
                                    <small>
                                        <i class="fas fa-tint text-<?= $farbe ?>"></i>
                                        <?= number_format($e['menge'],2,',','.') ?> l ·
                                        <?= number_format($v,2,',','.') ?> l/100 km
                                        <?php if($trend==='up'): ?><i class="fas fa-arrow-up text-danger"></i><?php elseif($trend==='down'): ?><i class="fas fa-arrow-down text-success"></i><?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
