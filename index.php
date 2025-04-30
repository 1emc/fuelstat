<?php
// index.php – Startseite
session_start();
include 'includes/db_connect.php';
include 'includes/functions.php';
include 'includes/header.php';

// ----- Flash-Message ---------------------------------------------------
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success mt-4 text-center">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}

$benutzer_id = 1; // TODO: ersetzen, sobald Login-System steht

$sql  = 'SELECT * FROM fahrzeuge WHERE benutzer_id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $benutzer_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
    /* --------------------------------------------------------------
       Hover‑Effekt, ohne dass die Karte ihre Größe verändert.
       Das Bild zoomt ein wenig, bleibt aber durch overflow:hidden
       innerhalb des Containers. Die Karte selbst hebt sich per
       Schatten + leichter Y‑Translation ab.                       */
    .card {
        transition: transform .25s ease, box-shadow .25s ease;
    }
    .card:hover {
        transform: translateY(-4px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
    }
    .card .zoom-img {
        transition: transform .4s ease;
    }
    .card:hover .zoom-img {
        transform: scale(1.05);
    }

    /* Kleinere Badge für Tachostand */
    .km-badge {
        font-size: .75rem;
        font-weight: 500;
        padding: .25em .5em;
        border-radius: .25rem;
    }
</style>

<h1>Fahrzeugübersicht</h1>

<div class="container mt-4">
    <?php if ($result->num_rows > 0): ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php while ($row = $result->fetch_assoc()):
                // -------------------------------------------------- Bildpfad
                $bildDatei = !empty($row['bild']) ? $row['bild'] : 'platzhalter.jpg';
                $bildPfad   = 'images/' . htmlspecialchars($bildDatei);
                // WebP-Variante (falls vorhanden)
                $bildWebp   = preg_replace('/\.[a-zA-Z0-9]+$/', '.webp', $bildPfad);
                $hasWebp    = file_exists($bildWebp);

                // ------------------------------------------------ Tachostand
                $aktTachostand = getAktuellenTachostand($row['id']);
                if ($aktTachostand === null) {
                    $aktTachostand = $row['tachostand'];
                }

                // ------------------------------------------------ Quick‑Stats (Ø Verbrauch aktuell)
                $verbrauch = berechneAktuellenVerbrauch($row['id']);
                $tooltip   = $verbrauch !== null
                    ? 'Ø Verbrauch: ' . number_format($verbrauch, 2, ',', '.') . ' l/100 km'
                    : 'Noch keine Verbrauchsdaten';
            ?>

            <div class="col">
                <div class="card h-100 shadow-sm border-0"
                     data-bs-toggle="tooltip" data-bs-placement="top" title="<?= htmlspecialchars($tooltip) ?>">

                    <!-- Bild-Wrapper + Tachostand‑Badge -->
                    <div class="position-relative overflow-hidden">
                        <div class="ratio ratio-16x9">
                            <picture>
                                <?php if ($hasWebp): ?>
                                    <source srcset="<?= $bildWebp ?>" type="image/webp">
                                <?php endif; ?>
                                <img src="<?= $bildPfad ?>" class="w-100 h-100 object-fit-cover zoom-img" alt="Bild von <?= htmlspecialchars($row['marke'] . ' ' . $row['modell']) ?>" loading="lazy">
                            </picture>
                        </div>
                        <span class="badge bg-secondary km-badge position-absolute top-0 end-0 m-2">
                            <?= number_format($aktTachostand, 0, ',', '.') ?> km
                        </span>
                    </div>

                    <!-- Titel -->
                    <div class="card-body pb-0">
                        <h5 class="card-title mb-2 fw-semibold">
                            <?= htmlspecialchars($row['marke'] . ' ' . $row['modell']) ?>
                        </h5>
                    </div>

                    <!-- Aktionen -->
                    <div class="card-footer bg-white border-0 pt-0 pb-3">
                        <div class="btn-group w-100">
                            <a href="pages/fahrzeug_detail.php?id=<?= intval($row['id']) ?>" class="btn btn-outline-primary">
                                <i class="fas fa-info-circle me-1"></i> Details
                            </a>
                            <a href="pages/eintrag_hinzufuegen.php?fahrzeug_id=<?= intval($row['id']) ?>" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">Keine Fahrzeuge gefunden.</div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Bootstrap‑Tooltip initialisieren
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>
sd