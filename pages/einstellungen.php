<?php
include '../includes/session.php';
require_once __DIR__ . '/../includes/api_helpers.php';

if (!isApiAuthenticated()) {
    header('Location: login.php');
    exit;
}

include '../includes/db_connect.php';
$api = getApiClient();
$success_message = '';
$error = '';
$user = ['email' => $_SESSION['user_email'] ?? ''];

try {
    $who = $api->getWhoAmI();
    $user['id'] = $who['id'] ?? $_SESSION['user_id'];
    $user['email'] = $who['email'] ?? $user['email'];
} catch (Throwable $e) {
    error_log('[Einstellungen] getWhoAmI failed: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Bitte eine gültige E-Mail-Adresse angeben.';
    } else {
        try {
            $api->patchUser($user['id'], ['email' => $email]);
            $_SESSION['user_email'] = $email;
            $user['email'] = $email;
            $success_message = 'E-Mail wurde aktualisiert.';
        } catch (Throwable $e) {
            error_log('[Einstellungen] patchUser failed: ' . $e->getMessage());
            $error = $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-cog fa-3x text-primary mb-3"></i>
                        <h3 class="h4">Profil bearbeiten</h3>
                        <p class="text-muted">E-Mail über die API aktualisieren</p>
                    </div>
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-4">
                            <label for="email" class="form-label">E-Mail-Adresse</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Änderungen speichern</button>
                            <a href="../index.php" class="btn btn-outline-secondary"><i class="fas fa-times me-2"></i>Abbrechen</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <p class="text-muted small mt-3 text-center">Passwort ändern und 2FA werden von der API (OpenAPI 1.0.0) derzeit nicht angeboten.</p>
</div>

<?php include '../includes/footer.php'; ?>
