<?php
include '../includes/session.php';
require_once __DIR__ . '/../includes/api_helpers.php';
if (!isApiAuthenticated()) {
    header('Location: login.php');
    exit;
}

$api = getApiClient();
try {
    $ich = $api->getWhoAmI();
} catch (Throwable $e) {
    $ich = [];
}
if (empty($ich['is_admin'])) {
    http_response_code(403);
    die('Kein Zugriff. Admins werden über ADMIN_USER_IDS in der Backend-.env festgelegt.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $zielId = trim($_POST['user_id'] ?? '');
    $aktion = $_POST['aktion'] ?? '';
    try {
        // Beta-Status und Feedback betreffen auch das eigene Konto
        if ($aktion === 'beta') {
            $api->putUserBeta($zielId, !empty($_POST['wert']));
            header('Location: admin.php');
            exit;
        }
        if ($aktion === 'feedback') {
            $api->patchFeedback(trim($_POST['feedback_id'] ?? ''), !empty($_POST['wert']));
            header('Location: admin.php#feedback');
            exit;
        }
        if ($zielId === ($ich['id'] ?? '')) {
            throw new RuntimeException('Das eigene Konto bitte über die Einstellungen verwalten.');
        }
        if (($_POST['aktion'] ?? '') === 'abmelden') {
            $api->revokeAllTokens($zielId);
            $_SESSION['success_message'] = 'Benutzer wurde auf allen Geräten abgemeldet.';
        } elseif (($_POST['aktion'] ?? '') === 'loeschen') {
            $api->deleteUser($zielId);
            $_SESSION['success_message'] = 'Benutzer gelöscht.';
        }
        header('Location: admin.php');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

try {
    $benutzer = $api->getUsers();
} catch (Throwable $e) {
    $benutzer = [];
    $error = $e->getMessage();
}
try {
    $feedback = $api->getFeedback();
} catch (Throwable $e) {
    $feedback = [];
}
$betaAnzahl = count(array_filter($benutzer, fn($u) => !empty($u['beta_tester'])));
$offenAnzahl = count(array_filter($feedback, fn($f) => empty($f['done'])));
$artInfo = [
    'fehler' => ['fa-bug', 'danger', 'Fehler'],
    'idee' => ['fa-lightbulb', 'warning', 'Idee'],
    'lob' => ['fa-heart', 'success', 'Lob'],
    'sonstiges' => ['fa-comment', 'secondary', 'Sonstiges'],
];

include '../includes/header.php';
?>
<div class="container mt-5">
    <h2><i class="fas fa-users-cog me-2"></i>Benutzerverwaltung</h2>
    <p class="text-body-secondary"><?= count($benutzer) ?> Benutzer, davon <?= $betaAnzahl ?> Beta-Tester<?= $offenAnzahl ? ' · <a href="#feedback">' . $offenAnzahl . ' offene Feedbacks</a>' : '' ?></p>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr><th>E-Mail</th><th>Benutzername</th><th>2FA</th><th>Beta-Tester</th><th>Registriert</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($benutzer as $u): $istIch = ($u['id'] ?? '') === ($ich['id'] ?? ''); ?>
                <tr>
                    <td><?= htmlspecialchars($u['email'] ?? '') ?><?= $istIch ? ' <span class="badge text-bg-primary">Du</span>' : '' ?></td>
                    <td><?= htmlspecialchars($u['username'] ?? '') ?></td>
                    <td><?= !empty($u['mfa_enabled']) ? '<i class="fas fa-check text-success"></i>' : '–' ?></td>
                    <td>
                        <form method="post" class="form-check form-switch mb-0">
                            <input type="hidden" name="aktion" value="beta">
                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                            <input type="hidden" name="wert" value="<?= empty($u['beta_tester']) ? '1' : '' ?>">
                            <input class="form-check-input" type="checkbox" role="switch" aria-label="Beta-Tester" onchange="this.form.submit()" <?= !empty($u['beta_tester']) ? 'checked' : '' ?>>
                        </form>
                    </td>
                    <td><?= isset($u['created_at']) ? date('d.m.Y', strtotime($u['created_at'])) : '' ?></td>
                    <td class="text-end">
                        <?php if (!$istIch): ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Diesen Benutzer auf allen Geräten abmelden?');">
                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                            <button name="aktion" value="abmelden" class="btn btn-sm btn-outline-secondary" title="Abmelden"><i class="fas fa-sign-out-alt"></i></button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('Benutzer <?= htmlspecialchars(addslashes($u['email'] ?? '')) ?> mit allen Daten löschen?');">
                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                            <button name="aktion" value="loeschen" class="btn btn-sm btn-outline-danger" title="Löschen"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3 class="h4 mt-5 mb-3" id="feedback"><i class="fas fa-comment-dots me-2"></i>Feedback</h3>
    <?php if (!$feedback): ?>
        <p class="text-body-secondary">Noch kein Feedback eingegangen.</p>
    <?php endif; ?>
    <div class="d-flex flex-column gap-3 mb-5">
        <?php foreach ($feedback as $f): [$icon, $farbe, $artName] = $artInfo[$f['category']] ?? $artInfo['sonstiges']; ?>
        <div class="card border-0 shadow-sm<?= !empty($f['done']) ? ' opacity-50' : '' ?>">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <span class="badge text-bg-<?= $farbe ?> me-2"><i class="fas <?= $icon ?> me-1"></i><?= $artName ?></span>
                        <?php if (!empty($f['username'])): ?><strong class="me-1"><?= htmlspecialchars($f['username']) ?></strong><?php endif; ?>
                        <?php if (!empty($f['user_email'])): ?>
                            <a href="mailto:<?= htmlspecialchars($f['user_email']) ?>?subject=<?= rawurlencode('Dein Fuelstat-Feedback') ?>" title="Antworten"><?= htmlspecialchars($f['user_email']) ?></a>
                        <?php else: ?>
                            <span class="text-body-secondary">gelöschtes Konto</span>
                        <?php endif; ?>
                        <?php if (!empty($f['beta_tester'])): ?><span class="badge rounded-pill text-bg-warning ms-1">Beta</span><?php endif; ?>
                        <div class="small text-body-secondary"><?= date('d.m.Y H:i', strtotime($f['created_at'])) ?><?= !empty($f['page']) ? ' · ' . htmlspecialchars($f['page']) : '' ?></div>
                    </div>
                    <form method="post">
                        <input type="hidden" name="aktion" value="feedback">
                        <input type="hidden" name="feedback_id" value="<?= htmlspecialchars($f['id']) ?>">
                        <input type="hidden" name="wert" value="<?= empty($f['done']) ? '1' : '' ?>">
                        <button class="btn btn-sm <?= empty($f['done']) ? 'btn-outline-success' : 'btn-outline-secondary' ?>">
                            <i class="fas <?= empty($f['done']) ? 'fa-check' : 'fa-rotate-left' ?> me-1"></i><?= empty($f['done']) ? 'Erledigt' : 'Wieder öffnen' ?>
                        </button>
                    </form>
                </div>
                <p class="mb-1" style="white-space: pre-wrap;"><?= htmlspecialchars($f['message']) ?></p>
                <?php if (!empty($f['user_agent'])): ?>
                    <div class="small text-body-secondary text-truncate" title="<?= htmlspecialchars($f['user_agent']) ?>"><i class="fas fa-mobile-screen me-1"></i><?= htmlspecialchars($f['user_agent']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
