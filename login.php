<?php
session_start();

// Redirection si l'utilisateur est déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Initialisation des variables
$error = '';
$username = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/db_connect.php';
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation basique
    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } else {
        // Recherche de l'utilisateur dans la base de données
        $stmt = $conn->prepare("SELECT id, username, mot_de_passe, email, role FROM utilisateurs WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Vérification du mot de passe
            if (password_verify($password, $user['mot_de_passe'])) {
                // Création de la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                
                // Récupérer l'adherent_id principal depuis la table de liaison
                $adherent_stmt = $conn->prepare("SELECT adherent_id FROM utilisateurs_adherents WHERE utilisateur_id = ? AND principal = 1");
                $adherent_stmt->bind_param("i", $user['id']);
                $adherent_stmt->execute();
                $adherent_result = $adherent_stmt->get_result();
                
                if ($adherent_result->num_rows === 1) {
                    $adherent_data = $adherent_result->fetch_assoc();
                    $_SESSION['adherent_id'] = $adherent_data['adherent_id'];
                }
                $adherent_stmt->close();
                
                // Redirection vers la page d'accueil
                header('Location: index.php');
                exit;
            } else {
                $error = 'Identifiants incorrects';
            }
        } else {
            $error = 'Identifiants incorrects';
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asso Vélo - Connexion</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <img src="img/login.png" alt="Asso Vélo Logo" class="img-fluid login-logo">
                            <h2 class="mt-3">Connexion</h2>
                        </div>
                        
                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post" action="login.php">
                            <div class="mb-3">
                                <label for="username" class="form-label">Nom d'utilisateur</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Se connecter</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>