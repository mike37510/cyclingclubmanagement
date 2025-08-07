<?php
/**
 * Fichier de fonctions communes pour éviter la duplication de code
 */

/**
 * Vérifie l'authentification et les droits d'accès
 * @param string $required_role Le rôle requis ('admin' ou null pour tout utilisateur connecté)
 * @param bool $is_ajax Indique si c'est une requête AJAX (pour retourner JSON)
 */
function check_authentication($required_role = null, $is_ajax = false) {
    init_secure_session();
    
    if (!isset($_SESSION['user_id'])) {
        if ($is_ajax) {
            json_response(false, 'Accès non autorisé');
        }
        header('Location: login.php');
        exit;
    }
    
    if ($required_role && $_SESSION['role'] !== $required_role) {
        if ($is_ajax) {
            json_response(false, 'Accès non autorisé');
        }
        header('Location: login.php');
        exit;
    }
}

/**
 * Calcule l'âge à partir d'une date de naissance
 * @param string $date_naissance Date de naissance au format Y-m-d
 * @return int Âge en années
 */
function calculate_age($date_naissance) {
    $date_naissance = new DateTime($date_naissance);
    $aujourd_hui = new DateTime();
    return $aujourd_hui->diff($date_naissance)->y;
}

/**
 * Vérifie si une personne est mineure
 * @param string $date_naissance Date de naissance au format Y-m-d
 * @return bool True si mineur, false sinon
 */
function is_minor($date_naissance) {
    return calculate_age($date_naissance) < 18;
}

/**
 * Récupère un adhérent avec ses catégories
 * @param mysqli $conn Connexion à la base de données
 * @param int $adherent_id ID de l'adhérent
 * @return array|null Données de l'adhérent ou null si non trouvé
 */
function get_adherent_with_categories($conn, $adherent_id) {
    $stmt = $conn->prepare("
        SELECT a.id, a.nom, a.prenom, a.date_naissance, a.email, a.telephone, a.photo, 
               a.tuteur_nom, a.tuteur_prenom, a.tuteur_telephone, a.tuteur_email,
               a.tuteur2_nom, a.tuteur2_prenom, a.tuteur2_telephone, a.tuteur2_email,
               a.droit_image,
               GROUP_CONCAT(c.nom SEPARATOR ', ') as categories_noms,
               GROUP_CONCAT(c.couleur SEPARATOR ',') as categories_couleurs
        FROM adherents a 
        LEFT JOIN adherent_categories ac ON a.id = ac.adherent_id
        LEFT JOIN categories c ON ac.categorie_id = c.id 
        WHERE a.id = ?
        GROUP BY a.id
    ");
    $stmt->bind_param("i", $adherent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }
    
    $adherent = $result->fetch_assoc();
    $stmt->close();
    
    // Ajouter l'âge et le statut mineur
    $adherent['age'] = calculate_age($adherent['date_naissance']);
    $adherent['est_mineur'] = is_minor($adherent['date_naissance']);
    
    return $adherent;
}

/**
 * Récupère tous les adhérents avec leurs catégories
 * @param mysqli $conn Connexion à la base de données
 * @return array Liste des adhérents
 */
function get_all_adherents_with_categories($conn) {
    $stmt = $conn->prepare("
        SELECT a.id, a.nom, a.prenom, a.date_naissance, a.email, a.telephone, a.photo, 
               a.tuteur_nom, a.tuteur_prenom, a.tuteur_telephone, a.tuteur_email,
               a.tuteur2_nom, a.tuteur2_prenom, a.tuteur2_telephone, a.tuteur2_email,
               a.droit_image, 
               GROUP_CONCAT(c.nom SEPARATOR ', ') as categories_noms,
               GROUP_CONCAT(c.couleur SEPARATOR ',') as categories_couleurs
        FROM adherents a 
        LEFT JOIN adherent_categories ac ON a.id = ac.adherent_id
        LEFT JOIN categories c ON ac.categorie_id = c.id 
        GROUP BY a.id
        ORDER BY a.nom ASC, a.prenom ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $adherents = [];
    while ($row = $result->fetch_assoc()) {
        // Ajouter l'âge et le statut mineur
        $row['age'] = calculate_age($row['date_naissance']);
        $row['est_mineur'] = is_minor($row['date_naissance']);
        
        // Vérification de la photo pour le trombinoscope
        if (empty($row['photo']) || !file_exists($row['photo'])) {
            $row['photo'] = 'img/default_user.svg';
        }
        
        $adherents[] = $row;
    }
    $stmt->close();
    
    return $adherents;
}

/**
 * Récupère les participants d'un événement avec leurs catégories
 * @param mysqli $conn Connexion à la base de données
 * @param int $event_id ID de l'événement
 * @return array Liste des participants
 */
function get_event_participants_with_categories($conn, $event_id) {
    $stmt = $conn->prepare("
        SELECT a.nom, a.prenom, a.email, a.telephone, a.date_naissance, 
               a.tuteur_nom, a.tuteur_prenom, a.tuteur_email, a.tuteur_telephone,
               a.tuteur2_nom, a.tuteur2_prenom, a.tuteur2_email, a.tuteur2_telephone,
               p.statut, 
               GROUP_CONCAT(c.nom SEPARATOR ', ') as categories_noms,
               GROUP_CONCAT(c.couleur SEPARATOR ',') as categories_couleurs
        FROM adherents a 
        INNER JOIN participation p ON a.id = p.adherent_id 
        LEFT JOIN adherent_categories ac ON a.id = ac.adherent_id
        LEFT JOIN categories c ON ac.categorie_id = c.id 
        WHERE p.evenement_id = ? AND p.statut = 'confirmé'
        GROUP BY a.id
        ORDER BY a.nom ASC, a.prenom ASC
    ");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $participants = [];
    while ($row = $result->fetch_assoc()) {
        // Ajouter l'âge et le statut mineur
        $row['age'] = calculate_age($row['date_naissance']);
        $row['est_mineur'] = is_minor($row['date_naissance']);
        
        $participants[] = $row;
    }
    $stmt->close();
    
    return $participants;
}

/**
 * Récupère les adhérents liés à un utilisateur
 * @param mysqli $conn Connexion à la base de données
 * @param int $user_id ID de l'utilisateur
 * @return array Liste des adhérents liés
 */
function get_user_linked_adherents($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT ua.adherent_id, ua.principal, a.nom, a.prenom 
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
    $stmt->close();
    
    return $adherents;
}

/**
 * Initialise une session sécurisée
 * @return void
 */
function init_secure_session() {
    if (!isset($_SESSION)) {
        session_start();
    }
    
    // Vérification de l'expiration de la session (30 minutes d'inactivité)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    // Mise à jour du timestamp de dernière activité
    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Génère une réponse JSON pour les appels AJAX
 * @param bool $success Succès de l'opération
 * @param string $message Message de retour
 * @param array $data Données supplémentaires
 * @return void
 */
function json_response($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

/**
 * Récupère toutes les catégories actives
 * @param mysqli $conn Connexion à la base de données
 * @return array Liste des catégories
 */
function get_active_categories($conn) {
    $stmt = $conn->prepare("SELECT id, nom, description, couleur FROM categories WHERE actif = 1 ORDER BY nom");
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $stmt->close();
    return $categories;
}

/**
 * Récupère tous les événements
 * @param mysqli $conn Connexion à la base de données
 * @return array Liste des événements
 */
function get_all_events($conn) {
    $stmt = $conn->prepare("SELECT id, titre, date, heure, lieu, point_rdv, informations FROM evenements ORDER BY date DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();
    return $events;
}

/**
 * Récupère un événement par son ID
 * @param mysqli $conn Connexion à la base de données
 * @param int $event_id ID de l'événement
 * @return array|null Données de l'événement ou null si non trouvé
 */
function get_event_by_id($conn, $event_id) {
    $stmt = $conn->prepare("SELECT id, titre, date, heure, lieu, point_rdv, informations FROM evenements WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    $stmt->close();
    return $event;
}

/**
 * Récupère les événements dans une plage de dates
 * @param mysqli $conn Connexion à la base de données
 * @param string $start_date Date de début
 * @param string $end_date Date de fin
 * @return array Liste des événements
 */
function get_events_by_date_range($conn, $start_date, $end_date) {
    $stmt = $conn->prepare("SELECT id, titre, date, heure, lieu, point_rdv FROM evenements WHERE date BETWEEN ? AND ? ORDER BY date ASC, heure ASC");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();
    return $events;
}

?>