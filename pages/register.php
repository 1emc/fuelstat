<?php
// pages/register.php

// Session-Handling
include '../includes/session.php';

// Output-Bufferung starten
ob_start();

// Debug-Logging
error_log("Register Script gestartet");

try {
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("POST-Request empfangen");
        error_log("POST-Daten: " . print_r($_POST, true));

        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];

        // Validierung
        if (empty($username) || empty($email) || empty($password)) {
            $error = "Bitte füllen Sie alle Felder aus.";
            error_log("Validierungsfehler: Leere Felder");
        } elseif ($password !== $password_confirm) {
            $error = "Die Passwörter stimmen nicht überein.";
            error_log("Validierungsfehler: Passwörter stimmen nicht überein");
        } elseif (strlen($password) < 8) {
            $error = "Das Passwort muss mindestens 8 Zeichen lang sein.";
            error_log("Validierungsfehler: Passwort zu kurz");
        } else {
            error_log("Starte Datenbankverbindung");
            include '../includes/db_connect.php';
            
            // Überprüfen, ob Benutzername oder E-Mail bereits existiert
            $stmt = $conn->prepare("SELECT id FROM benutzer WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "Benutzername oder E-Mail-Adresse bereits vergeben.";
                error_log("Validierungsfehler: Benutzer existiert bereits");
            } else {
                // Benutzer registrieren
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO benutzer (username, password, email) VALUES (?, ?, ?)");
                error_log("Benutzerdaten vor INSERT: username=" . $username . ", email=" . $email);
                $stmt->bind_param("sss", $username, $hashed_password, $email);
                
                if ($stmt->execute()) {
                    error_log("Benutzer erfolgreich registriert: " . $username);
                    $_SESSION['success_message'] = "Registrierung erfolgreich! Sie können sich jetzt anmelden.";
                    header('Location: login.php');
                    exit;
                } else {
                    $error = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
                    error_log("Datenbankfehler: " . $conn->error);
                }
            }
        }
    }

    // Nur wenn keine Weiterleitung erfolgt ist, laden wir die Header und das Template
    include '../includes/header.php';
} catch (Exception $e) {
    error_log("Fehler in register.php: " . $e->getMessage());
    error_log("Stack Trace: " . $e->getTraceAsString());
    die("Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.");
}
?>

<style>
  .card:hover {
    transform: none !important;
    box-shadow: var(--bs-card-box-shadow, 0 .125rem .25rem rgba(0,0,0,.075)) !important;
  }
  .card {
    transition: none !important;
  }
</style>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-gas-pump fa-3x text-primary mb-3"></i>
                        <h3 class="h4">Fuelstat</h3>
                        <p class="text-muted">Registrieren Sie sich</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

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
                                       placeholder="Wählen Sie einen Benutzernamen" 
                                       required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">E-Mail-Adresse</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       placeholder="Ihre E-Mail-Adresse" 
                                       required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Passwort</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Mindestens 8 Zeichen" 
                                       required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirm" class="form-label">Passwort bestätigen</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password_confirm" 
                                       name="password_confirm" 
                                       placeholder="Passwort wiederholen" 
                                       required>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i>
                                Registrieren
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <p class="text-muted mb-0">
                            Bereits registriert? 
                            <a href="login.php" class="text-decoration-none">
                                Jetzt anmelden
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
