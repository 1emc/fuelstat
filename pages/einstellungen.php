<?php
// Debug-Einstellungen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
error_log("Einstellungen Script gestartet für Benutzer: " . $_SESSION['user_id']);

try {
    include '../includes/db_connect.php';
    $success_message = '';
    $error = '';

    // Benutzerdaten abrufen
    $stmt = $conn->prepare("SELECT username, email FROM benutzer WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        
        // Validierung
        if (empty($username) || empty($email)) {
            $error = "Bitte füllen Sie alle Felder aus.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Bitte geben Sie eine gültige E-Mail-Adresse ein.";
        } else {
            // Überprüfen, ob Benutzername oder E-Mail bereits existiert (außer für den aktuellen Benutzer)
            $stmt = $conn->prepare("SELECT id FROM benutzer WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->bind_param("ssi", $username, $email, $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "Benutzername oder E-Mail-Adresse bereits vergeben.";
            } else {
                // Profil aktualisieren
                $stmt = $conn->prepare("UPDATE benutzer SET username = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $username, $email, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['username'] = $username; // Session aktualisieren
                    $success_message = "Profil erfolgreich aktualisiert!";
                    // Benutzerdaten neu abrufen
                    $stmt = $conn->prepare("SELECT username, email FROM benutzer WHERE id = ?");
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                } else {
                    $error = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
                }
            }
        }
    }

    include '../includes/header.php';

    // --- Sessions/angemeldete Geräte anzeigen und verwalten ---
    // Session-Abmeldung
    if (isset($_GET['logout_session']) && ctype_digit($_GET['logout_session'])) {
        $sessionId = $_GET['logout_session'];
        // Nur eigene Sessions dürfen gelöscht werden
        $stmt = $conn->prepare("DELETE FROM benutzer_sessions WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $sessionId, $_SESSION['user_id']);
        $stmt->execute();
        header('Location: einstellungen.php');
        exit;
    }
    // Alle anderen Sessions abmelden
    if (isset($_GET['logout_others']) && $_GET['logout_others'] === '1') {
        $currentSessionId = session_id();
        $stmt = $conn->prepare("DELETE FROM benutzer_sessions WHERE user_id = ? AND session_id != ?");
        $stmt->bind_param("is", $_SESSION['user_id'], $currentSessionId);
        $stmt->execute();
        header('Location: einstellungen.php');
        exit;
    }
    // Aktive Sessions abrufen
    $stmt = $conn->prepare("SELECT * FROM benutzer_sessions WHERE user_id = ? ORDER BY last_activity DESC");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $sessionsResult = $stmt->get_result();
    $sessions = $sessionsResult->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Fehler in einstellungen.php: " . $e->getMessage());
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
                        <i class="fas fa-user-cog fa-3x text-primary mb-3"></i>
                        <h3 class="h4">Profil bearbeiten</h3>
                        <p class="text-muted">Aktualisieren Sie Ihre persönlichen Daten</p>
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
                            <label for="username" class="form-label">Benutzername</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" 
                                       required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="email" class="form-label">E-Mail-Adresse</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" 
                                       required>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>
                                Änderungen speichern
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

    <!-- Aktive Geräte/Sessions -->
    <div class="row justify-content-center mt-4">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="fas fa-laptop me-2"></i>Angemeldete Geräte/Sessions</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Gerät/Browser</th>
                                    <th>IP-Adresse</th>
                                    <th>Login</th>
                                    <th>Letzte Aktivität</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $sess): ?>
                                    <tr<?php if ($sess['session_id'] === session_id()) echo ' class="table-success"'; ?>>
                                        <td><?php echo htmlspecialchars($sess['user_agent']); ?><?php if ($sess['session_id'] === session_id()) echo ' <span class="badge bg-primary">Aktuell</span>'; ?></td>
                                        <td><?php echo htmlspecialchars($sess['ip_address']); ?></td>
                                        <td><?php echo htmlspecialchars($sess['login_time']); ?></td>
                                        <td><?php echo htmlspecialchars($sess['last_activity']); ?></td>
                                        <td>
                                            <?php if ($sess['session_id'] !== session_id()): ?>
                                                <a href="?logout_session=<?php echo $sess['id']; ?>" class="btn btn-sm btn-outline-danger" title="Abmelden" onclick="return confirm('Diese Session wirklich abmelden?');">
                                                    <i class="fas fa-sign-out-alt"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Diese Session</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($sessions) > 1): ?>
                        <a href="?logout_others=1" class="btn btn-outline-warning btn-sm mt-2" onclick="return confirm('Alle anderen Geräte wirklich abmelden?');">
                            <i class="fas fa-unlink me-1"></i> Alle anderen Geräte abmelden
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
