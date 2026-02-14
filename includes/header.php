<?php
// API-Anbindung (ersetzt frühere DB-Verbindung)
include 'db_connect.php';
require_once __DIR__ . '/api_helpers.php';

// Basis-URL (mit http/https) und Basis-Pfad (unterhalb Document-Root) ermitteln
// So funktionieren Links und Assets auch in Unterordnern (z.B. /fuelstat)
$isHttps = (
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);
$scheme  = $isHttps ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Pfade auflösen und in URL-Pfad umwandeln
$docRoot    = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$appRootFs  = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$basePath   = rtrim(str_replace($docRoot, '', $appRootFs), '/');
if ($basePath === '' || $basePath[0] !== '/') {
    $basePath = '/' . ltrim($basePath, '/');
}
$baseUrl = $scheme . '://' . $host . ($basePath === '/' ? '' : $basePath) . '/';

// Fahrzeuge des eingeloggten Benutzers über API (user_id wird ignoriert; API nutzt JWT)
$api = getApiClient();

function getUserVehicles($user_id) {
    global $api;
    $vehicles = [];
    try {
        $list = $api->getVehicles();
        foreach ($list as $v) {
            $vehicles[] = [
                'id' => $v['id'],
                'marke' => '', // API liefert nur name
                'modell' => $v['name'] ?? '',
            ];
        }
    } catch (Throwable $e) {
        error_log('[Header] getVehicles failed: ' . $e->getMessage());
    }
    return $vehicles;
}

// Session validieren: Nur bei 401 (ungültiger/abgelaufener Token) ausloggen
if (isApiAuthenticated()) {
    try {
        $api->getWhoAmI();
    } catch (Throwable $e) {
        $code = method_exists($e, 'getCode') ? $e->getCode() : 0;
        error_log('[Header] getWhoAmI failed with code ' . $code . ': ' . $e->getMessage());
        if ($code === 401) {
            clearApiToken();
            $_SESSION = array();
            if (isset($_COOKIE[session_name()])) {
                $sessionCookieOptions = getSessionCookieOptions();
                setcookie(session_name(), '', [
                    'expires' => time() - 3600,
                    'path' => $sessionCookieOptions['path'],
                    'secure' => $sessionCookieOptions['secure'],
                    'httponly' => $sessionCookieOptions['httponly'],
                    'samesite' => $sessionCookieOptions['samesite'],
                ]);
            }
            session_destroy();
            header('Location: ' . $baseUrl . 'pages/login.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="de" class="light-style layout-menu-fixed layout-compact" dir="ltr">
<head>
    <meta charset="utf-8" />
    <title>Fuelstat - Die Tank-Statistik</title>

    <meta name="description" content="" />

    <!-- Apple Information and PWA-Function -->
    <link rel="manifest" href="<?= htmlspecialchars($baseUrl) ?>manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($baseUrl) ?>images/new-icon-512x512.png">
	<link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($baseUrl) ?>images/new-icon.ico">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">


    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>css/style.css" />
</head>

<body>

<!-- Navigation Menu -->
<nav class="navbar sticky-top navbar-expand-lg navbar-light bg-light">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= htmlspecialchars($baseUrl) ?>index.php">
            <i class="fas fa-gas-pump"></i> Fuelstat
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Navigation umschalten">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end bg-light" id="navbarNav">
            <ul class="navbar-nav">
                <!-- Menüelemente -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= htmlspecialchars($baseUrl) ?>index.php">Home</a>
                </li>
                <?php if (isApiAuthenticated()): ?>
                    <!-- Dropdown für Fahrzeuge -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="fahrzeugeDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Meine Fahrzeuge
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="fahrzeugeDropdown">
                            <?php
                            // Funktion zum Abrufen der Fahrzeuge des Benutzers
                            $userVehicles = getUserVehicles($_SESSION['user_id'] ?? null);
                            foreach ($userVehicles as $vehicle):
                            ?>
                                <li>
                                    <a class="dropdown-item" href="<?= htmlspecialchars($baseUrl) ?>pages/fahrzeug_detail.php?id=<?php echo htmlspecialchars($vehicle['id']); ?>">
                                        <?php echo htmlspecialchars(trim($vehicle['marke'] . ' ' . $vehicle['modell'])); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($baseUrl) ?>pages/fahrzeug_hinzufuegen.php">Fahrzeug hinzufügen</a></li>
                        </ul>
                    </li>
                    <!-- Benutzereinstellungen -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userSettingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-cog"></i> Einstellungen
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userSettingsDropdown">
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($baseUrl) ?>pages/einstellungen.php">
                                    <i class="fas fa-user-edit me-2"></i> Profil bearbeiten
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($baseUrl) ?>pages/passwort_aendern.php">
                                    <i class="fas fa-key me-2"></i> Passwort ändern
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($baseUrl) ?>pages/mfa_einrichten.php">
                                    <i class="fas fa-shield-alt me-2"></i> 2FA einrichten
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($baseUrl) ?>pages/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Abmelden
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- Anmelden -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= htmlspecialchars($baseUrl) ?>pages/login.php">Anmelden</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Hauptinhalt -->
<div class="container mt-4">

<?php
// Erfolgs- und Warnmeldungen anzeigen
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
    echo '<i class="fas fa-check-circle me-2"></i>' . htmlspecialchars($_SESSION['success_message']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>';
    echo '</div>';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['warning_message'])) {
    echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
    echo '<i class="fas fa-exclamation-triangle me-2"></i>' . htmlspecialchars($_SESSION['warning_message']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>';
    echo '</div>';
    unset($_SESSION['warning_message']);
}

if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo '<i class="fas fa-times-circle me-2"></i>' . htmlspecialchars($_SESSION['error_message']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>';
    echo '</div>';
    unset($_SESSION['error_message']);
}
?>
