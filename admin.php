<?php
session_start();

// Inclusion des fonctions communes
require_once 'includes/functions.php';

// Vérification de l'authentification et des droits d'accès
check_authentication('admin');

// Configuration de la page
$page_title = 'Asso Vélo - Administration';

// Inclusion de la connexion à la base de données
require_once 'includes/db_connect.php';

// Définition des constantes
define('LOGO_DIR', 'uploads/logo/');
define('DEFAULT_LOGO', 'default_logo.png');

// Création du répertoire de logo s'il n'existe pas
if (!file_exists(LOGO_DIR)) {
    mkdir(LOGO_DIR, 0777, true);
}

// Traitement AJAX pour la gestion des utilisateurs
if (isset($_POST['action'])) {
    // Ajout du débogage
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => '', 'debug' => []];
    
    // Log de débogage
    $response['debug']['action'] = $_POST['action'] ?? 'non définie';
    $response['debug']['post_data'] = $_POST;
    
    try {
        // Ajout d'un utilisateur
        if ($_POST['action'] === 'add_user') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';
            
            if (empty($username) || empty($email) || empty($password)) {
                $response['message'] = 'Tous les champs sont obligatoires';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = 'Adresse email invalide';
            } else {
                // Vérification de l'unicité du nom d'utilisateur et de l'email
                $stmt = $conn->prepare("SELECT id FROM utilisateurs WHERE username = ? OR email = ?");
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $response['message'] = 'Ce nom d\'utilisateur ou cette adresse email existe déjà';
                } else {
                    // Hashage du mot de passe
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insertion dans la base de données
                    $stmt = $conn->prepare("INSERT INTO utilisateurs (username, mot_de_passe, email, role) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $username, $hashedPassword, $email, $role);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Utilisateur créé avec succès';
                        $response['id'] = $conn->insert_id;
                    } else {
                        $response['message'] = 'Erreur lors de la création : ' . $stmt->error;
                    }
                }
                $stmt->close();
            }
        }
        
        // Modification d'un utilisateur
        elseif ($_POST['action'] === 'edit_user') {
            $id = $_POST['id'] ?? 0;
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'user';
            
            if (empty($id) || empty($username) || empty($email)) {
                $response['message'] = 'Tous les champs sont obligatoires';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = 'Adresse email invalide';
            } else {
                // Vérification de l'unicité (en excluant l'utilisateur actuel)
                $stmt = $conn->prepare("SELECT id FROM utilisateurs WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->bind_param("ssi", $username, $email, $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $response['message'] = 'Ce nom d\'utilisateur ou cette adresse email existe déjà';
                } else {
                    // Mise à jour dans la base de données
                    $stmt = $conn->prepare("UPDATE utilisateurs SET username = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $username, $email, $role, $id);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Utilisateur modifié avec succès';
                    } else {
                        $response['message'] = 'Erreur lors de la modification : ' . $stmt->error;
                    }
                }
                $stmt->close();
            }
        }
        
        // Réinitialisation du mot de passe
        elseif ($_POST['action'] === 'reset_password') {
            $id = $_POST['id'] ?? 0;
            $newPassword = $_POST['new_password'] ?? '';
            
            if (empty($id) || empty($newPassword)) {
                $response['message'] = 'ID utilisateur et nouveau mot de passe requis';
            } else {
                // Hashage du nouveau mot de passe
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Mise à jour du mot de passe
                $stmt = $conn->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?");
                $stmt->bind_param("si", $hashedPassword, $id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Mot de passe réinitialisé avec succès';
                } else {
                    $response['message'] = 'Erreur lors de la réinitialisation : ' . $stmt->error;
                }
                $stmt->close();
            }
        }
        
        // Suppression d'un utilisateur
        elseif ($_POST['action'] === 'delete_user') {
            $id = $_POST['id'] ?? 0;
            
            if (empty($id)) {
                $response['message'] = 'ID de l\'utilisateur manquant';
            } elseif ($id == $_SESSION['user_id']) {
                $response['message'] = 'Vous ne pouvez pas supprimer votre propre compte';
            } else {
                $stmt = $conn->prepare("DELETE FROM utilisateurs WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Utilisateur supprimé avec succès';
                } else {
                    $response['message'] = 'Erreur lors de la suppression : ' . $stmt->error;
                }
                $stmt->close();
            }
        }
        
        // Récupération des utilisateurs avec les informations d'adhérent
        elseif ($_POST['action'] === 'get_users') {
            $stmt = $conn->prepare("
                SELECT u.id, u.username, u.email, u.role, u.date_creation,
                       GROUP_CONCAT(CONCAT(a.nom, ' ', a.prenom) SEPARATOR ', ') as adherents_names,
                       GROUP_CONCAT(a.id SEPARATOR ',') as adherents_ids
                FROM utilisateurs u
                LEFT JOIN utilisateurs_adherents ua ON u.id = ua.utilisateur_id
                LEFT JOIN adherents a ON ua.adherent_id = a.id
                GROUP BY u.id, u.username, u.email, u.role, u.date_creation
                ORDER BY u.username ASC
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $users = [];
            while ($row = $result->fetch_assoc()) {
                // Formatage de la date
                $date = new DateTime($row['date_creation']);
                $row['date_formatted'] = $date->format('d/m/Y H:i');
                $row['adherents_names'] = $row['adherents_names'] ?: null;
                $row['adherents_ids'] = $row['adherents_ids'] ? explode(',', $row['adherents_ids']) : [];
                $users[] = $row;
            }
            
            $response['success'] = true;
            $response['users'] = $users;
            $stmt->close();
        }
        
        // Gestion des catégories
        elseif ($_POST['action'] === 'get_categories') {
            $stmt = $conn->prepare("SELECT id, nom, description, couleur, actif, date_creation FROM categories ORDER BY nom ASC");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $categories = [];
            while ($row = $result->fetch_assoc()) {
                $date = new DateTime($row['date_creation']);
                $row['date_formatted'] = $date->format('d/m/Y H:i');
                $categories[] = $row;
            }
            
            $response['success'] = true;
            $response['categories'] = $categories;
            $stmt->close();
        }
        
        elseif ($_POST['action'] === 'add_category') {
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $couleur = $_POST['couleur'] ?? '#007bff';
            $actif = isset($_POST['actif']) ? 1 : 0;
            
            if (empty($nom)) {
                $response['message'] = 'Le nom de la catégorie est obligatoire';
            } else {
                // Vérification de l'unicité du nom
                $stmt = $conn->prepare("SELECT id FROM categories WHERE nom = ?");
                $stmt->bind_param("s", $nom);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $response['message'] = 'Cette catégorie existe déjà';
                } else {
                    $stmt->close();
                    
                    // Insertion dans la base de données
                    $stmt = $conn->prepare("INSERT INTO categories (nom, description, couleur, actif) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $nom, $description, $couleur, $actif);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Catégorie créée avec succès';
                        $response['id'] = $conn->insert_id;
                    } else {
                        $response['message'] = 'Erreur lors de la création : ' . $stmt->error;
                    }
                }
                $stmt->close();
            }
        }
        
        elseif ($_POST['action'] === 'edit_category') {
            $id = $_POST['id'] ?? 0;
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $couleur = $_POST['couleur'] ?? '#007bff';
            $actif = isset($_POST['actif']) ? 1 : 0;
            
            if (empty($id) || empty($nom)) {
                $response['message'] = 'ID et nom de la catégorie sont obligatoires';
            } else {
                // Vérification de l'unicité (en excluant la catégorie actuelle)
                $stmt = $conn->prepare("SELECT id FROM categories WHERE nom = ? AND id != ?");
                $stmt->bind_param("si", $nom, $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $response['message'] = 'Cette catégorie existe déjà';
                } else {
                    $stmt->close();
                    
                    // Mise à jour dans la base de données
                    $stmt = $conn->prepare("UPDATE categories SET nom = ?, description = ?, couleur = ?, actif = ? WHERE id = ?");
                    $stmt->bind_param("sssii", $nom, $description, $couleur, $actif, $id);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Catégorie modifiée avec succès';
                    } else {
                        $response['message'] = 'Erreur lors de la modification : ' . $stmt->error;
                    }
                }
                $stmt->close();
            }
        }
        
        elseif ($_POST['action'] === 'delete_category') {
            $id = $_POST['id'] ?? 0;
            
            if (empty($id)) {
                $response['message'] = 'ID de la catégorie manquant';
            } else {
                // Vérifier si des adhérents utilisent cette catégorie
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM adherent_categories WHERE categorie_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $count = $result->fetch_assoc()['count'];
                $stmt->close();
                
                if ($count > 0) {
                    $response['message'] = "Impossible de supprimer cette catégorie car $count adhérent(s) l'utilisent";
                } else {
                    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Catégorie supprimée avec succès';
                    } else {
                        $response['message'] = 'Erreur lors de la suppression : ' . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
        
        // Gestion des tâches
        elseif ($_POST['action'] === 'get_taches') {
            $stmt = $conn->prepare("SELECT id, nom, description, couleur, actif, date_creation FROM taches ORDER BY nom ASC");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $taches = [];
            while ($row = $result->fetch_assoc()) {
                $date = new DateTime($row['date_creation']);
                $row['date_formatted'] = $date->format('d/m/Y H:i');
                $taches[] = $row;
            }
            
            $response['success'] = true;
            $response['taches'] = $taches;
            $stmt->close();
        }
        
        elseif ($_POST['action'] === 'add_tache') {
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $couleur = $_POST['couleur'] ?? '#007bff';
            $actif = isset($_POST['actif']) ? 1 : 0;
            
            if (empty($nom)) {
                $response['message'] = 'Le nom de la tâche est obligatoire';
            } else {
                // Vérification de l'unicité du nom
                $stmt = $conn->prepare("SELECT id FROM taches WHERE nom = ?");
                $stmt->bind_param("s", $nom);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $response['message'] = 'Cette tâche existe déjà';
                } else {
                    $stmt->close();
                    
                    // Insertion dans la base de données
                    $stmt = $conn->prepare("INSERT INTO taches (nom, description, couleur, actif) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $nom, $description, $couleur, $actif);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Tâche créée avec succès';
                        $response['id'] = $conn->insert_id;
                    } else {
                        $response['message'] = 'Erreur lors de la création : ' . $stmt->error;
                    }
                }
                $stmt->close();
            }
        }
        
        elseif ($_POST['action'] === 'edit_tache') {
            $id = $_POST['id'] ?? 0;
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $couleur = $_POST['couleur'] ?? '#007bff';
            $actif = isset($_POST['actif']) ? 1 : 0;
            
            if (empty($id) || empty($nom)) {
                $response['message'] = 'Données manquantes';
            } else {
                // Vérification de l'unicité du nom (sauf pour la tâche actuelle)
                $stmt = $conn->prepare("SELECT id FROM taches WHERE nom = ? AND id != ?");
                $stmt->bind_param("si", $nom, $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $response['message'] = 'Cette tâche existe déjà';
                } else {
                    $stmt->close();
                    
                    // Mise à jour dans la base de données
                    $stmt = $conn->prepare("UPDATE taches SET nom = ?, description = ?, couleur = ?, actif = ? WHERE id = ?");
                    $stmt->bind_param("sssii", $nom, $description, $couleur, $actif, $id);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Tâche modifiée avec succès';
                    } else {
                        $response['message'] = 'Erreur lors de la modification : ' . $stmt->error;
                    }
                }
                $stmt->close();
            }
        }
        
        elseif ($_POST['action'] === 'delete_tache') {
            $id = $_POST['id'] ?? 0;
            
            if (empty($id)) {
                $response['message'] = 'ID de la tâche manquant';
            } else {
                // Vérifier si des événements utilisent cette tâche
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM evenement_taches_adherents WHERE tache_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $count = $result->fetch_assoc()['count'];
                $stmt->close();
                
                if ($count > 0) {
                    $response['message'] = "Impossible de supprimer cette tâche car elle est utilisée dans $count assignation(s)";
                } else {
                    $stmt = $conn->prepare("DELETE FROM taches WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Tâche supprimée avec succès';
                    } else {
                        $response['message'] = 'Erreur lors de la suppression : ' . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
        
        // Récupération des adhérents pour la liste déroulante
        elseif ($_POST['action'] === 'get_adherents') {
            $stmt = $conn->prepare("SELECT id, nom, prenom FROM adherents ORDER BY nom ASC, prenom ASC");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $adherents = [];
            while ($row = $result->fetch_assoc()) {
                $adherents[] = $row;
            }
            
            $response['success'] = true;
            $response['adherents'] = $adherents;
            $stmt->close();
        }
        
        // Ajouter une nouvelle fonction pour supprimer une liaison
        elseif ($_POST['action'] === 'unlink_user_adherent') {
            $user_id = $_POST['user_id'] ?? 0;
            $adherent_id = $_POST['adherent_id'] ?? 0;
            
            if (empty($user_id) || empty($adherent_id)) {
                $response['message'] = 'ID utilisateur et adhérent requis';
            } else {
                $stmt = $conn->prepare("DELETE FROM utilisateurs_adherents WHERE utilisateur_id = ? AND adherent_id = ?");
                $stmt->bind_param("ii", $user_id, $adherent_id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Liaison supprimée avec succès';
                } else {
                    $response['message'] = 'Erreur lors de la suppression : ' . $stmt->error;
                }
                $stmt->close();
            }
        }
        
        // Ajouter une fonction pour obtenir les liaisons d'un utilisateur
        elseif ($_POST['action'] === 'get_user_adherents') {
            $user_id = $_POST['user_id'] ?? 0;
            
            if (empty($user_id)) {
                $response['message'] = 'ID utilisateur manquant';
            } else {
                $stmt = $conn->prepare("
                    SELECT a.id, a.nom, a.prenom, ua.principal
                    FROM utilisateurs_adherents ua
                    JOIN adherents a ON ua.adherent_id = a.id
                    WHERE ua.utilisateur_id = ?
                    ORDER BY ua.principal DESC, a.nom ASC, a.prenom ASC
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $adherents = [];
                while ($row = $result->fetch_assoc()) {
                    $adherents[] = $row;
                }
                
                $response['success'] = true;
                $response['adherents'] = $adherents;
                $stmt->close();
            }
        }
        
        // Liaison utilisateur-adhérent
        elseif ($_POST['action'] === 'link_user_adherent') {
            $user_id = $_POST['user_id'] ?? 0;
            $adherent_id = $_POST['adherent_id'] ?? null;
            
            if (empty($user_id)) {
                $response['message'] = 'ID utilisateur manquant';
            } elseif (empty($adherent_id)) {
                $response['message'] = 'ID adhérent manquant';
            } else {
                // Vérifier si la liaison existe déjà
                $stmt = $conn->prepare("SELECT id FROM utilisateurs_adherents WHERE utilisateur_id = ? AND adherent_id = ?");
                $stmt->bind_param("ii", $user_id, $adherent_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $response['message'] = 'Cette liaison existe déjà';
                } else {
                    $stmt->close();
                    
                    // Créer la nouvelle liaison
                    $stmt = $conn->prepare("INSERT INTO utilisateurs_adherents (utilisateur_id, adherent_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $user_id, $adherent_id);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Liaison créée avec succès';
                    } else {
                        $response['message'] = 'Erreur lors de la création : ' . $stmt->error;
                    }
                }
                $stmt->close();
            }
        }
        
        // Récupération des statistiques de participation
        elseif ($_POST['action'] === 'get_participation_stats') {
            // Récupération des événements avec nombre de participants souhaité défini
            $stmt = $conn->prepare("
                SELECT 
                    e.id,
                    e.titre,
                    e.date,
                    e.nombre_participants_souhaite,
                    COUNT(CASE WHEN p.statut = 'confirmé' THEN 1 END) as participants_confirmes
                FROM evenements e
                LEFT JOIN participation p ON e.id = p.evenement_id
                WHERE e.nombre_participants_souhaite IS NOT NULL 
                    AND e.nombre_participants_souhaite > 0
                    AND e.date >= CURDATE()
                GROUP BY e.id, e.titre, e.date, e.nombre_participants_souhaite
                ORDER BY e.date ASC
            ");
            
            if (!$stmt) {
                throw new Exception('Erreur de préparation de la requête : ' . $conn->error);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $events = [];
            while ($row = $result->fetch_assoc()) {
                // Formatage de la date
                $date = new DateTime($row['date']);
                $row['date_formatted'] = $date->format('d/m/Y');
                $events[] = $row;
            }
            
            $response['success'] = true;
            $response['events'] = $events;
            $stmt->close();
        }
    } catch (Exception $e) {
        $response['message'] = 'Erreur : ' . $e->getMessage();
        $response['debug']['exception'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Initialisation des variables pour le logo
$message = '';
$messageType = '';
$currentLogo = '';

// Récupération du logo actuel
$logoFile = 'config/logo.txt';
if (file_exists($logoFile)) {
    $currentLogo = file_get_contents($logoFile);
} else {
    // Création du fichier de configuration s'il n'existe pas
    if (!file_exists('config')) {
        mkdir('config', 0777, true);
    }
    file_put_contents($logoFile, DEFAULT_LOGO);
    $currentLogo = DEFAULT_LOGO;
}

// Traitement du formulaire de modification du logo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $fileInfo = pathinfo($_FILES['logo']['name']);
        $extension = strtolower($fileInfo['extension']);
        
        // Vérification de l'extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        if (in_array($extension, $allowedExtensions)) {
            // Génération d'un nom de fichier unique
            $newFileName = 'logo_' . time() . '.' . $extension;
            $uploadPath = LOGO_DIR . $newFileName;
            
            // Déplacement du fichier uploadé
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                // Mise à jour du fichier de configuration
                file_put_contents($logoFile, $newFileName);
                $currentLogo = $newFileName;
                $message = 'Le logo a été mis à jour avec succès.';
                $messageType = 'success';
            } else {
                $message = 'Erreur lors du téléchargement du logo.';
                $messageType = 'danger';
            }
        } else {
            $message = 'Extension de fichier non autorisée. Utilisez JPG, JPEG, PNG, GIF ou SVG.';
            $messageType = 'danger';
        }
    } elseif ($_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Gestion des erreurs d'upload
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par PHP.',
            UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le formulaire.',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pas de répertoire temporaire.',
            UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque.',
            UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté le téléchargement du fichier.'
        ];
        
        $errorCode = $_FILES['logo']['error'];
        $message = isset($uploadErrors[$errorCode]) ? $uploadErrors[$errorCode] : 'Erreur inconnue lors du téléchargement.';
        $messageType = 'danger';
    }
}

// Chemin complet du logo actuel
$logoPath = file_exists(LOGO_DIR . $currentLogo) ? LOGO_DIR . $currentLogo : LOGO_DIR . DEFAULT_LOGO;
if (!file_exists($logoPath)) {
    $logoPath = 'img/' . DEFAULT_LOGO; // Fallback vers le logo par défaut dans le dossier img
}
?>
<?php include 'includes/header.php'; ?>



    <div class="container mt-4">
        <h1>Administration</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>
        
        <!-- Navigation par onglets -->
        <ul class="nav nav-tabs" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab" aria-controls="users" aria-selected="true">
                    <i class="fas fa-users"></i> Gestion des utilisateurs
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="logo-tab" data-bs-toggle="tab" data-bs-target="#logo" type="button" role="tab" aria-controls="logo" aria-selected="false">
                    <i class="fas fa-image"></i> Logo
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="adherents-tab" data-bs-toggle="tab" data-bs-target="#adherents" type="button" role="tab" aria-controls="adherents" aria-selected="false">
                    <i class="fas fa-id-card"></i> Adhérents
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab" aria-controls="categories" aria-selected="false">
                    <i class="fas fa-tags"></i> Catégories
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="evenements-tab" data-bs-toggle="tab" data-bs-target="#evenements" type="button" role="tab" aria-controls="evenements" aria-selected="false">
                    <i class="fas fa-calendar-alt"></i> Événements
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="taches-tab" data-bs-toggle="tab" data-bs-target="#taches" type="button" role="tab" aria-controls="taches" aria-selected="false">
                    <i class="fas fa-tasks"></i> Tâches
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="adminTabsContent">
            <!-- Onglet Gestion des utilisateurs -->
            <div class="tab-pane fade show active" id="users" role="tabpanel" aria-labelledby="users-tab">
                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Gestion des utilisateurs</h3>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus"></i> Ajouter un utilisateur
                        </button>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th>Nom d'utilisateur</th>
                                            <th>Email</th>
                                            <th>Rôle</th>
                                            <!-- <th>Adhérent lié</th> -->
                                            <th>Date de création</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Les utilisateurs seront chargés ici via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Logo -->
            <div class="tab-pane fade" id="logo" role="tabpanel" aria-labelledby="logo-tab">
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Modification du logo</h5>
                            </div>
                            <div class="card-body">
                                <form action="admin.php" method="post" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="logo" class="form-label">Nouveau logo</label>
                                        <input type="file" class="form-control" id="logo" name="logo" accept="image/*" required>
                                        <div class="form-text">Formats acceptés : JPG, JPEG, PNG, GIF, SVG</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Logo actuel</h5>
                            </div>
                            <div class="card-body text-center">
                                <img src="<?php echo $logoPath; ?>" alt="Logo Asso Vélo" class="img-fluid logo-image">
                                <p class="mt-3">Nom du fichier : <?php echo $currentLogo; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Adhérents -->
            <div class="tab-pane fade" id="adherents" role="tabpanel" aria-labelledby="adherents-tab">
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>Outils d'exportation</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h5 class="card-title">Exporter tous les adhérents en PDF</h5>
                                                <p class="card-text">Générer un PDF contenant la liste complète des adhérents avec leurs informations.</p>
                                                <a href="export_all_adherents_pdf.php" target="_blank" class="btn btn-primary">
                                                    <i class="fas fa-file-pdf"></i> Exporter en PDF
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h5 class="card-title">Exporter tous les adhérents en CSV</h5>
                                                <p class="card-text">Générer un fichier CSV contenant la liste complète des adhérents pour Excel.</p>
                                                <a href="export_all_adherents_csv.php" class="btn btn-success">
                                                    <i class="fas fa-file-csv"></i> Exporter en CSV
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h5 class="card-title">Gestion des mailings</h5>
                                                <p class="card-text">Gérer les adresses email des adhérents avec filtres par catégorie pour les mailings.</p>
                                                <a href="mailing.php" class="btn btn-info">
                                                    <i class="fas fa-envelope"></i> Gérer les mailings
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h5 class="card-title">Exporter le trombinoscope en PDF</h5>
                                                <p class="card-text">Générer un PDF contenant les photos, noms et prénoms de tous les adhérents.</p>
                                                <a href="export_trombinoscope_pdf.php" target="_blank" class="btn btn-primary">
                                                    <i class="fas fa-id-card"></i> Exporter le trombinoscope
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Catégories -->
            <div class="tab-pane fade" id="categories" role="tabpanel" aria-labelledby="categories-tab">
                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Gestion des catégories</h3>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus"></i> Ajouter une catégorie
                        </button>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="categoriesTable">
                                    <thead>
                                        <tr>
                                            <th>Nom</th>
                                            <th>Description</th>
                                            <th>Couleur</th>
                                            <th>Statut</th>
                                            <th>Date de création</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Les catégories seront chargées ici via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglet Événements -->
            <div class="tab-pane fade" id="evenements" role="tabpanel" aria-labelledby="evenements-tab">
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>Outils d'exportation des événements</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h5 class="card-title">Exporter les événements à venir en PDF</h5>
                                                <p class="card-text">Générer un PDF contenant tous les événements à venir au format calendrier.</p>
                                                <a href="export_evenements_calendrier_pdf.php" target="_blank" class="btn btn-primary">
                                                    <i class="fas fa-calendar-alt"></i> Exporter le calendrier
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Section Analyse des participations -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>Analyse des participations aux événements</h5>
                            </div>
                            <div class="card-body">
                                <div id="participationCharts">
                                    <div class="text-center">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Chargement...</span>
                                        </div>
                                        <p class="mt-2">Chargement des données de participation...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Tâches -->
            <div class="tab-pane fade" id="taches" role="tabpanel" aria-labelledby="taches-tab">
                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Gestion des tâches</h3>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTacheModal">
                            <i class="fas fa-plus"></i> Ajouter une tâche
                        </button>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="tachesTable">
                                    <thead>
                                        <tr>
                                            <th>Nom</th>
                                            <th>Description</th>
                                            <th>Couleur</th>
                                            <th>Statut</th>
                                            <th>Date de création</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Les tâches seront chargées ici via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Inclusion de Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    // Fonction pour charger les données de participation
    function loadParticipationData() {
        const formData = new FormData();
        formData.append('action', 'get_participation_stats');
        
        fetch('admin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayParticipationCharts(data.events);
            } else {
                document.getElementById('participationCharts').innerHTML = 
                    '<div class="alert alert-danger">Erreur lors du chargement des données: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            document.getElementById('participationCharts').innerHTML = 
                '<div class="alert alert-danger">Une erreur est survenue lors du chargement des données</div>';
        });
    }
    
    // Fonction pour afficher les graphiques de participation
    function displayParticipationCharts(events) {
        const chartsContainer = document.getElementById('participationCharts');
        
        if (events.length === 0) {
            chartsContainer.innerHTML = '<div class="alert alert-info">Aucun événement avec nombre de participants souhaité trouvé</div>';
            return;
        }
        
        let chartsHTML = '<div class="row">';
        
        events.forEach((event, index) => {
            const chartId = `chart_${event.id}`;
            const participants_confirmes = parseInt(event.participants_confirmes) || 0;
            const nombre_souhaite = parseInt(event.nombre_participants_souhaite) || 0;
            const places_restantes = Math.max(0, nombre_souhaite - participants_confirmes);
            
            const pourcentage = nombre_souhaite > 0 ? Math.round((participants_confirmes / nombre_souhaite) * 100) : 0;
            
            chartsHTML += `
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">${event.titre}</h6>
                            <small class="text-muted">${event.date_formatted}</small>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <canvas id="${chartId}" width="200" height="200"></canvas>
                            </div>
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="text-success">
                                        <strong>${participants_confirmes}</strong>
                                        <br><small>Confirmés</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-primary">
                                        <strong>${nombre_souhaite}</strong>
                                        <br><small>Souhaité</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-warning">
                                        <strong>${places_restantes}</strong>
                                        <br><small>Restantes</small>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-2">
                                <span class="badge ${pourcentage >= 100 ? 'bg-success' : pourcentage >= 75 ? 'bg-warning' : 'bg-danger'}">
                                    ${pourcentage}% rempli
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        chartsHTML += '</div>';
        chartsContainer.innerHTML = chartsHTML;
        
        // Créer les graphiques
        events.forEach(event => {
            const chartId = `chart_${event.id}`;
            const ctx = document.getElementById(chartId).getContext('2d');
            
            const participants_confirmes = parseInt(event.participants_confirmes) || 0;
            const nombre_souhaite = parseInt(event.nombre_participants_souhaite) || 0;
            const places_restantes = Math.max(0, nombre_souhaite - participants_confirmes);
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Participants confirmés', 'Places restantes'],
                    datasets: [{
                        data: [participants_confirmes, places_restantes],
                        backgroundColor: [
                            '#28a745',  // Vert pour les confirmés
                            '#ffc107'   // Jaune pour les places restantes
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 10,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = nombre_souhaite;
                                    const value = context.parsed;
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return context.label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        });
    }
    
    // Charger les données au chargement de la page et lors du clic sur l'onglet événements
    document.addEventListener('DOMContentLoaded', function() {
        const evenementsTab = document.getElementById('evenements-tab');
        if (evenementsTab) {
            evenementsTab.addEventListener('click', function() {
                setTimeout(loadParticipationData, 100);
            });
        }
    });
    </script>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ajouter un utilisateur -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Ajouter un utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm">
                        <div class="mb-3">
                            <label for="add_username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="add_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_email" class="form-label">Adresse email *</label>
                            <input type="email" class="form-control" id="add_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_password" class="form-label">Mot de passe *</label>
                            <input type="password" class="form-control" id="add_password" name="password" required>
                            <div class="form-text">Minimum 6 caractères</div>
                        </div>
                        <div class="mb-3">
                            <label for="add_role" class="form-label">Rôle</label>
                            <select class="form-select" id="add_role" name="role">
                                <option value="user">Utilisateur</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="button" class="btn btn-primary" onclick="addUser()">
                        <i class="fas fa-plus"></i> Ajouter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Modifier un utilisateur -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Modifier un utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" id="edit_user_id" name="id">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Adresse email *</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Rôle</label>
                            <select class="form-select" id="edit_role" name="role">
                                <option value="user">Utilisateur</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="button" class="btn btn-primary" onclick="editUser()">
                        <i class="fas fa-save"></i> Modifier
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Réinitialiser le mot de passe -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">Réinitialiser le mot de passe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <form id="resetPasswordForm">
                        <input type="hidden" id="reset_user_id" name="id">
                        <p>Utilisateur : <strong id="reset_username"></strong></p>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nouveau mot de passe *</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="form-text">Minimum 6 caractères</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmer le mot de passe *</label>
                            <input type="password" class="form-control" id="confirm_password" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="button" class="btn btn-warning" onclick="resetPassword()">
                        <i class="fas fa-key"></i> Réinitialiser
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de liaison utilisateur-adhérent -->
    <!-- Modal linkUserModal commenté
    <div class="modal fade" id="linkUserModal" tabindex="-1" aria-labelledby="linkUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="linkUserModalLabel">Lier un utilisateur à un adhérent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <form id="linkUserForm">
                        <input type="hidden" id="link_user_id" name="user_id">
                        <div class="mb-3">
                            <label for="link_username" class="form-label">Utilisateur</label>
                            <input type="text" class="form-control" id="link_username" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="link_adherent_id" class="form-label">Adhérent</label>
                            <select class="form-select" id="link_adherent_id" name="adherent_id">
                                <option value="">Aucun adhérent lié</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="button" class="btn btn-primary" onclick="linkUser()">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>
    --> 

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" crossorigin="anonymous"></script>
    
    <script>
    // Variables globales
let users = [];
let categories = [];
    
    // Chargement initial
    // Ajouter après le DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        loadUsers();
        
        // Écouter la fermeture des modales pour recharger
        document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function () {
            // Vérifier si l'ajout a été réussi (vous pouvez utiliser une variable globale)
            if (window.lastActionSuccess) {
                loadUsers();
                window.lastActionSuccess = false;
            }
        });
    });
    
    // Fonction pour charger les utilisateurs
    function loadUsers() {
        fetch('admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_users'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                users = data.users;
                displayUsers();
            } else {
                showAlert('Erreur lors du chargement des utilisateurs: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('Erreur de communication avec le serveur', 'danger');
        });
    }
    
    // Fonction pour afficher les utilisateurs
    function displayUsers() {
        const tbody = document.querySelector('#usersTable tbody');
        tbody.innerHTML = '';
        
        users.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${escapeHtml(user.username)}</td>
                <td>${escapeHtml(user.email)}</td>
                <td>
                    <span class="badge ${user.role === 'admin' ? 'bg-danger' : 'bg-primary'}">
                        ${user.role === 'admin' ? 'Administrateur' : 'Utilisateur'}
                    </span>
                </td>
                <!-- <td>
                    ${user.adherents_names ? 
                        `<div class="adherents-list">
                            ${user.adherents_names.split(', ').map(name => 
                                `<span class="badge bg-secondary me-1">${escapeHtml(name)}</span>`
                            ).join('')}
                        </div>` : 
                        '<em>Aucun</em>'
                    }
                </td> -->
                <td>${user.date_formatted}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="openEditModal(${user.id})" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <!-- <button type="button" class="btn btn-outline-info" onclick="openManageLinksModal(${user.id}, '${escapeHtml(user.username)}')" title="Gérer les liaisons">
                            <i class="fas fa-link"></i>
                        </button> -->
                        <button type="button" class="btn btn-outline-warning" onclick="openResetPasswordModal(${user.id})" title="Réinitialiser le mot de passe">
                            <i class="fas fa-key"></i>
                        </button>
                        ${user.id != <?php echo $_SESSION['user_id']; ?> ? `
                        <button type="button" class="btn btn-outline-danger" onclick="deleteUser(${user.id})" title="Supprimer">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });
    }
    
    // Fonction pour ouvrir le modal d'édition
    function openEditModal(userId) {
        const user = users.find(u => u.id == userId);
        if (user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }
    }
    
    // Fonction pour ouvrir le modal de réinitialisation de mot de passe
    function openResetPasswordModal(userId) {
        const user = users.find(u => u.id == userId);
        if (user) {
            document.getElementById('reset_user_id').value = user.id;
            document.getElementById('reset_username').textContent = user.username;
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
            modal.show();
        }
    }
    
    // Fonction pour ajouter un utilisateur
    function addUser() {
        const form = document.getElementById('addUserForm');
        const formData = new FormData(form);
        formData.append('action', 'add_user');
        
        // Validation du mot de passe
        const password = formData.get('password');
        if (password.length < 6) {
            showAlert('Le mot de passe doit contenir au moins 6 caractères', 'danger');
            return;
        }
        
        // Désactiver le bouton pendant la requête
        const submitBtn = document.querySelector('#addUserModal .btn-primary');
        const originalContent = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
        
        fetch('admin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Fermer la modal immédiatement
                bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
                // Recharger la page entière pour éviter les problèmes
                window.location.reload();
            } else {
                showAlert(data.message, 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalContent;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('Erreur de communication avec le serveur', 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalContent;
        });
    }
    
    // Fonction pour modifier un utilisateur
    function editUser() {
        const form = document.getElementById('editUserForm');
        const formData = new FormData(form);
        formData.append('action', 'edit_user');
        
        // Désactiver le bouton pendant la requête
        const submitBtn = document.querySelector('#editUserModal .btn-primary');
        const originalContent = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Modification...';
        
        fetch('admin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
                loadUsers(); // Recharger la liste
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('Erreur de communication avec le serveur', 'danger');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalContent;
        });
    }
    
    // Fonction pour réinitialiser le mot de passe
    function resetPassword() {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (newPassword.length < 6) {
            showAlert('Le mot de passe doit contenir au moins 6 caractères', 'danger');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            showAlert('Les mots de passe ne correspondent pas', 'danger');
            return;
        }
        
        // Désactiver le bouton pendant la requête
        const submitBtn = document.querySelector('#resetPasswordModal .btn-warning');
        const originalContent = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Réinitialisation...';
        
        const formData = new FormData();
        formData.append('action', 'reset_password');
        formData.append('id', document.getElementById('reset_user_id').value);
        formData.append('new_password', newPassword);
        
        fetch('admin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('resetPasswordModal')).hide();
                loadUsers(); // Recharger la liste
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('Erreur de communication avec le serveur', 'danger');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalContent;
        });
    }
    
    // Fonction pour supprimer un utilisateur
    function deleteUser(userId) {
        const user = users.find(u => u.id == userId);
        if (user && confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur "${user.username}" ?`)) {
            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('id', userId);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    loadUsers(); // Recharger immédiatement
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showAlert('Erreur de communication avec le serveur', 'danger');
            });
        }
    }
    
    // Fonction pour afficher les alertes
    function showAlert(message, type) {
        // Supprimer les alertes existantes
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());
        
        // Créer la nouvelle alerte
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        `;
        
        // Insérer l'alerte au début du container
        const container = document.querySelector('.container');
        container.insertBefore(alertDiv, container.children[1]);
        
        // Auto-suppression après 5 secondes
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
    
    // Fonction pour charger les catégories
function loadCategories() {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_categories'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            categories = data.categories;
            displayCategories();
        } else {
            showAlert('Erreur lors du chargement des catégories: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showAlert('Erreur de communication avec le serveur', 'danger');
    });
}

// Fonction pour afficher les catégories
function displayCategories() {
    const tbody = document.querySelector('#categoriesTable tbody');
    tbody.innerHTML = '';
    
    categories.forEach(category => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(category.nom)}</td>
            <td>${escapeHtml(category.description || '')}</td>
            <td>
                <div class="color-preview" style="background-color: ${category.couleur}; width: 20px; height: 20px; border-radius: 3px; border: 1px solid #dee2e6;"></div>
            </td>
            <td>
                <span class="badge ${category.actif ? 'bg-success' : 'bg-secondary'}">
                    ${category.actif ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td>${category.date_formatted}</td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary" onclick="openEditCategoryModal(${category.id})" title="Modifier">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteCategory(${category.id})" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}



// Fonction pour ouvrir le modal d'édition
function openEditCategoryModal(categoryId) {
    const category = categories.find(c => c.id == categoryId);
    if (category) {
        document.getElementById('edit_category_id').value = category.id;
        document.getElementById('edit_category_nom').value = category.nom;
        document.getElementById('edit_category_description').value = category.description || '';
        document.getElementById('edit_category_couleur').value = category.couleur;
        document.getElementById('edit_category_actif').checked = category.actif == 1;
        
        const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
        modal.show();
    }
}

// Fonction pour modifier une catégorie
function editCategory() {
    const form = document.getElementById('editCategoryForm');
    const formData = new FormData(form);
    formData.append('action', 'edit_category');
    
    const submitBtn = document.querySelector('#editCategoryModal .btn-primary');
    const originalContent = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Modification...';
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            // Correction : utiliser une méthode plus robuste pour fermer la modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('editCategoryModal')) || new bootstrap.Modal(document.getElementById('editCategoryModal'));
            modal.hide();
            loadCategories();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showAlert('Erreur de communication avec le serveur', 'danger');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalContent;
    });
}

// Fonction pour supprimer une catégorie
function deleteCategory(categoryId) {
    const category = categories.find(c => c.id == categoryId);
    if (category && confirm(`Êtes-vous sûr de vouloir supprimer la catégorie "${category.nom}" ?`)) {
        const formData = new FormData();
        formData.append('action', 'delete_category');
        formData.append('id', categoryId);
        
        fetch('admin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                loadCategories();
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('Erreur de communication avec le serveur', 'danger');
        });
    }
}

// Charger les catégories au clic sur l'onglet
document.getElementById('categories-tab').addEventListener('click', function() {
    loadCategories();
});

// Variables globales pour les tâches
let taches = [];

// Fonction pour charger les tâches
function loadTaches() {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_taches'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            taches = data.taches;
            displayTaches();
        } else {
            showAlert('Erreur lors du chargement des tâches', 'danger');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showAlert('Erreur de communication avec le serveur', 'danger');
    });
}

// Fonction pour afficher les tâches
function displayTaches() {
    const tbody = document.querySelector('#tachesTable tbody');
    tbody.innerHTML = '';
    
    taches.forEach(tache => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(tache.nom)}</td>
            <td>${tache.description ? escapeHtml(tache.description) : '<em>Aucune</em>'}</td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="color-preview me-2" style="width: 20px; height: 20px; background-color: ${tache.couleur}; border: 1px solid #ddd; border-radius: 3px;"></div>
                    <span>${tache.couleur}</span>
                </div>
            </td>
            <td>
                <span class="badge ${tache.actif == 1 ? 'bg-success' : 'bg-secondary'}">
                    ${tache.actif == 1 ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td>${tache.date_formatted}</td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary" onclick="openEditTacheModal(${tache.id})" title="Modifier">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteTache(${tache.id})" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Fonction pour ajouter une tâche
function addTache() {
    const form = document.getElementById('addTacheForm');
    const formData = new FormData(form);
    formData.append('action', 'add_tache');
    
    const submitBtn = document.querySelector('#addTacheModal .btn-primary');
    const originalContent = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('addTacheModal'));
            modal.hide();
            form.reset();
            loadTaches();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showAlert('Erreur de communication avec le serveur', 'danger');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalContent;
    });
}

// Fonction pour ouvrir le modal d'édition
function openEditTacheModal(tacheId) {
    const tache = taches.find(t => t.id == tacheId);
    if (tache) {
        document.getElementById('edit_tache_id').value = tache.id;
        document.getElementById('edit_tache_nom').value = tache.nom;
        document.getElementById('edit_tache_description').value = tache.description || '';
        document.getElementById('edit_tache_couleur').value = tache.couleur;
        document.getElementById('edit_tache_actif').checked = tache.actif == 1;
        
        const modal = new bootstrap.Modal(document.getElementById('editTacheModal'));
        modal.show();
    }
}

// Fonction pour modifier une tâche
function editTache() {
    const form = document.getElementById('editTacheForm');
    const formData = new FormData(form);
    formData.append('action', 'edit_tache');
    
    const submitBtn = document.querySelector('#editTacheModal .btn-primary');
    const originalContent = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Modification...';
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('editTacheModal'));
            modal.hide();
            loadTaches();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showAlert('Erreur de communication avec le serveur', 'danger');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalContent;
    });
}

// Fonction pour supprimer une tâche
function deleteTache(tacheId) {
    const tache = taches.find(t => t.id == tacheId);
    if (tache && confirm(`Êtes-vous sûr de vouloir supprimer la tâche "${tache.nom}" ?`)) {
        const formData = new FormData();
        formData.append('action', 'delete_tache');
        formData.append('id', tacheId);
        
        fetch('admin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                loadTaches();
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('Erreur de communication avec le serveur', 'danger');
        });
    }
}

// Charger les tâches au clic sur l'onglet
document.getElementById('taches-tab').addEventListener('click', function() {
    loadTaches();
});

// Charger les catégories au clic sur l'onglet
document.getElementById('categories-tab').addEventListener('click', function() {
    loadCategories();
});

// Fonction pour charger les catégories
function loadCategories() {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_categories'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            categories = data.categories;
            displayCategories();
        } else {
            showAlert('Erreur lors du chargement des catégories: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showAlert('Erreur de communication avec le serveur', 'danger');
    });
}

// Fonction pour afficher les catégories
function displayCategories() {
    const tbody = document.querySelector('#categoriesTable tbody');
    tbody.innerHTML = '';
    
    categories.forEach(category => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(category.nom)}</td>
            <td>${escapeHtml(category.description || '')}</td>
            <td>
                <div class="color-preview" style="background-color: ${category.couleur}; width: 20px; height: 20px; border-radius: 3px; border: 1px solid #dee2e6;"></div>
            </td>
            <td>
                <span class="badge ${category.actif ? 'bg-success' : 'bg-secondary'}">
                    ${category.actif ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td>${category.date_formatted}</td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary" onclick="openEditCategoryModal(${category.id})" title="Modifier">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteCategory(${category.id})" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Fonction pour ajouter une catégorie
function addCategory() {
    const form = document.getElementById('addCategoryForm');
    const formData = new FormData(form);
    formData.append('action', 'add_category');
    
    const submitBtn = document.querySelector('#addCategoryModal .btn-primary');
    const originalContent = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erreur réseau: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            
            // Fermeture propre de la modal
            const modalElement = document.getElementById('addCategoryModal');
            const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
            modal.hide();
            
            // Réinitialiser le formulaire
            form.reset();
            
            // Recharger les catégories
            loadCategories();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showAlert('Erreur de communication avec le serveur: ' + error.message, 'danger');
    })
    .finally(() => {
        // Réactiver le bouton dans tous les cas
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalContent;
    });
}

// Gestionnaire d'événement pour réinitialiser le modal d'ajout
document.addEventListener('DOMContentLoaded', function() {
    const addCategoryModal = document.getElementById('addCategoryModal');
    if (addCategoryModal) {
        addCategoryModal.addEventListener('show.bs.modal', function () {
            // Réinitialiser le formulaire à chaque ouverture
            const form = document.getElementById('addCategoryForm');
            if (form) {
                form.reset();
            }
            
            // Réinitialiser le bouton
            const submitBtn = document.querySelector('#addCategoryModal .btn-primary');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-plus"></i> Ajouter';
            }
        });
        
        addCategoryModal.addEventListener('hidden.bs.modal', function () {
            // Nettoyer complètement après fermeture
            const form = document.getElementById('addCategoryForm');
            if (form) {
                form.reset();
            }
            
            // S'assurer que le backdrop est supprimé
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            // Réinitialiser les styles du body
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        });
    }
});

// Fonction pour ouvrir le modal d'édition
function openEditCategoryModal(categoryId) {
    const category = categories.find(c => c.id == categoryId);
    if (category) {
        document.getElementById('edit_category_id').value = category.id;
        document.getElementById('edit_category_nom').value = category.nom;
        document.getElementById('edit_category_description').value = category.description || '';
        document.getElementById('edit_category_couleur').value = category.couleur;
        document.getElementById('edit_category_actif').checked = category.actif == 1;
        
        const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
        modal.show();
    }
}

// Fonction pour modifier une catégorie
function editCategory() {
    const form = document.getElementById('editCategoryForm');
    const formData = new FormData(form);
    formData.append('action', 'edit_category');
    
    const submitBtn = document.querySelector('#editCategoryModal .btn-primary');
    const originalContent = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Modification...';
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            // Fermeture forcée de la modal et suppression du backdrop
            const modalElement = document.getElementById('editCategoryModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
            // Supprimer manuellement le backdrop et les classes si nécessaire
            setTimeout(() => {
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }, 300);
            
            loadCategories();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showAlert('Erreur de communication avec le serveur', 'danger');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalContent;
    });
}

// Fonction pour supprimer une catégorie
function deleteCategory(categoryId) {
    const category = categories.find(c => c.id == categoryId);
    if (category && confirm(`Êtes-vous sûr de vouloir supprimer la catégorie "${category.nom}" ?`)) {
        const formData = new FormData();
        formData.append('action', 'delete_category');
        formData.append('id', categoryId);
        
        fetch('admin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                loadCategories();
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('Erreur de communication avec le serveur', 'danger');
        });
    }
}

// Charger les catégories au clic sur l'onglet
document.getElementById('categories-tab').addEventListener('click', function() {
    loadCategories();
});

// Fonction pour échapper le HTML
function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Fonction pour charger les adhérents dans la liste déroulante
/* function loadAdherents() {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_adherents'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('link_adherent_id');
            // Garder l'option "Aucun adhérent lié"
            select.innerHTML = '<option value="">Aucun adhérent lié</option>';
            
            data.adherents.forEach(adherent => {
                const option = document.createElement('option');
                option.value = adherent.id;
                option.textContent = `${adherent.nom} ${adherent.prenom}`;
                select.appendChild(option);
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
    });
} */

// Fonction pour charger les adhérents dans le modal de gestion
/* function loadAdherentsForManagement() {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_adherents'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('new_adherent_select');
            // Garder l'option par défaut
            select.innerHTML = '<option value="">Sélectionner un adhérent...</option>';
            
            data.adherents.forEach(adherent => {
                const option = document.createElement('option');
                option.value = adherent.id;
                option.textContent = `${adherent.nom} ${adherent.prenom}`;
                select.appendChild(option);
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
    });
}

// Fonction pour ouvrir le modal de liaison
/* function openLinkModal(userId, username, adherentId = null) {
    document.getElementById('link_user_id').value = userId;
    document.getElementById('link_username').value = username;
    
    // Charger la liste des adhérents
    loadAdherents();
    
    // Sélectionner l'adhérent actuel s'il y en a un
    setTimeout(() => {
        if (adherentId) {
            document.getElementById('link_adherent_id').value = adherentId;
        }
    }, 100);
    
    const modal = new bootstrap.Modal(document.getElementById('linkUserModal'));
    modal.show();
} */

// Nouvelle fonction pour gérer les liaisons multiples
/* function openManageLinksModal(userId, username) {
    document.getElementById('manage_user_id').value = userId;
    document.getElementById('manage_username').value = username;
    
    // Charger les adhérents disponibles et les liaisons existantes
    loadAdherentsForManagement();
    loadUserAdherents(userId);
    
    const modal = new bootstrap.Modal(document.getElementById('manageLinksModal'));
    modal.show();
} */

    </script>

<!-- Modal Ajouter une catégorie -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Ajouter une catégorie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <form id="addCategoryForm">
                    <div class="mb-3">
                        <label for="add_category_nom" class="form-label">Nom *</label>
                        <input type="text" class="form-control" id="add_category_nom" name="nom" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_category_description" class="form-label">Description</label>
                        <textarea class="form-control" id="add_category_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="add_category_couleur" class="form-label">Couleur</label>
                        <input type="color" class="form-control form-control-color" id="add_category_couleur" name="couleur" value="#007bff">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="add_category_actif" name="actif" checked>
                        <label class="form-check-label" for="add_category_actif">Catégorie active</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="button" class="btn btn-primary" onclick="addCategory()">
                    <i class="fas fa-plus"></i> Ajouter
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Modifier une catégorie -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Modifier une catégorie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <form id="editCategoryForm">
                    <input type="hidden" id="edit_category_id" name="id">
                    <div class="mb-3">
                        <label for="edit_category_nom" class="form-label">Nom *</label>
                        <input type="text" class="form-control" id="edit_category_nom" name="nom" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_category_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category_couleur" class="form-label">Couleur</label>
                        <input type="color" class="form-control form-control-color" id="edit_category_couleur" name="couleur">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_category_actif" name="actif">
                        <label class="form-check-label" for="edit_category_actif">Catégorie active</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="button" class="btn btn-primary" onclick="editCategory()">
                    <i class="fas fa-save"></i> Modifier
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajouter une tâche -->
<div class="modal fade" id="addTacheModal" tabindex="-1" aria-labelledby="addTacheModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTacheModalLabel">Ajouter une tâche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <form id="addTacheForm">
                    <div class="mb-3">
                        <label for="tache_nom" class="form-label">Nom *</label>
                        <input type="text" class="form-control" id="tache_nom" name="nom" required>
                    </div>
                    <div class="mb-3">
                        <label for="tache_description" class="form-label">Description</label>
                        <textarea class="form-control" id="tache_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="tache_couleur" class="form-label">Couleur</label>
                        <input type="color" class="form-control form-control-color" id="tache_couleur" name="couleur" value="#007bff">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="tache_actif" name="actif" checked>
                        <label class="form-check-label" for="tache_actif">Tâche active</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="button" class="btn btn-primary" onclick="addTache()">
                    <i class="fas fa-plus"></i> Ajouter
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Modifier une tâche -->
<div class="modal fade" id="editTacheModal" tabindex="-1" aria-labelledby="editTacheModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTacheModalLabel">Modifier une tâche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <form id="editTacheForm">
                    <input type="hidden" id="edit_tache_id" name="id">
                    <div class="mb-3">
                        <label for="edit_tache_nom" class="form-label">Nom *</label>
                        <input type="text" class="form-control" id="edit_tache_nom" name="nom" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_tache_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_tache_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_tache_couleur" class="form-label">Couleur</label>
                        <input type="color" class="form-control form-control-color" id="edit_tache_couleur" name="couleur">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_tache_actif" name="actif">
                        <label class="form-check-label" for="edit_tache_actif">Tâche active</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="button" class="btn btn-primary" onclick="editTache()">
                    <i class="fas fa-save"></i> Modifier
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>