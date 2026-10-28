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
    try {
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

include '../includes/header.php';
?>
<div class="container mt-5">
    <h2><i class="fas fa-users-cog me-2"></i>Benutzerverwaltung</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr><th>E-Mail</th><th>Benutzername</th><th>2FA</th><th>Registriert</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($benutzer as $u): $istIch = ($u['id'] ?? '') === ($ich['id'] ?? ''); ?>
                <tr>
                    <td><?= htmlspecialchars($u['email'] ?? '') ?><?= $istIch ? ' <span class="badge text-bg-primary">Du</span>' : '' ?></td>
                    <td><?= htmlspecialchars($u['username'] ?? '') ?></td>
                    <td><?= !empty($u['mfa_enabled']) ? '<i class="fas fa-check text-success"></i>' : '–' ?></td>
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
</div>
<?php include '../includes/footer.php'; ?>
