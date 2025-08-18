<?php
// Session-Handling
include '../includes/session.php';

// Output-Bufferung starten
ob_start();

// Debug-Logging
error_log("Login Script gestartet");

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("POST-Request empfangen");
        error_log("POST-Daten: " . print_r($_POST, true));
        
        include '../includes/db_connect.php';
        
        // TOTP-Funktionen
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

        function verifyTOTP(string $secret, string $code): bool {
            $time      = floor(time() / 30);
            $secretKey = base32_decode($secret);
        
            for ($i = -1; $i <= 1; $i++) {
                $counter = $time + $i;
                $binaryCounter = pack('J', $counter);
                $hmac   = hash_hmac('sha1', $binaryCounter, $secretKey, true);
                $offset = ord($hmac[19]) & 0x0F;
                $slice  = substr($hmac, $offset, 4);
                $value  = unpack('N', $slice)[1] & 0x7FFFFFFF;
                $token  = str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
        
                if (hash_equals($token, $code)) {
                    return true;
                }
            }
            return false;
        }

        // Erste Stufe: Benutzername und Passwort prüfen
        if (!isset($_SESSION['user_id']) && isset($_POST['username']) && isset($_POST['password'])) {
            $username = $_POST['username'];
            $password = $_POST['password'];
            
            $stmt = $conn->prepare("SELECT id, username, password, mfa_enabled, mfa_secret FROM benutzer WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    error_log("Login erfolgreich für Benutzer: " . $username);
                    
                    // Wenn 2FA aktiviert ist, speichere Benutzerdaten in Session und zeige 2FA-Formular
                    if ($user['mfa_enabled']) {
                        $_SESSION['pending_user_id'] = $user['id'];
                        $_SESSION['pending_username'] = $user['username'];
                        $_SESSION['pending_mfa_secret'] = $user['mfa_secret'];
                        $show_mfa_form = true;
                    } else {
                        // Wenn keine 2FA, direkt einloggen
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        
                        // Nach erfolgreichem Login:
                        $sessionId = session_id();
                        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                        $now = date('Y-m-d H:i:s');

                        // Prüfen, ob Session schon existiert
                        $stmt = $conn->prepare("SELECT id FROM benutzer_sessions WHERE session_id = ?");
                        $stmt->bind_param("s", $sessionId);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            // Update last_activity
                            $stmt = $conn->prepare("UPDATE benutzer_sessions SET last_activity = ? WHERE session_id = ?");
                            $stmt->bind_param("ss", $now, $sessionId);
                            $stmt->execute();
                        } else {
                            // Neue Session anlegen
                            $stmt = $conn->prepare("INSERT INTO benutzer_sessions (user_id, session_id, user_agent, ip_address, login_time, last_activity) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("isssss", $_SESSION['user_id'], $sessionId, $userAgent, $ip, $now, $now);
                            $stmt->execute();
                        }
                        
                        header('Location: onboarding.php');
                        exit;
                    }
                } else {
                    $error = "Ungültige Anmeldedaten";
                    error_log("Login fehlgeschlagen für Benutzer: " . $username);
                }
            } else {
                $error = "Ungültige Anmeldedaten";
                error_log("Benutzer nicht gefunden: " . $username);
            }
        }
        
        // Zweite Stufe: 2FA-Code prüfen
        if (isset($_SESSION['pending_user_id']) && isset($_POST['mfa_code'])) {
            $mfa_code = trim($_POST['mfa_code']);
            
            if (verifyTOTP($_SESSION['pending_mfa_secret'], $mfa_code)) {
                // 2FA erfolgreich, Benutzer einloggen
                $_SESSION['user_id'] = $_SESSION['pending_user_id'];
                $_SESSION['username'] = $_SESSION['pending_username'];
                
                // Pending-Session-Daten löschen
                unset($_SESSION['pending_user_id']);
                unset($_SESSION['pending_username']);
                unset($_SESSION['pending_mfa_secret']);
                
                // Nach erfolgreichem Login:
                $sessionId = session_id();
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $now = date('Y-m-d H:i:s');

                // Prüfen, ob Session schon existiert
                $stmt = $conn->prepare("SELECT id FROM benutzer_sessions WHERE session_id = ?");
                $stmt->bind_param("s", $sessionId);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    // Update last_activity
                    $stmt = $conn->prepare("UPDATE benutzer_sessions SET last_activity = ? WHERE session_id = ?");
                    $stmt->bind_param("ss", $now, $sessionId);
                    $stmt->execute();
                } else {
                    // Neue Session anlegen
                    $stmt = $conn->prepare("INSERT INTO benutzer_sessions (user_id, session_id, user_agent, ip_address, login_time, last_activity) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssss", $_SESSION['user_id'], $sessionId, $userAgent, $ip, $now, $now);
                    $stmt->execute();
                }
                
                header('Location: onboarding.php');
                exit;
            } else {
                $error = "Ungültiger 2FA-Code";
                $show_mfa_form = true;
            }
        }
    }

    // Nur wenn keine Weiterleitung erfolgt ist, laden wir die Header und das Template
    include '../includes/header.php';
} catch (Exception $e) {
    error_log("Fehler in login.php: " . $e->getMessage());
    error_log("Stack Trace: " . $e->getTraceAsString());
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

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error); ?>
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

                    <?php if (isset($show_mfa_form) && $show_mfa_form): ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="mfa_code" class="form-label">2FA-Code</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-mobile-alt"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="mfa_code" 
                                           name="mfa_code" 
                                           placeholder="6-stelliger Code aus Ihrer App" 
                                           required 
                                           pattern="[0-9]{6}">
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Verifizieren
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="username" class="form-label">Benutzername</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="username" 
                                           name="username" 
                                           placeholder="Ihr Benutzername" 
                                           required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Passwort</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           placeholder="Ihr Passwort" 
                                           required>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Anmelden
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted mb-0">
                                Noch kein Konto? 
                                <a href="register.php" class="text-decoration-none">
                                    Jetzt registrieren
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
