<?php
// Démarrer le buffering de sortie pour éviter les sorties non désirées
ob_start();

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// Inclusion des fonctions communes
require_once 'includes/functions.php';

// Vérification de l'authentification et des droits d'accès
check_authentication('admin', $_SERVER['REQUEST_METHOD'] === 'POST');

// Traitement AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nettoyer le buffer de sortie
    ob_clean();
    
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Inclusion de la connexion à la base de données
        require_once 'includes/db_connect.php';
        
        // Vérifier que l'action est définie
        if (!isset($_POST['action'])) {
            throw new Exception('Action non spécifiée');
        }
        
        $action = $_POST['action'];
        
        // Récupérer les participants confirmés d'un événement
        if ($action === 'get_confirmed_participants') {
            $event_id = $_POST['event_id'] ?? 0;
            
            if (empty($event_id)) {
                throw new Exception('ID de l\'événement manquant');
            }
            
            // Récupérer les participants confirmés
            $stmt = $conn->prepare("
                SELECT DISTINCT a.id, a.nom, a.prenom, a.photo
                FROM adherents a
                INNER JOIN participation p ON a.id = p.adherent_id
                WHERE p.evenement_id = ? AND p.statut = 'confirmé'
                ORDER BY a.nom, a.prenom
            ");
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $participants = [];
            while ($row = $result->fetch_assoc()) {
                $participants[] = $row;
            }
            $stmt->close();
            
            $response['success'] = true;
            $response['participants'] = $participants;
        }
        
        // Récupérer les tâches disponibles
        elseif ($action === 'get_taches') {
            $stmt = $conn->prepare("SELECT id, nom, description, couleur FROM taches WHERE actif = 1 ORDER BY nom");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $taches = [];
            while ($row = $result->fetch_assoc()) {
                $taches[] = $row;
            }
            $stmt->close();
            
            $response['success'] = true;
            $response['taches'] = $taches;
        }
        
        // Récupérer les assignations existantes
        elseif ($action === 'get_assignations') {
            $event_id = $_POST['event_id'] ?? 0;
            
            if (empty($event_id)) {
                throw new Exception('ID de l\'événement manquant');
            }
            
            $stmt = $conn->prepare("
                SELECT eta.adherent_id, eta.tache_id, eta.notes,
                       a.nom, a.prenom, t.nom as tache_nom, t.couleur
                FROM evenement_taches_adherents eta
                INNER JOIN adherents a ON eta.adherent_id = a.id
                INNER JOIN taches t ON eta.tache_id = t.id
                WHERE eta.evenement_id = ?
                ORDER BY t.nom, a.nom, a.prenom
            ");
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $assignations = [];
            while ($row = $result->fetch_assoc()) {
                $assignations[] = $row;
            }
            $stmt->close();
            
            $response['success'] = true;
            $response['assignations'] = $assignations;
        }
        
        // Assigner une tâche à un adhérent
        elseif ($action === 'assign_task') {
            $event_id = $_POST['event_id'] ?? 0;
            $adherent_id = $_POST['adherent_id'] ?? 0;
            $tache_id = $_POST['tache_id'] ?? 0;
            $notes = trim($_POST['notes'] ?? '');
            
            if (empty($event_id) || empty($adherent_id) || empty($tache_id)) {
                throw new Exception('Paramètres manquants');
            }
            
            // Vérifier si l'assignation existe déjà
            $stmt = $conn->prepare("SELECT id FROM evenement_taches_adherents WHERE evenement_id = ? AND adherent_id = ? AND tache_id = ?");
            $stmt->bind_param("iii", $event_id, $adherent_id, $tache_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception('Cette assignation existe déjà');
            }
            $stmt->close();
            
            // Créer l'assignation
            $stmt = $conn->prepare("INSERT INTO evenement_taches_adherents (evenement_id, adherent_id, tache_id, notes) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $event_id, $adherent_id, $tache_id, $notes);
            
            if (!$stmt->execute()) {
                throw new Exception('Erreur lors de l\'assignation : ' . $stmt->error);
            }
            
            $response['success'] = true;
            $response['message'] = 'Tâche assignée avec succès';
            $stmt->close();
        }
        
        // Supprimer une assignation
        elseif ($action === 'remove_assignment') {
            $event_id = $_POST['event_id'] ?? 0;
            $adherent_id = $_POST['adherent_id'] ?? 0;
            $tache_id = $_POST['tache_id'] ?? 0;
            
            if (empty($event_id) || empty($adherent_id) || empty($tache_id)) {
                throw new Exception('Paramètres manquants');
            }
            
            $stmt = $conn->prepare("DELETE FROM evenement_taches_adherents WHERE evenement_id = ? AND adherent_id = ? AND tache_id = ?");
            $stmt->bind_param("iii", $event_id, $adherent_id, $tache_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Erreur lors de la suppression : ' . $stmt->error);
            }
            
            $response['success'] = true;
            $response['message'] = 'Assignation supprimée avec succès';
            $stmt->close();
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Récupération de l'ID de l'événement
$event_id = $_GET['event_id'] ?? 0;
if (empty($event_id)) {
    header('Location: evenement.php');
    exit;
}

// Récupération des informations de l'événement
require_once 'includes/db_connect.php';
$stmt = $conn->prepare("SELECT titre, date, heure, lieu FROM evenements WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: evenement.php');
    exit;
}

$event = $result->fetch_assoc();
$stmt->close();

// Formatage de la date
$date = new DateTime($event['date']);
$event['date_formatted'] = $date->format('d/m/Y');

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'includes/head.php'; ?>
    <title>Organisation des tâches - <?= htmlspecialchars($event['titre']) ?></title>
    <style>
        .task-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .participant-badge {
            cursor: pointer;
            margin: 2px;
            transition: all 0.3s ease;
        }
        .participant-badge:hover {
            transform: scale(1.05);
        }
        .drop-zone {
            min-height: 100px;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            transition: all 0.3s ease;
        }
        .drop-zone.drag-over {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .assigned-participant {
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 15px;
            padding: 5px 10px;
            margin: 2px;
            display: inline-block;
            font-size: 0.875rem;
        }
        
        /* Styles pour la colonne sticky des participants */
        .participants-sticky {
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }
        
        .participants-sticky .card-body {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
        
        /* Amélioration du scroll */
        .participants-sticky .card-body::-webkit-scrollbar {
            width: 6px;
        }
        
        .participants-sticky .card-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .participants-sticky .card-body::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .participants-sticky .card-body::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-tasks"></i> Organisation des tâches</h2>
                        <h4 class="text-muted"><?= htmlspecialchars($event['titre']) ?></h4>
                        <p class="text-muted mb-0"><?= $event['date_formatted'] ?> à <?= $event['heure'] ?> - <?= htmlspecialchars($event['lieu']) ?></p>
                    </div>
                    <div>
                        <a href="evenement.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour aux événements
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Colonne des participants disponibles -->
            <div class="col-md-4">
                <div class="card participants-sticky">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-users"></i> Participants confirmés</h5>
                    </div>
                    <div class="card-body" id="participantsContainer">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Chargement...</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <button id="exportPdfBtn" class="btn btn-success btn-sm" title="Exporter l'organisation en PDF">
                            <i class="fas fa-file-pdf"></i> Exporter PDF
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Colonne des tâches -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Tâches à organiser</h5>
                    </div>
                    <div class="card-body" id="tachesContainer">
                        <div class="text-center">
                            <div class="spinner-border text-success" role="status">
                                <span class="visually-hidden">Chargement...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const eventId = <?= $event_id ?>;
            let participants = [];
            let taches = [];
            let assignations = [];
            
            // Charger les données initiales
            loadParticipants();
            loadTaches().then(() => {
                // Charger les assignations seulement après que les tâches soient affichées
                loadAssignations();
            });
            
            // Fonction pour charger les participants confirmés
            function loadParticipants() {
                const formData = new FormData();
                formData.append('action', 'get_confirmed_participants');
                formData.append('event_id', eventId);
                
                fetch('organiser.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        participants = data.participants;
                        displayParticipants();
                    } else {
                        showAlert('danger', 'Erreur lors du chargement des participants');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showAlert('danger', 'Erreur de communication avec le serveur');
                });
            }
            
            // Fonction pour charger les tâches
            function loadTaches() {
                const formData = new FormData();
                formData.append('action', 'get_taches');
                
                return fetch('organiser.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        taches = data.taches;
                        displayTaches();
                        return Promise.resolve();
                    } else {
                        showAlert('danger', 'Erreur lors du chargement des tâches');
                        return Promise.reject('Erreur lors du chargement des tâches');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showAlert('danger', 'Erreur de communication avec le serveur');
                    return Promise.reject(error);
                });
            }
            
            // Fonction pour charger les assignations
            function loadAssignations() {
                const formData = new FormData();
                formData.append('action', 'get_assignations');
                formData.append('event_id', eventId);
                
                fetch('organiser.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        assignations = data.assignations;
                        updateAssignationsDisplay();
                    } else {
                        showAlert('danger', 'Erreur lors du chargement des assignations');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showAlert('danger', 'Erreur de communication avec le serveur');
                });
            }
            
            // Fonction pour afficher les participants
            function displayParticipants() {
                const container = document.getElementById('participantsContainer');
                
                if (participants.length === 0) {
                    container.innerHTML = '<p class="text-muted">Aucun participant confirmé</p>';
                    return;
                }
                
                container.innerHTML = participants.map(participant => {
                    return `
                        <div class="participant-badge badge bg-primary" 
                             draggable="true" 
                             data-participant-id="${participant.id}"
                             data-participant-name="${participant.nom} ${participant.prenom}">
                            ${participant.nom} ${participant.prenom}
                        </div>
                    `;
                }).join('');
                
                // Ajouter les événements de drag
                document.querySelectorAll('.participant-badge').forEach(badge => {
                    badge.addEventListener('dragstart', function(e) {
                        e.dataTransfer.setData('text/plain', JSON.stringify({
                            participantId: this.getAttribute('data-participant-id'),
                            participantName: this.getAttribute('data-participant-name')
                        }));
                    });
                });
            }
            
            // Fonction pour afficher les tâches
            function displayTaches() {
                const container = document.getElementById('tachesContainer');
                
                if (taches.length === 0) {
                    container.innerHTML = '<p class="text-muted">Aucune tâche disponible</p>';
                    return;
                }
                
                container.innerHTML = taches.map(tache => {
                    return `
                        <div class="task-card card mb-3" style="border-left-color: ${tache.couleur}">
                            <div class="card-header" style="background-color: ${tache.couleur}20">
                                <h6 class="mb-0" style="color: ${tache.couleur}">
                                    <i class="fas fa-clipboard-check"></i> ${tache.nom}
                                </h6>
                                ${tache.description ? `<small class="text-muted">${tache.description}</small>` : ''}
                            </div>
                            <div class="card-body">
                                <div class="drop-zone" 
                                     data-tache-id="${tache.id}"
                                     data-tache-name="${tache.nom}">
                                    <div class="assigned-participants" id="assigned-${tache.id}">
                                        <!-- Les participants assignés seront affichés ici -->
                                    </div>
                                    <small class="text-muted">Glissez-déposez les participants ici</small>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
                
                // Ajouter les événements de drop
                document.querySelectorAll('.drop-zone').forEach(zone => {
                    zone.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        this.classList.add('drag-over');
                    });
                    
                    zone.addEventListener('dragleave', function(e) {
                        this.classList.remove('drag-over');
                    });
                    
                    zone.addEventListener('drop', function(e) {
                        e.preventDefault();
                        this.classList.remove('drag-over');
                        
                        const data = JSON.parse(e.dataTransfer.getData('text/plain'));
                        const tacheId = this.getAttribute('data-tache-id');
                        const tacheName = this.getAttribute('data-tache-name');
                        
                        assignTask(data.participantId, tacheId, data.participantName, tacheName);
                    });
                });
            }
            
            // Fonction pour assigner une tâche
            function assignTask(participantId, tacheId, participantName, tacheName) {
                const formData = new FormData();
                formData.append('action', 'assign_task');
                formData.append('event_id', eventId);
                formData.append('adherent_id', participantId);
                formData.append('tache_id', tacheId);
                
                fetch('organiser.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', `${participantName} assigné(e) à la tâche ${tacheName}`);
                        loadAssignations();
                    } else {
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showAlert('danger', 'Erreur de communication avec le serveur');
                });
            }
            
            // Fonction pour supprimer une assignation
            function removeAssignment(participantId, tacheId, participantName, tacheName) {
                if (!confirm(`Êtes-vous sûr de vouloir retirer ${participantName} de la tâche ${tacheName} ?`)) {
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'remove_assignment');
                formData.append('event_id', eventId);
                formData.append('adherent_id', participantId);
                formData.append('tache_id', tacheId);
                
                fetch('organiser.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        loadAssignations();
                    } else {
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showAlert('danger', 'Erreur de communication avec le serveur');
                });
            }
            
            // Fonction pour mettre à jour l'affichage des assignations
            function updateAssignationsDisplay() {
                // Grouper les assignations par tâche
                const assignationsByTask = {};
                assignations.forEach(assignation => {
                    if (!assignationsByTask[assignation.tache_id]) {
                        assignationsByTask[assignation.tache_id] = [];
                    }
                    assignationsByTask[assignation.tache_id].push(assignation);
                });
                
                // Mettre à jour chaque zone de tâche
                taches.forEach(tache => {
                    const container = document.getElementById(`assigned-${tache.id}`);
                    if (container) {
                        const taskAssignations = assignationsByTask[tache.id] || [];
                        
                        if (taskAssignations.length === 0) {
                            container.innerHTML = '';
                        } else {
                            container.innerHTML = taskAssignations.map(assignation => {
                                return `
                                    <span class="assigned-participant" 
                                          onclick="removeAssignment(${assignation.adherent_id}, ${assignation.tache_id}, '${assignation.nom} ${assignation.prenom}', '${assignation.tache_nom}')"
                                          title="Cliquer pour retirer">
                                        ${assignation.nom} ${assignation.prenom}
                                        <i class="fas fa-times ms-1"></i>
                                    </span>
                                `;
                            }).join('');
                        }
                    }
                });
            }
            
            // Fonction pour afficher les alertes
            function showAlert(type, message) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
                alertDiv.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                const container = document.querySelector('.container-fluid');
                container.insertBefore(alertDiv, container.firstChild);
                
                // Auto-dismiss après 5 secondes
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
            }
            
            // Événement pour le bouton d'export PDF
            document.getElementById('exportPdfBtn').addEventListener('click', function() {
                window.open(`export_organisation_pdf.php?event_id=${eventId}`, '_blank');
            });
            
            // Exposer la fonction removeAssignment globalement
            window.removeAssignment = removeAssignment;
        });
    </script>
</body>
</html>