<?php
// Inclusion des fonctions communes
require_once 'includes/functions.php';

// Initialisation de la session sécurisée
init_secure_session();

// Inclusion de la structure HTML de base
require_once 'includes/head.php';

// Récupération du rôle de l'utilisateur
$role = $_SESSION['role'] ?? '';

// Vérifier si l'utilisateur a un compte adhérent lié
$has_adherent_account = false;
$adherent_principal_id = null;
if (isset($_SESSION['user_id'])) {
    require_once 'includes/db_connect.php';
    
    // Récupérer les adhérents liés à cet utilisateur
    $adherents_lies = get_user_linked_adherents($conn, $_SESSION['user_id']);
    
    if (!empty($adherents_lies)) {
        $has_adherent_account = true;
        // Prendre le premier adhérent (principal ou premier par ordre alphabétique)
        $first_adherent = $adherents_lies[0];
        $adherent_principal_id = $first_adherent['adherent_id'];
        
        // Stocker l'ID de l'adhérent principal dans la session pour compatibilité
        $_SESSION['adherent_id'] = $adherent_principal_id;
    } else {
        // Supprimer l'adherent_id de la session s'il n'y a plus de liaison
        unset($_SESSION['adherent_id']);
    }
}

// Récupération du logo depuis le fichier de configuration
$logo_file = 'config/logo.txt';
$default_logo = 'img/default_logo.svg';

if (file_exists($logo_file)) {
    $logo_name = file_get_contents($logo_file);
    $logo_path = 'uploads/logo/' . $logo_name;
    
    // Vérification si le fichier existe
    if (!file_exists($logo_path)) {
        $logo_path = $default_logo;
    }
} else {
    $logo_path = $default_logo;
}

// Vérifier si l'utilisateur est connecté
$is_logged_in = isset($_SESSION['user_id']);
?>

<header class="sticky-top">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Asso Vélo" height="40">
                Asso Vélo
            </a>
            
            <?php if ($is_logged_in): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Accueil</a>
                    </li>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="adherent.php">Adhérents</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="evenement.php">Événements</a>
                        </li>
                    <?php endif; ?>
                    <?php if ($has_adherent_account): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="mes_evenements.php">Mes Événements</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profil.php">Mon Profil</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="documents.php">Documents</a>
                    </li>
                    <?php if ($role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php">Administration</a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Bonjour, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Utilisateur'); ?>
                    </span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">Déconnexion</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </nav>
</header>