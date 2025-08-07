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

// Traitement AJAX pour les événements
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
        
        // Ajout d'un événement
        if ($action === 'add_event') {
            $titre = trim($_POST['titre'] ?? '');
            $date = $_POST['date'] ?? '';
            $heure = $_POST['heure'] ?? '';
            $lieu = trim($_POST['lieu'] ?? '');
            $point_rdv = trim($_POST['point_rdv'] ?? '');
            $informations = trim($_POST['informations'] ?? '');
            $nombre_participants_souhaite = !empty($_POST['nombre_participants_souhaite']) ? intval($_POST['nombre_participants_souhaite']) : null;
            
            if (empty($titre) || empty($date) || empty($heure) || empty($lieu)) {
                throw new Exception('Tous les champs obligatoires doivent être remplis');
            }
            
            // Insertion dans la base de données
            $stmt = $conn->prepare("INSERT INTO evenements (titre, date, heure, lieu, point_rdv, informations, nombre_participants_souhaite) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception('Erreur de préparation de la requête : ' . $conn->error);
            }
            
            $stmt->bind_param("ssssssi", $titre, $date, $heure, $lieu, $point_rdv, $informations, $nombre_participants_souhaite);
            
            if (!$stmt->execute()) {
                throw new Exception('Erreur lors de l\'ajout : ' . $stmt->error);
            }
            
            $response['success'] = true;
            $response['message'] = 'Événement ajouté avec succès';
            $response['id'] = $conn->insert_id;
            $stmt->close();
        }
        
        // Modification d'un événement
        elseif ($action === 'edit_event') {
            $id = $_POST['id'] ?? 0;
            $titre = trim($_POST['titre'] ?? '');
            $date = $_POST['date'] ?? '';
            $heure = $_POST['heure'] ?? '';
            $lieu = trim($_POST['lieu'] ?? '');
            $point_rdv = trim($_POST['point_rdv'] ?? '');
            $informations = trim($_POST['informations'] ?? '');
            $nombre_participants_souhaite = !empty($_POST['nombre_participants_souhaite']) ? intval($_POST['nombre_participants_souhaite']) : null;
            
            if (empty($id) || empty($titre) || empty($date) || empty($heure) || empty($lieu)) {
                throw new Exception('Tous les champs obligatoires doivent être remplis');
            }
            
            // Mise à jour dans la base de données
            $stmt = $conn->prepare("UPDATE evenements SET titre = ?, date = ?, heure = ?, lieu = ?, point_rdv = ?, informations = ?, nombre_participants_souhaite = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception('Erreur de préparation de la requête : ' . $conn->error);
            }
            
            $stmt->bind_param("ssssssii", $titre, $date, $heure, $lieu, $point_rdv, $informations, $nombre_participants_souhaite, $id);
            
            if (!$stmt->execute()) {
                throw new Exception('Erreur lors de la modification : ' . $stmt->error);
            }
            
            $response['success'] = true;
            $response['message'] = 'Événement modifié avec succès';
            $stmt->close();
        }
        
        // Suppression d'un événement
        elseif ($action === 'delete_event') {
            $id = $_POST['id'] ?? 0;
            
            if (empty($id)) {
                throw new Exception('ID de l\'événement manquant');
            }
            
            // Suppression des participations associées
            $stmt = $conn->prepare("DELETE FROM participation WHERE evenement_id = ?");
            if (!$stmt) {
                throw new Exception('Erreur de préparation de la requête : ' . $conn->error);
            }
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            
            // Suppression de l'événement
            $stmt = $conn->prepare("DELETE FROM evenements WHERE id = ?");
            if (!$stmt) {
                throw new Exception('Erreur de préparation de la requête : ' . $conn->error);
            }
            $stmt->bind_param("i", $id);
            
            if (!$stmt->execute()) {
                throw new Exception('Erreur lors de la suppression : ' . $stmt->error);
            }
            
            $response['success'] = true;
            $response['message'] = 'Événement supprimé avec succès';
            $stmt->close();
        }
        
        // Récupération des événements
        elseif ($action === 'get_events') {
            $events = get_all_events($conn);
            
            // Formatage des dates
            foreach ($events as &$row) {
                $date = new DateTime($row['date']);
                $row['date_formatted'] = $date->format('d/m/Y');
            }
            
            $response['success'] = true;
            $response['events'] = $events;
        }
        
        // Récupération des adhérents pour un événement
        elseif ($action === 'get_participants') {
            $event_id = $_POST['event_id'] ?? 0;
            
            if (empty($event_id)) {
                throw new Exception('ID de l\'événement manquant');
            }
            
            // Récupération de tous les adhérents
            $stmt = $conn->prepare("SELECT id, nom, prenom FROM adherents ORDER BY nom ASC");
            if (!$stmt) {
                throw new Exception('Erreur de préparation de la requête : ' . $conn->error);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $adherents = [];
            while ($row = $result->fetch_assoc()) {
                $adherents[] = $row;
            }
            $stmt->close();
            
            // Récupération des participations existantes pour cet événement
            $stmt = $conn->prepare("SELECT adherent_id, statut FROM participation WHERE evenement_id = ?");
            if (!$stmt) {
                throw new Exception('Erreur de préparation de la requête : ' . $conn->error);
            }
            
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $participations = [];
            while ($row = $result->fetch_assoc()) {
                $participations[$row['adherent_id']] = $row['statut'];
            }
            
            $response['success'] = true;
            $response['adherents'] = $adherents;
            $response['participations'] = $participations;
            $stmt->close();
        }
        
        // Mise à jour des participations
        elseif ($action === 'update_participation') {
            $event_id = $_POST['event_id'] ?? 0;
            $adherent_id = $_POST['adherent_id'] ?? 0;
            $statut = $_POST['statut'] ?? '';
            
            if (empty($event_id) || empty($adherent_id) || empty($statut)) {
                throw new Exception('Tous les champs sont obligatoires');
            }
            
            // Vérifier si la participation existe déjà
            $stmt = $conn->prepare("SELECT id FROM participation WHERE evenement_id = ? AND adherent_id = ?");
            if (!$stmt) {
                throw new Exception('Erreur de préparation de la requête : ' . $conn->error);
            }
            
            $stmt->bind_param("ii", $event_id, $adherent_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Mise à jour de la participation existante
                $stmt = $conn->prepare("UPDATE participation SET statut = ? WHERE evenement_id = ? AND adherent_id = ?");
                if (!$stmt) {
                    throw new Exception('Erreur de préparation de la requête : ' . $conn->error);
                }
                $stmt->bind_param("sii", $statut, $event_id, $adherent_id);
            } else {
                // Création d'une nouvelle participation
                $stmt = $conn->prepare("INSERT INTO participation (evenement_id, adherent_id, statut) VALUES (?, ?, ?)");
                if (!$stmt) {
                    throw new Exception('Erreur de préparation de la requête : ' . $conn->error);
                }
                $stmt->bind_param("iis", $event_id, $adherent_id, $statut);
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Erreur lors de la mise à jour : ' . $stmt->error);
            }
            
            $response['success'] = true;
            $response['message'] = 'Participation mise à jour avec succès';
            $stmt->close();
        }
        
        else {
            throw new Exception('Action non reconnue : ' . $action);
        }
        
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = 'Erreur : ' . $e->getMessage();
        $response['debug'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    echo json_encode($response);
    exit;
}

// Inclusion de la connexion à la base de données pour les pages normales
require_once 'includes/db_connect.php';

// Arrêter le buffering pour les pages normales
ob_end_flush();

// Configuration de la page
$page_title = 'Asso Vélo - Gestion des Événements';
?>
<?php include 'includes/header.php'; ?>


    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Gestion des événements</h1>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                    <i class="bi bi-plus-circle"></i> Ajouter un événement
                </button>
            </div>
        </div>
        
        <!-- Liste des événements -->
        <div id="eventsList">
            <!-- Les événements seront chargés ici via AJAX -->
        </div>
    </div>
    
    <!-- Modal d'ajout d'événement -->
    <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addEventModalLabel">Ajouter un événement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <form id="addEventForm">
                        <input type="hidden" name="action" value="add_event">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="titre" class="form-label">Titre</label>
                                <input type="text" class="form-control" id="titre" name="titre" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="date" name="date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="heure" class="form-label">Heure</label>
                                <input type="time" class="form-control" id="heure" name="heure" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="lieu" class="form-label">Lieu (secteur de la course)</label>
                            <input type="text" class="form-control" id="lieu" name="lieu" required>
                        </div>
                        <div class="mb-3">
                            <label for="point_rdv" class="form-label">Point de rendez-vous</label>
                            <input type="text" class="form-control" id="point_rdv" name="point_rdv" placeholder="Lieu précis du rendez-vous">
                        </div>
                        <div class="mb-3">
                            <label for="informations" class="form-label">Informations complémentaires</label>
                            <textarea class="form-control" id="informations" name="informations" rows="3" placeholder="Informations supplémentaires sur l'événement (équipement, niveau, etc.)"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="nombre_participants_souhaite" class="form-label">Nombre de participants souhaité</label>
                            <input type="number" class="form-control" id="nombre_participants_souhaite" name="nombre_participants_souhaite" min="1" placeholder="Nombre de participants souhaité (optionnel)">
                            <div class="form-text">Laissez vide si aucune limite n'est souhaitée</div>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Ajouter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de modification d'événement -->
    <div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editEventModalLabel">Modifier un événement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <form id="editEventForm">
                        <input type="hidden" name="action" value="edit_event">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="edit_titre" class="form-label">Titre</label>
                                <input type="text" class="form-control" id="edit_titre" name="titre" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="edit_date" name="date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_heure" class="form-label">Heure</label>
                                <input type="time" class="form-control" id="edit_heure" name="heure" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_lieu" class="form-label">Lieu (secteur de la course)</label>
                            <input type="text" class="form-control" id="edit_lieu" name="lieu" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_point_rdv" class="form-label">Point de rendez-vous</label>
                            <input type="text" class="form-control" id="edit_point_rdv" name="point_rdv" placeholder="Lieu précis du rendez-vous">
                        </div>
                        <div class="mb-3">
                            <label for="edit_informations" class="form-label">Informations complémentaires</label>
                            <textarea class="form-control" id="edit_informations" name="informations" rows="3" placeholder="Informations supplémentaires sur l'événement (équipement, niveau, etc.)"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_nombre_participants_souhaite" class="form-label">Nombre de participants souhaité</label>
                            <input type="number" class="form-control" id="edit_nombre_participants_souhaite" name="nombre_participants_souhaite" min="1" placeholder="Nombre de participants souhaité (optionnel)">
                            <div class="form-text">Laissez vide si aucune limite n'est souhaitée</div>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de gestion des participants -->
    <div class="modal fade" id="participantsModal" tabindex="-1" aria-labelledby="participantsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="participantsModalLabel">Gestion des participants</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <h6 id="event_title"></h6>
                    <p id="event_details"></p>
                    
                    <div class="table-responsive mt-4">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="participantsList">
                                <!-- Les participants seront chargés ici via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteEventModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer cet événement ? Cette action est irréversible.</p>
                    <p><strong>Titre : </strong><span id="delete_titre"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" id="confirmDelete" class="btn btn-danger">Supprimer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variables globales
            let currentView = 'table'; // Vue par défaut en tableau
            
            let deleteEventId = null;
            let currentEventId = null;
            
            // Éléments DOM
            const eventsList = document.getElementById('eventsList');
            
            // Formulaires
            const addEventForm = document.getElementById('addEventForm');
            const editEventForm = document.getElementById('editEventForm');
            
            // Modals
            const addEventModal = new bootstrap.Modal(document.getElementById('addEventModal'));
            const editEventModal = new bootstrap.Modal(document.getElementById('editEventModal'));
            const participantsModal = new bootstrap.Modal(document.getElementById('participantsModal'));
            const deleteEventModal = new bootstrap.Modal(document.getElementById('deleteEventModal'));
            
            
            
            
            
            // Chargement initial des événements
            loadEvents();
            
            // Suppression de l'event listener pour le changement de vue
            

            
            // Soumission du formulaire d'ajout
            addEventForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                submitForm(this, function() {
                    addEventModal.hide();
                    addEventForm.reset();
                    loadEvents();
                });
            });
            
            // Soumission du formulaire de modification
            editEventForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                submitForm(this, function() {
                    editEventModal.hide();
                    loadEvents();
                });
            });
            
            // Confirmation de suppression
            document.getElementById('confirmDelete').addEventListener('click', function() {
                if (deleteEventId) {
                    const formData = new FormData();
                    formData.append('action', 'delete_event');
                    formData.append('id', deleteEventId);
                    
                    fetch('evenement.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            deleteEventModal.hide();
                            loadEvents();
                            showAlert('success', data.message);
                        } else {
                            showAlert('danger', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        showAlert('danger', 'Une erreur est survenue lors de la suppression');
                    });
                }
            });
            
            
            
            // Fonction pour charger les événements
            function loadEvents() {
                eventsList.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div></div>';
                
                const formData = new FormData();
                formData.append('action', 'get_events');
                
                fetch('evenement.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.events.length === 0) {
                            eventsList.innerHTML = '<div class="alert alert-info">Aucun événement trouvé</div>';
                        } else {
                            if (currentView === 'cards') {
                                displayEventsAsCards(data.events);
                            } else {
                                displayEventsAsTable(data.events);
                            }
                        }
                    } else {
                        showAlert('danger', 'Erreur lors du chargement des événements');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showAlert('danger', 'Une erreur est survenue lors du chargement des événements');
                });
            }
            
            // Fonction pour afficher les événements en cartes
            function displayEventsAsCards(events) {
                eventsList.innerHTML = '';
                const row = document.createElement('div');
                row.className = 'row';
                
                events.forEach(event => {
                    const col = document.createElement('div');
                    col.className = 'col-md-6 col-lg-4 mb-4';
                    
                    col.innerHTML = `
                        <div class="card event-card">
                            <div class="card-body">
                                <h5 class="card-title">${event.titre}</h5>
                                <p class="card-text event-date">${event.date_formatted} à ${event.heure}</p>
                                <p class="card-text event-location">${event.lieu}</p>
                                <div class="d-flex justify-content-between mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary participants-btn" data-id="${event.id}" data-titre="${event.titre}" data-details="${event.date_formatted} à ${event.heure} - ${event.lieu}">Participants</button>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary edit-event" data-id="${event.id}">Modifier</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-event" data-id="${event.id}" data-titre="${event.titre}">Supprimer</button>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <button type="button" class="btn btn-sm btn-warning organiser-btn" data-id="${event.id}" title="Organiser les tâches">🗂️ Organiser</button>
                                    <button type="button" class="btn btn-sm btn-success export-pdf-btn" data-id="${event.id}" data-titre="${event.titre}">📄 Export PDF</button>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    row.appendChild(col);
                });
                
                eventsList.appendChild(row);
                
                // Ajout des événements sur les boutons
                addButtonEventListeners();
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
                                    <th>Participants souhaités</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${events.map(event => {
                                    // Vérifier si l'événement est passé
                                    const eventDate = new Date(event.date + 'T' + event.heure);
                                    const now = new Date();
                                    const isPastEvent = eventDate < now;
                                    const rowClass = isPastEvent ? 'table-secondary text-muted' : '';
                                    
                                    return `
                                        <tr class="${rowClass}">
                                            <td>${event.titre}${isPastEvent ? ' <span class="badge bg-secondary">Terminé</span>' : ''}</td>
                                            <td>${event.date_formatted}</td>
                                            <td>${event.heure}</td>
                                            <td>${event.lieu}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary participants-btn" data-id="${event.id}" data-titre="${event.titre}" data-details="${event.date_formatted} à ${event.heure} - ${event.lieu}">Participants</button>
                                                <button type="button" class="btn btn-sm btn-warning organiser-btn" data-id="${event.id}" title="Organiser les tâches">🗂️ Organiser</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary edit-event" data-id="${event.id}">Modifier</button>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-event" data-id="${event.id}" data-titre="${event.titre}">Supprimer</button>
                                                <button type="button" class="btn btn-sm btn-success export-pdf-btn" data-id="${event.id}" data-titre="${event.titre}">📄 Export PDF</button>
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
            
            // Fonction pour ajouter les événements sur les boutons
            function addButtonEventListeners() {
                // Boutons de gestion des participants
                document.querySelectorAll('.participants-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const eventId = this.getAttribute('data-id');
                        const titre = this.getAttribute('data-titre');
                        const details = this.getAttribute('data-details');
                        
                        currentEventId = eventId;
                        document.getElementById('event_title').textContent = titre;
                        document.getElementById('event_details').textContent = details;
                        
                        loadParticipants(eventId);
                        participantsModal.show();
                    });
                });
                
                // Boutons de modification
                document.querySelectorAll('.edit-event').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const eventId = this.getAttribute('data-id');
                        openEditModal(eventId);
                    });
                });
                
                // Boutons de suppression
                document.querySelectorAll('.delete-event').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const eventId = this.getAttribute('data-id');
                        const titre = this.getAttribute('data-titre');
                        openDeleteModal(eventId, titre);
                    });
                });
                
                // Boutons d'export PDF
                document.querySelectorAll('.export-pdf-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const eventId = this.getAttribute('data-id');
                        const titre = this.getAttribute('data-titre');
                        
                        // Ouvrir le PDF dans un nouvel onglet
                        window.open(`export_event_pdf.php?event_id=${eventId}`, '_blank');
                    });
                });
                
                // Boutons d'organisation des tâches
                document.querySelectorAll('.organiser-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const eventId = this.getAttribute('data-id');
                        
                        // Rediriger vers la page d'organisation
                        window.location.href = `organiser.php?event_id=${eventId}`;
                    });
                });
            }
            
            // Fonction pour charger les participants d'un événement
            // Fonction pour charger les participants
            function loadParticipants(eventId) {
            const participantsList = document.getElementById('participantsList');
            
            // Sauvegarder la position de défilement actuelle
            const modalBody = document.querySelector('#participantsModal .modal-body');
            const scrollPosition = modalBody ? modalBody.scrollTop : 0;
            
            participantsList.innerHTML = '<tr><td colspan="4" class="text-center"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Chargement...</span></div></td></tr>';
            
            const formData = new FormData();
            formData.append('action', 'get_participants');
            formData.append('event_id', eventId);
            
            fetch('evenement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    participantsList.innerHTML = '';
                    
                    if (data.adherents.length === 0) {
                        participantsList.innerHTML = '<tr><td colspan="4" class="text-center">Aucun adhérent trouvé</td></tr>';
                    } else {
                        data.adherents.forEach(adherent => {
                            const statut = data.participations[adherent.id] || 'en attente';
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${adherent.nom}</td>
                                <td>${adherent.prenom}</td>
                                <td>
                                    <span class="badge ${getStatusClass(statut)}">${getStatusLabel(statut)}</span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-success update-status" data-adherent="${adherent.id}" data-status="confirmé">Confirmé</button>
                                        <button type="button" class="btn btn-outline-warning update-status" data-adherent="${adherent.id}" data-status="en attente">En attente</button>
                                        <button type="button" class="btn btn-outline-danger update-status" data-adherent="${adherent.id}" data-status="pas disponible">Pas disponible</button>
                                    </div>
                                </td>
                            `;
                            
                            participantsList.appendChild(tr);
                        });
                        
                        // Ajouter les événements sur les boutons de mise à jour du statut
                        document.querySelectorAll('.update-status').forEach(btn => {
                            btn.addEventListener('click', function() {
                                const adherentId = this.getAttribute('data-adherent');
                                const status = this.getAttribute('data-status');
                                updateParticipationStatus(eventId, adherentId, status);
                            });
                        });
                    }
                    
                    // Restaurer la position de défilement après le rechargement
                    setTimeout(() => {
                        if (modalBody) {
                            modalBody.scrollTop = scrollPosition;
                        }
                    }, 100);
                } else {
                    showAlert('danger', 'Erreur lors du chargement des participants');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showAlert('danger', 'Une erreur est survenue lors du chargement des participants');
            });
            }
            
            // Fonction pour mettre à jour le statut de participation
            function updateParticipationStatus(eventId, adherentId, status) {
            // Sauvegarder la position de défilement avant la mise à jour
            const modalBody = document.querySelector('#participantsModal .modal-body');
            const scrollPosition = modalBody ? modalBody.scrollTop : 0;
            
            const formData = new FormData();
            formData.append('action', 'update_participation');
            formData.append('event_id', eventId);
            formData.append('adherent_id', adherentId);
            formData.append('statut', status);
            
            fetch('evenement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Recharger les participants en conservant la position
                    loadParticipants(eventId);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showAlert('danger', 'Une erreur est survenue lors de la mise à jour du statut');
            });
            }
            
            // Fonction pour ouvrir le modal de modification
            function openEditModal(eventId) {
                const formData = new FormData();
                formData.append('action', 'get_events');
                
                fetch('evenement.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const event = data.events.find(e => e.id == eventId);
                        
                        if (event) {
                            document.getElementById('edit_id').value = event.id;
                document.getElementById('edit_titre').value = event.titre;
                document.getElementById('edit_date').value = event.date;
                document.getElementById('edit_heure').value = event.heure;
                document.getElementById('edit_lieu').value = event.lieu;
                document.getElementById('edit_point_rdv').value = event.point_rdv || '';
                document.getElementById('edit_informations').value = event.informations || '';
                document.getElementById('edit_nombre_participants_souhaite').value = event.nombre_participants_souhaite || '';
                            
                            editEventModal.show();
                        }
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showAlert('danger', 'Une erreur est survenue lors du chargement de l\'événement');
                });
            }
            
            // Fonction pour ouvrir le modal de suppression
            function openDeleteModal(id, titre) {
                deleteEventId = id;
                document.getElementById('delete_titre').textContent = titre;
                deleteEventModal.show();
            }
            
            // Fonction pour soumettre un formulaire via AJAX
            function submitForm(form, callback) {
                const formData = new FormData(form);
                
                // Debug: afficher les données envoyées
                console.log('Données envoyées:');
                for (let [key, value] of formData.entries()) {
                    console.log(key, value);
                }
                
                fetch('evenement.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Statut de la réponse:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text(); // Changé de .json() à .text() pour voir la réponse brute
                })
                .then(text => {
                    console.log('Réponse brute:', text);
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            showAlert('success', data.message);
                            if (typeof callback === 'function') {
                                callback();
                                // Forcer la suppression du backdrop modal
                                setTimeout(() => {
                                    const backdrops = document.querySelectorAll('.modal-backdrop');
                                    backdrops.forEach(backdrop => backdrop.remove());
                                    document.body.classList.remove('modal-open');
                                    document.body.style.overflow = '';
                                    document.body.style.paddingRight = '';
                                }, 100);
                            }
                        } else {
                            showAlert('danger', data.message);
                        }
                    } catch (parseError) {
                        console.error('Erreur de parsing JSON:', parseError);
                        console.error('Texte reçu:', text);
                        showAlert('danger', 'Erreur de format de réponse du serveur');
                    }
                })
                .catch(error => {
                    console.error('Erreur complète:', error);
                    showAlert('danger', 'Une erreur est survenue lors de la soumission du formulaire: ' + error.message);
                });
            }
            
            // Fonction pour obtenir la classe CSS du statut
            function getStatusClass(statut) {
                switch (statut) {
                    case 'confirmé': return 'bg-success';
                    case 'en attente': return 'bg-warning';
                    case 'pas disponible': return 'bg-danger';
                    default: return 'bg-secondary';
                }
            }
            
            // Fonction pour obtenir le libellé du statut
            function getStatusLabel(statut) {
                switch (statut) {
                    case 'confirmé': return 'Confirmé';
                    case 'en attente': return 'En attente';
                    case 'pas disponible': return 'Pas disponible';
                    default: return statut;
                }
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