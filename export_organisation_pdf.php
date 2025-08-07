<?php
session_start();

// Inclusion des fonctions communes
require_once 'includes/functions.php';

// Vérification de l'authentification et des droits d'accès
check_authentication('admin');

// Récupération de l'ID de l'événement
$event_id = $_GET['event_id'] ?? 0;
if (empty($event_id)) {
    header('Location: evenement.php');
    exit;
}

// Inclusion de la connexion à la base de données
require_once 'includes/db_connect.php';

// Récupération des informations de l'événement
$stmt = $conn->prepare("SELECT titre, date, heure, lieu, point_rdv, informations FROM evenements WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: evenement.php');
    exit;
}

$event = $result->fetch_assoc();
$stmt->close();

// Récupération des participants confirmés
$stmt = $conn->prepare("
    SELECT DISTINCT a.id, a.nom, a.prenom, a.telephone, a.email
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

// Récupération des tâches disponibles
$stmt = $conn->prepare("SELECT id, nom, description, couleur FROM taches WHERE actif = 1 ORDER BY nom");
$stmt->execute();
$result = $stmt->get_result();

$taches = [];
while ($row = $result->fetch_assoc()) {
    $taches[] = $row;
}
$stmt->close();

// Récupération des assignations
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

// Organiser les assignations par tâche
$assignations_par_tache = [];
foreach ($assignations as $assignation) {
    $tache_id = $assignation['tache_id'];
    if (!isset($assignations_par_tache[$tache_id])) {
        $assignations_par_tache[$tache_id] = [
            'tache_nom' => $assignation['tache_nom'],
            'couleur' => $assignation['couleur'],
            'participants' => []
        ];
    }
    $assignations_par_tache[$tache_id]['participants'][] = $assignation;
}

// Formatage de la date
$date = new DateTime($event['date']);
$event['date_formatted'] = $date->format('d/m/Y');

// Configuration pour le PDF
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organisation des tâches - <?php echo htmlspecialchars($event['titre']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 24px;
        }
        .header h2 {
            color: #666;
            margin: 5px 0;
            font-size: 18px;
        }
        .event-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
            text-align: center;
        }
        .section-title {
            background-color: #007bff;
            color: white;
            padding: 10px;
            margin: 20px 0 10px 0;
            font-weight: bold;
            font-size: 16px;
        }
        .task-section {
            margin-bottom: 20px;
        }
        .task-title {
            background-color: #f0f0f0;
            padding: 8px;
            border: 1px solid #ddd;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .participant-item {
            padding: 5px 15px;
            border-left: 3px solid #007bff;
            margin-bottom: 3px;
        }
        .participant-notes {
            font-style: italic;
            color: #666;
            font-size: 0.9em;
            margin-left: 15px;
        }
        .participants-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .participants-table th,
        .participants-table td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: left;
        }
        .participants-table th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .participants-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
        }
        .print-button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 20px;
        }
        .print-button:hover {
            background-color: #0056b3;
        }
        @media print {
            .print-button {
                display: none;
            }
            body {
                margin: 0;
            }
        }
        .count-info {
            background-color: #e7f3ff;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            color: #0056b3;
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">🖨️ Imprimer ce document</button>
    
    <div class="header">
        <h1>ORGANISATION DES TÂCHES</h1>
        <h2><?php echo htmlspecialchars($event['titre']); ?></h2>
    </div>
    
    <div class="event-info">
        <strong>Date :</strong> <?php echo $event['date_formatted']; ?> à <?php echo htmlspecialchars($event['heure']); ?><br>
        <strong>Lieu :</strong> <?php echo htmlspecialchars($event['lieu']); ?><br>
        <?php if (!empty($event['point_rdv'])): ?>
            <strong>Point de RDV :</strong> <?php echo htmlspecialchars($event['point_rdv']); ?><br>
        <?php endif; ?>
    </div>
    
    <div class="count-info">
        Total participants confirmés : <?php echo count($participants); ?>
    </div>
    
    <?php if (!empty($assignations_par_tache)): ?>
        <div class="section-title">RÉPARTITION DES TÂCHES</div>
        
        <?php foreach ($assignations_par_tache as $tache_id => $tache_data): ?>
            <div class="task-section">
                <div class="task-title">
                    <?php echo htmlspecialchars($tache_data['tache_nom']); ?>
                </div>
                
                <?php foreach ($tache_data['participants'] as $participant): ?>
                    <div class="participant-item">
                        • <?php echo htmlspecialchars($participant['nom'] . ' ' . $participant['prenom']); ?>
                        <?php if (!empty($participant['notes'])): ?>
                            <div class="participant-notes">
                                Notes : <?php echo htmlspecialchars($participant['notes']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php
    // Section des participants non assignés
    $participants_assignes = array_column($assignations, 'adherent_id');
    $participants_non_assignes = array_filter($participants, function($p) use ($participants_assignes) {
        return !in_array($p['id'], $participants_assignes);
    });
    ?>
    
    <?php if (!empty($participants_non_assignes)): ?>
        <div class="section-title">PARTICIPANTS NON ASSIGNÉS</div>
        
        <?php foreach ($participants_non_assignes as $participant): ?>
            <div class="participant-item">
                • <?php echo htmlspecialchars($participant['nom'] . ' ' . $participant['prenom']); ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="section-title">LISTE COMPLÈTE DES PARTICIPANTS CONFIRMÉS</div>
    
    <table class="participants-table">
        <thead>
            <tr>
                <th>Nom Prénom</th>
                <th>Téléphone</th>
                <th>Email</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($participants as $participant): ?>
                <tr>
                    <td><?php echo htmlspecialchars($participant['nom'] . ' ' . $participant['prenom']); ?></td>
                    <td><?php echo htmlspecialchars($participant['telephone'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($participant['email'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Document généré automatiquement par le système de gestion des événements</p>
        <p>Association Vélo - <?php echo date('d/m/Y à H:i'); ?></p>
    </div>
</body>
</html>
<?php
?>