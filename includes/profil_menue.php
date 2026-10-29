<?php
// Einträge des Benutzermenüs: am PC als Dropdown, mobil als Liste im Profil-Blatt (eingebunden von header.php)
$alsListe = !empty($profilMenueAlsListe);
$eintragKlasse = $alsListe ? 'list-group-item list-group-item-action d-flex align-items-center' : 'dropdown-item';
[$liAuf, $liZu] = $alsListe ? ['', ''] : ['<li>', '</li>'];
$icon = fn(string $name) => '<i class="fas ' . $name . ($alsListe ? ' fa-fw me-3' : ' me-2') . '"></i>';
$menue = [
    ['pages/einstellungen.php', 'fa-user-edit', 'Profil bearbeiten'],
    ['pages/passwort_aendern.php', 'fa-key', 'Passwort ändern'],
    ['pages/mfa_einrichten.php', 'fa-shield-alt', '2FA einrichten'],
    ['pages/benachrichtigungen.php', 'fa-envelope', 'E-Mail-Benachrichtigungen'],
];
if (!empty($currentUser['is_admin'])) {
    $menue[] = ['pages/admin.php', 'fa-users-cog', 'Benutzerverwaltung'];
}
foreach ($menue as [$ziel, $symbol, $text]): ?>
    <?= $liAuf ?><a class="<?= $eintragKlasse ?>" href="<?= htmlspecialchars($baseUrl . $ziel) ?>"><?= $icon($symbol) ?><?= htmlspecialchars($text) ?></a><?= $liZu ?>
<?php endforeach; ?>
<?php if ($alsListe): ?>
    <button type="button" class="<?= $eintragKlasse ?> theme-umschalter"><?= $icon('fa-moon') ?><span class="theme-text">Dunkle Darstellung</span></button>
<?php else: ?>
    <li><hr class="dropdown-divider"></li>
<?php endif; ?>
    <?= $liAuf ?><a class="<?= $eintragKlasse ?><?= $alsListe ? ' text-danger' : '' ?>" href="<?= htmlspecialchars($baseUrl) ?>pages/logout.php"><?= $icon('fa-sign-out-alt') ?>Abmelden</a><?= $liZu ?>
