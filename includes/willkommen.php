<?php
// Startseite für nicht angemeldete Besucher (eingebunden von index.php nach header.php)

// Registrierung kann in der API abgeschaltet sein (REGISTRATION_ENABLED=false)
$registrierungOffen = true;
try {
    $registrierungOffen = (getApiClient(null)->getHealth()['registration_enabled'] ?? true) !== false;
} catch (Throwable $e) {
    // API nicht erreichbar: Links trotzdem anzeigen, die Seiten melden den Fehler selbst
}

$funktionen = [
    ['fa-gas-pump', 'Tanken & Laden', 'Tankfüllungen und Ladevorgänge in Sekunden erfassen – mit Tankstellen in der Nähe und aktuellen Spritpreisen.'],
    ['fa-chart-line', 'Verbrauch & Statistiken', 'Verbrauch pro Tankfüllung, Monat und Jahr, Trends und Diagramme für jedes Fahrzeug.'],
    ['fa-euro-sign', 'Alle Kosten im Blick', 'Versicherung, Werkstatt, Steuer, Reifen: alle Ausgaben, Kosten pro Kilometer und Fixkosten.'],
    ['fa-bell', 'Erinnerungen', 'Rechtzeitig an TÜV, Wartung, Versicherung und Steuer denken – auf Wunsch per E-Mail.'],
    ['fa-leaf', 'CO₂-Bilanz', 'Wie viel CO₂ dein Fahrzeug ausstößt, insgesamt, pro Kilometer und pro Jahr.'],
    ['fa-file-import', 'Import & Export', 'Daten aus Excel oder anderen Apps übernehmen und jederzeit als CSV oder PDF-Bericht mitnehmen.'],
];
?>
<style>
    .willkommen-hero { background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), .12), rgba(var(--bs-success-rgb), .10)); }
    .willkommen-logo { width: 96px; height: 96px; border-radius: 22px; box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .15); }
    .willkommen-icon { width: 3rem; height: 3rem; border-radius: .75rem; background: rgba(var(--bs-primary-rgb), .12); }
    .willkommen-icon .fas { color: var(--bs-primary); }
    .willkommen-schritt { width: 2.5rem; height: 2.5rem; flex-shrink: 0; }
    [data-bs-theme="dark"] .willkommen-karte { background: var(--bs-tertiary-bg); }
</style>

<section class="willkommen-hero rounded-4 px-4 py-5 mb-5 text-center">
    <img src="<?= htmlspecialchars($baseUrl) ?>images/new-icon-512x512.png" alt="" class="willkommen-logo mb-4">
    <h1 class="display-5 fw-bold mb-3">Willkommen bei Fuelstat</h1>
    <p class="lead text-body-secondary mx-auto mb-4" style="max-width: 40rem;">
        Behalte Verbrauch, Kosten und Termine deiner Fahrzeuge im Blick – für Benzin, Diesel, Gas und Elektro.
    </p>
    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
        <?php if ($registrierungOffen): ?>
            <a href="<?= htmlspecialchars($baseUrl) ?>pages/register.php" class="btn btn-primary btn-lg px-4">
                <i class="fas fa-user-plus me-2"></i>Kostenlos registrieren
            </a>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($baseUrl) ?>pages/login.php" class="btn btn-<?= $registrierungOffen ? 'outline-primary' : 'primary' ?> btn-lg px-4">
            <i class="fas fa-sign-in-alt me-2"></i>Anmelden
        </a>
    </div>
    <?php if (!$registrierungOffen): ?>
        <p class="small text-body-secondary mt-3 mb-0">Neue Konten werden auf dieser Installation vom Betreiber angelegt.</p>
    <?php endif; ?>
</section>

<section class="mb-5">
    <h2 class="h3 text-center mb-4">Was Fuelstat für dich erledigt</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($funktionen as [$icon, $titel, $text]): ?>
        <div class="col">
            <div class="card willkommen-karte h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="willkommen-icon d-flex align-items-center justify-content-center mb-3">
                        <i class="fas <?= $icon ?> fa-lg"></i>
                    </div>
                    <h3 class="h5"><?= htmlspecialchars($titel) ?></h3>
                    <p class="text-body-secondary mb-0"><?= htmlspecialchars($text) ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="mb-5">
    <h2 class="h3 text-center mb-4">In drei Schritten startklar</h2>
    <div class="row g-4 justify-content-center">
        <?php foreach ([
            $registrierungOffen
                ? ['Konto anlegen', 'Mit E-Mail und Passwort, auf Wunsch zusätzlich mit Zwei-Faktor-Schutz.']
                : ['Zugang erhalten', 'Mit den Zugangsdaten anmelden, die du vom Betreiber bekommen hast.'],
            ['Fahrzeug hinzufügen', 'Name, Kraftstoff und gern ein Foto – bisherige Daten lassen sich per CSV übernehmen.'],
            ['Losfahren & erfassen', 'Nach jedem Tankstopp kurz eintragen, den Rest rechnet Fuelstat für dich aus.'],
        ] as $nr => [$titel, $text]): ?>
        <div class="col-md-4">
            <div class="d-flex gap-3">
                <div class="willkommen-schritt rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center"><?= $nr + 1 ?></div>
                <div>
                    <h3 class="h6 mb-1"><?= htmlspecialchars($titel) ?></h3>
                    <p class="text-body-secondary small mb-0"><?= htmlspecialchars($text) ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="text-center bg-body-tertiary rounded-4 px-4 py-5 mb-4">
    <h2 class="h4 mb-3">Bereit für den ersten Tankstopp?</h2>
    <p class="text-body-secondary mb-4">Fuelstat läuft im Browser und lässt sich auf dem Handy wie eine App installieren.</p>
    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
        <?php if ($registrierungOffen): ?>
            <a href="<?= htmlspecialchars($baseUrl) ?>pages/register.php" class="btn btn-primary px-4">Jetzt registrieren</a>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($baseUrl) ?>pages/login.php" class="btn btn-<?= $registrierungOffen ? 'outline-secondary' : 'primary' ?> px-4"><?= $registrierungOffen ? 'Ich habe schon ein Konto' : 'Anmelden' ?></a>
    </div>
</section>
