<?php
// Debug-Einstellungen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session starten
session_start();

// Prüfen ob Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Output-Bufferung starten
ob_start();

// Debug-Logging
error_log("Passwort ändern Script gestartet für Benutzer: " . $_SESSION['user_id']);

try {
    include '../includes/db_connect.php';
    $success_message = '';
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validierung
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "Bitte füllen Sie alle Felder aus.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Die neuen Passwörter stimmen nicht überein.";
        } elseif (strlen($new_password) < 8) {
            $error = "Das neue Passwort muss mindestens 8 Zeichen lang sein.";
        } else {
            // Aktuelles Passwort überprüfen
            $stmt = $conn->prepare("SELECT password FROM benutzer WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if (password_verify($current_password, $user['password'])) {
                // Neues Passwort hashen und speichern
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE benutzer SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $success_message = "Passwort erfolgreich geändert!";
                } else {
                    $error = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
                }
            } else {
                $error = "Das aktuelle Passwort ist nicht korrekt.";
            }
        }
    }

    include '../includes/header.php';
} catch (Exception $e) {
    error_log("Fehler in passwort_aendern.php: " . $e->getMessage());
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
                        <i class="fas fa-key fa-3x text-primary mb-3"></i>
                        <h3 class="h4">Passwort ändern</h3>
                        <p class="text-muted">Geben Sie Ihr aktuelles und neues Passwort ein</p>
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

                    <form method="post">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Aktuelles Passwort</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="current_password" 
                                       name="current_password" 
                                       placeholder="Ihr aktuelles Passwort" 
                                       required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">Neues Passwort</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-key"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="new_password" 
                                       name="new_password" 
                                       placeholder="Mindestens 8 Zeichen" 
                                       required>
                            </div>
                            <div class="form-text">
                                Das Passwort muss mindestens 8 Zeichen lang sein.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Neues Passwort bestätigen</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-key"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       placeholder="Passwort wiederholen" 
                                       required>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>
                                Passwort ändern
                            </button>
                            <a href="../index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>
                                Abbrechen
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
