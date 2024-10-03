<!doctype html>
<html lang="de" class="light-style layout-menu-fixed layout-compact" dir="ltr">
<head>
    <meta charset="utf-8" />
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Fuelstat - Die Tank-Statistik</title>

    <meta name="description" content="" />

    <!-- Apple Information and PWA-Function -->
    <link rel="manifest" href="/fuelstat/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="/fuelstat/images/icon-192x192.png">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="/fuelstat/css/style.css" />
</head>

<body>

<!-- Navigation Menu -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container-fluid">
        <a class="navbar-brand" href="/fuelstat/">
            <i class="fas fa-gas-pump"></i> Fuelstat
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Navigation umschalten">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav">
                <!-- Menüelemente -->
                <li class="nav-item">
                    <a class="nav-link" href="/fuelstat/">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/fuelstat/pages/fahrzeug_hinzufuegen.php">Fahrzeug hinzufügen</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Dropdown für Fahrzeuge -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="fahrzeugeDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Meine Fahrzeuge
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="fahrzeugeDropdown">
                            <?php
                            // Funktion zum Abrufen der Fahrzeuge des Benutzers
                            $userVehicles = getUserVehicles($_SESSION['user_id']);
                            foreach ($userVehicles as $vehicle):
                            ?>
                                <li>
                                    <a class="dropdown-item" href="/fuelstat/pages/fahrzeug_detail.php?id=<?php echo $vehicle['id']; ?>">
                                        <?php echo htmlspecialchars($vehicle['marke'] . ' ' . $vehicle['modell']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/fuelstat/pages/fahrzeug_hinzufuegen.php">Fahrzeug hinzufügen</a></li>
                        </ul>
                    </li>
                    <!-- Abmelden -->
                    <li class="nav-item">
                        <a class="nav-link" href="/fuelstat/pages/logout.php">Abmelden</a>
                    </li>
                <?php else: ?>
                    <!-- Anmelden -->
                    <li class="nav-item">
                        <a class="nav-link" href="#">Anmelden</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Hauptinhalt -->
<div class="container mt-4">
