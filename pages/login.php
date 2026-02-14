<?php
include '../includes/session.php';
require_once __DIR__ . '/../includes/api_helpers.php';
ob_start();

$error = '';
$show_mfa_form = false;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        include '../includes/db_connect.php';

        // API-Login: E-Mail + Passwort (OpenAPI Auth)
        if (!isApiAuthenticated() && isset($_POST['email']) && isset($_POST['password'])) {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($email === '' || $password === '') {
                $error = "Bitte E-Mail und Passwort eingeben.";
            } else {
                try {
                    // Login ohne Session-Token aufrufen (sonst wird alter Bearer mitgeschickt → 401)
                    $apiLogin = getApiClient(null);
                    $response = $apiLogin->postAuthLogin($email, $password);
                    $data = $response['data'] ?? $response;
                    $token = $data['token'] ?? $data['access_token'] ?? $data['accessToken'] ?? '';
                    $user = $data['user'] ?? [];
                    $userId = $user['id'] ?? $user['userId'] ?? null;
                    if ($token !== '') {
                        setApiToken($token, [
                            'id' => $userId,
                            'email' => $user['email'] ?? $email,
                        ]);
                        error_log('[Login] success token_len=' . strlen($token) . ' session_id=' . session_id() . ' stored_token_len=' . strlen((string)getApiToken()));
                        session_write_close();
                        while (ob_get_level()) {
                            ob_end_clean();
                        }
                        header('Location: /index.php', true, 302);
                        exit;
                    }
                    error_log('[Login] missing token in response for ' . $email . ', keys=' . implode(',', array_keys($data)));
                    $error = 'Login-Antwort enthielt kein Token.';
                } catch (RuntimeException $e) {
                    $code = $e->getCode();
                    $msg = $e->getMessage();
                    error_log('[Login] API-Fehler: ' . $code . ' ' . $msg);
                    if ($code === 401 || stripos($msg, 'invalid_credentials') !== false) {
                        $error = "Ungültige E-Mail oder Passwort.";
                    } else {
                        $error = $msg;
                    }
                }
            }
        }
    }

    include '../includes/header.php';
} catch (Exception $e) {
    error_log("Fehler in login.php: " . $e->getMessage());
    die("Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.");
}
?>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-gas-pump fa-3x text-primary mb-3"></i>
                        <h3 class="h4">Fuelstat</h3>
                        <p class="text-muted">Bitte melde dich an</p>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success" role="alert">
                            <?php
                            echo htmlspecialchars($_SESSION['success_message']);
                            unset($_SESSION['success_message']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label for="email" class="form-label">E-Mail</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" autocomplete="username"
                                       placeholder="Ihre E-Mail-Adresse" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label">Passwort</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" autocomplete="current-password"
                                       placeholder="Ihr Passwort" required>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i> Anmelden
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <p class="text-muted mb-0">
                            Noch kein Konto?
                            <a href="register.php" class="text-decoration-none">Jetzt registrieren</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
