<?php
// pages/mfa_einrichten.php

// Session-Handling
include '../includes/session.php';

// Prüfen ob Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Output-Bufferung starten
ob_start();

// Debug-Logging
error_log("MFA Script gestartet für Benutzer: " . $_SESSION['user_id']);

try {
    include '../includes/db_connect.php';
    $success_message = '';
    $error = '';

    // Benutzerdaten abrufen
    $stmt = $conn->prepare("SELECT username, mfa_secret, mfa_enabled FROM benutzer WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // TOTP-Funktionen
    function generateSecret() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }

    function verifyTOTP(string $secret, string $code): bool
    {
        $time      = floor(time() / 30);
        $secretKey = base32_decode($secret);        // RAW‑Key (20 Bytes)
    
        for ($i = -1; $i <= 1; $i++) {              // ±1 Zeitscheibe zulassen
            $counter = $time + $i;
    
            // 8‑Byte‑Counter big‑endian
            $binaryCounter = pack('J', $counter);    // PHP 8;  unter PHP 7: pack('N2', $counter >> 32, $counter & 0xFFFFFFFF);
    
            // *** HMAC als BINARY ***
            $hmac   = hash_hmac('sha1', $binaryCounter, $secretKey, true);
    
            $offset = ord($hmac[19]) & 0x0F;        // 19. echtes Byte
            $slice  = substr($hmac, $offset, 4);
    
            $value  = unpack('N', $slice)[1] & 0x7FFFFFFF;
            $token  = str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
    
            if (hash_equals($token, $code)) {
                return true;
            }
        }
        return false;
    }
    

    function base32_decode($input) {
        $input = strtoupper($input);
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bufferSize = 0;
        $result = '';
        
        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];
            $value = strpos($chars, $char);
            if ($value === false) continue;
            
            $buffer = ($buffer << 5) | $value;
            $bufferSize += 5;
            
            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $result .= chr(($buffer >> $bufferSize) & 0xFF);
            }
        }
        
        return $result;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'enable') {
                // 2FA aktivieren
                $verification_code = trim($_POST['verification_code']);
                error_log("Eingegebener Code: " . $verification_code);
                error_log("MFA Secret: " . $user['mfa_secret']);
                
                if (verifyTOTP($user['mfa_secret'], $verification_code)) {
                    $stmt = $conn->prepare("UPDATE benutzer SET mfa_enabled = 1 WHERE id = ?");
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    if ($stmt->execute()) {
                        $success_message = "Zwei-Faktor-Authentifizierung erfolgreich aktiviert!";
                        // Benutzerdaten neu abrufen
                        $stmt = $conn->prepare("SELECT username, mfa_secret, mfa_enabled FROM benutzer WHERE id = ?");
                        $stmt->bind_param("i", $_SESSION['user_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                    } else {
                        $error = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
                    }
                } else {
                    $error = "Ungültiger Verifizierungscode. Bitte überprüfen Sie die Uhrzeit Ihres Geräts und versuchen Sie es erneut.";
                }
            } elseif ($_POST['action'] === 'disable') {
                // 2FA deaktivieren
                $stmt = $conn->prepare("UPDATE benutzer SET mfa_secret = NULL, mfa_enabled = 0 WHERE id = ?");
                $stmt->bind_param("i", $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $success_message = "Zwei-Faktor-Authentifizierung erfolgreich deaktiviert!";
                    $user['mfa_secret'] = null;
                    $user['mfa_enabled'] = 0;
                } else {
                    $error = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
                }
            }
        }
    }

    // Wenn kein MFA-Secret existiert, erstelle ein neues
    if (empty($user['mfa_secret'])) {
        $secret = generateSecret();
        $stmt = $conn->prepare("UPDATE benutzer SET mfa_secret = ? WHERE id = ?");
        $stmt->bind_param("si", $secret, $_SESSION['user_id']);
        $stmt->execute();
        $user['mfa_secret'] = $secret;
    }

    // TOTP-URI für QR-Code generieren
    $issuer = 'Fuelstat';
    $accountName = $user['username'];
    $totpUri = sprintf(
        'otpauth://totp/%s:%s?secret=%s&issuer=%s',
        rawurlencode($issuer),
        rawurlencode($accountName),
        $user['mfa_secret'],
        rawurlencode($issuer)
    );

    include '../includes/header.php';
} catch (Exception $e) {
    error_log("Fehler in mfa_einrichten.php: " . $e->getMessage());
    error_log("Stack Trace: " . $e->getTraceAsString());
    die("Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.");
}
?>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                        <h3 class="h4">Zwei-Faktor-Authentifizierung</h3>
                        <p class="text-muted">Schützen Sie Ihr Konto mit 2FA</p>
                    </div>

                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success" role="alert">
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$user['mfa_enabled']): ?>
                        <div class="text-center mb-4">
                            <p>Scannen Sie diesen QR-Code mit Ihrer Authenticator-App:</p>
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($totpUri); ?>" 
                                 alt="QR Code" 
                                 class="img-fluid mb-3">
                            <p class="small text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Verwenden Sie eine Authenticator-App wie Google Authenticator, Microsoft Authenticator oder Authy.
                            </p>
                            <p class="small text-muted">
                                <i class="fas fa-key me-1"></i>
                                Alternativ können Sie diesen Code manuell eingeben:
                            </p>
                            <code class="bg-light p-2 rounded d-block mb-3"><?php echo $user['mfa_secret']; ?></code>
                        </div>

                        <form method="post">
                            <input type="hidden" name="action" value="enable">
                            <div class="mb-3">
                                <label for="verification_code" class="form-label">Verifizierungscode</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-mobile-alt"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="verification_code" 
                                           name="verification_code" 
                                           placeholder="6-stelliger Code aus Ihrer App" 
                                           required 
                                           pattern="[0-9]{6}">
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check me-2"></i>
                                    2FA aktivieren
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            Zwei-Faktor-Authentifizierung ist aktiviert.
                        </div>

                        <form method="post" onsubmit="return confirm('Möchten Sie die Zwei-Faktor-Authentifizierung wirklich deaktivieren?');">
                            <input type="hidden" name="action" value="disable">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-times me-2"></i>
                                    2FA deaktivieren
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <a href="../index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Zurück
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
