<?php
session_start();

// Inclusion des fonctions communes et de la connexion à la base de données
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Vérification de l'authentification
check_authentication();

// Récupération des adhérents liés à l'utilisateur
$adherents_lies = get_user_linked_adherents($conn, $_SESSION['user_id']);

if (empty($adherents_lies)) {
    // L'utilisateur n'a pas de compte adhérent lié
    header('Location: login.php');
    exit();
}

// Déterminer l'adhérent actuel (principal ou sélectionné)
$adherent_actuel_id = $adherents_lies[0]['adherent_id']; // Par défaut le premier
if (isset($_GET['adherent_id'])) {
    $requested_id = (int)$_GET['adherent_id'];
    foreach ($adherents_lies as $adherent) {
        if ($adherent['adherent_id'] == $requested_id) {
            $adherent_actuel_id = $requested_id;
            break;
        }
    }
}

// Traitement AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_upcoming_events':
            try {
                $adherent_filter = $_POST['adherent_id'] ?? $adherent_actuel_id;
                
                // Vérifier que l'adhérent appartient à l'utilisateur
                $is_authorized = false;
                foreach ($adherents_lies as $adherent) {
                    if ($adherent['adherent_id'] == $adherent_filter) {
                        $is_authorized = true;
                        break;
                    }
                }
                
                if (!$is_authorized) {
                    throw new Exception('Adhérent non autorisé');
                }
                
                // Récupérer les événements à venir pour cet adhérent
                $stmt = $conn->prepare("
                    SELECT e.*, 
                           DATE_FORMAT(e.date, '%d/%m/%Y') as date_formatted,
                           p.statut as mon_statut
                    FROM evenements e 
                    LEFT JOIN participation p ON e.id = p.evenement_id AND p.adherent_id = ?
                    WHERE e.date >= CURDATE() 
                    ORDER BY e.date ASC, e.heure ASC
                ");
                $stmt->bind_param("i", $adherent_filter);
                $stmt->execute();
                $result = $stmt->get_result();
                $events = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                echo json_encode([
                    'success' => true,
                    'events' => $events
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors du chargement des événements : ' . $e->getMessage()
                ]);
            }
            exit();
            
        case 'update_my_participation':
            try {
                $event_id = $_POST['event_id'] ?? '';
                $statut = $_POST['statut'] ?? '';
                $adherent_id = $_POST['adherent_id'] ?? $adherent_actuel_id;
                
                if (empty($event_id) || empty($statut)) {
                    throw new Exception('Paramètres manquants');
                }
                
                // Vérifier que l'adhérent appartient à l'utilisateur
                $is_authorized = false;
                foreach ($adherents_lies as $adherent) {
                    if ($adherent['adherent_id'] == $adherent_id) {
                        $is_authorized = true;
                        break;
                    }
                }
                
                if (!$is_authorized) {
                    throw new Exception('Adhérent non autorisé');
                }
                
                // Vérifier que l'événement existe et est à venir
                $stmt = $conn->prepare("SELECT id FROM evenements WHERE id = ? AND date >= CURDATE()");
                $stmt->bind_param("i", $event_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    $stmt->close();
                    throw new Exception('Événement non trouvé ou déjà passé');
                }
                $stmt->close();
                
                // Vérifier si l'utilisateur a déjà une réponse définitive
                $stmt = $conn->prepare("SELECT statut FROM participation WHERE evenement_id = ? AND adherent_id = ?");
                $stmt->bind_param("ii", $event_id, $adherent_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $existing_status = $result->fetch_assoc()['statut'];
                    if ($existing_status === 'confirmé' || $existing_status === 'pas disponible') {
                        $stmt->close();
                        throw new Exception('Vous avez déjà donné une réponse définitive pour cet événement. Modification non autorisée.');
                    }
                }
                $stmt->close();
                
                // Mettre à jour ou insérer la participation
                $stmt = $conn->prepare("
                    INSERT INTO participation (evenement_id, adherent_id, statut) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE statut = VALUES(statut)
                ");
                $stmt->bind_param("iis", $event_id, $adherent_id, $statut);
                $stmt->execute();
                $stmt->close();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Votre participation a été enregistrée avec succès. Vous ne pourrez plus la modifier vous même. Si besoin de modification il faudra conctacter un administrateur du site.'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la mise à jour : ' . $e->getMessage()
                ]);
            }
            exit();
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Mes Événements</h2>
        
        <?php if (count($adherents_lies) > 1): ?>
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" id="adherentDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                Adhérent : <?php 
                    $current_adherent = null;
                    foreach ($adherents_lies as $adherent) {
                        if ($adherent['adherent_id'] == $adherent_actuel_id) {
                            $current_adherent = $adherent;
                            break;
                        }
                    }
                    echo htmlspecialchars($current_adherent['nom'] . ' ' . $current_adherent['prenom']);
                ?>
            </button>
            <ul class="dropdown-menu" aria-labelledby="adherentDropdown">
                <?php foreach ($adherents_lies as $adherent): ?>
                <li>
                    <a class="dropdown-item <?php echo $adherent['adherent_id'] == $adherent_actuel_id ? 'active' : ''; ?>" 
                       href="?adherent_id=<?php echo $adherent['adherent_id']; ?>">
                        <?php echo htmlspecialchars($adherent['nom'] . ' ' . $adherent['prenom']); ?>
                        <?php if ($adherent['principal']): ?>
                            <span class="badge bg-primary ms-2">Principal</span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    
    <div id="eventsList">
        <!-- Les événements seront chargés ici via AJAX -->
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
const CURRENT_ADHERENT_ID = <?php echo $adherent_actuel_id; ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Éléments DOM
    const eventsList = document.getElementById('eventsList');
    
    // Chargement initial des événements
    loadEvents();
    
    // Fonction pour charger les événements
    function loadEvents() {
        eventsList.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div></div>';
        
        const formData = new FormData();
        formData.append('action', 'get_upcoming_events');
        formData.append('adherent_id', CURRENT_ADHERENT_ID);
        
        fetch('mes_evenements.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.events.length === 0) {
                    eventsList.innerHTML = '<div class="alert alert-info">Aucun événement à venir trouvé</div>';
                } else {
                    displayEventsAsTable(data.events);
                }
            } else {
                showAlert('danger', 'Erreur lors du chargement des événements: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('danger', 'Une erreur est survenue lors du chargement des événements');
        });
    }
    
    // Fonction pour afficher les événements en tableau
    function displayEventsAsTable(events) {
        eventsList.innerHTML = `
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Lieu</th>
                            <th>Mon Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${events.map(event => {
                            const statusBadge = getStatusBadge(event.mon_statut);
                            const participationButtons = getParticipationButtons(event.id, event.mon_statut);
                            
                            return `
                                <tr>
                                    <td>
                                        <strong>${event.titre}</strong>
                                        ${event.point_rdv ? `<br><small class="text-muted">RDV: ${event.point_rdv}</small>` : ''}
                                        ${event.informations ? `<br><small class="text-muted">${event.informations}</small>` : ''}
                                    </td>
                                    <td>${event.date_formatted}</td>
                                    <td>${event.heure}</td>
                                    <td>${event.lieu}</td>
                                    <td>${statusBadge}</td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            ${participationButtons}
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
        
        // Ajout des événements sur les boutons
        addButtonEventListeners();
    }
    
    // Fonction pour obtenir le badge de statut
    function getStatusBadge(statut) {
        switch (statut) {
            case 'confirmé':
                return '<span class="badge bg-success">Confirmé</span>';
            case 'pas disponible':
                return '<span class="badge bg-danger">Pas disponible</span>';
            default:
                return '<span class="badge bg-secondary">Non inscrit</span>';
        }
    }
    
    // Fonction pour obtenir les boutons de participation
    function getParticipationButtons(eventId, currentStatus) {
        // Si l'utilisateur a déjà donné une réponse définitive, afficher seulement le statut
        if (currentStatus === 'confirmé' || currentStatus === 'pas disponible') {
            return `
                <div class="alert alert-info mb-0 p-2">
                    <small><i class="fas fa-lock"></i> Réponse enregistrée</small>
                </div>
            `;
        }
        
        // Sinon, afficher les boutons (sans "Peut-être")
        return `
            <button type="button" class="btn btn-sm btn-success participation-btn" 
                    data-event-id="${eventId}" data-status="confirmé">Participer</button>
            <button type="button" class="btn btn-sm btn-danger participation-btn" 
                    data-event-id="${eventId}" data-status="pas disponible">Pas dispo</button>
        `;
    }
    
    // Fonction pour ajouter les événements sur les boutons
    function addButtonEventListeners() {
        document.querySelectorAll('.participation-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const eventId = this.getAttribute('data-event-id');
                const status = this.getAttribute('data-status');
                updateParticipation(eventId, status);
            });
        });
    }
    
    // Fonction pour mettre à jour la participation
    function updateParticipation(eventId, status) {
        const formData = new FormData();
        formData.append('action', 'update_my_participation');
        formData.append('event_id', eventId);
        formData.append('statut', status);
        formData.append('adherent_id', CURRENT_ADHERENT_ID);
        
        fetch('mes_evenements.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                loadEvents(); // Recharger les événements pour mettre à jour l'affichage
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('danger', 'Une erreur est survenue lors de la mise à jour');
        });
    }
    
    // Fonction pour afficher une alerte
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        `;
        
        // Insertion de l'alerte en haut de la page
        const container = document.querySelector('.container');
        container.insertBefore(alertDiv, container.firstChild);
        
        // Suppression automatique après 5 secondes
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
});
</script>

<?php include 'includes/footer.php'; ?>