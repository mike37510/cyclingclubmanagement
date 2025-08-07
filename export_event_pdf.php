<?php
session_start();

// Inclusion des fonctions communes et de la connexion à la base de données
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Vérification de l'authentification et des droits d'accès
check_authentication('admin');

// Vérification de l'ID de l'événement
if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
    die('ID de l\'événement manquant');
}

$event_id = intval($_GET['event_id']);

// Récupération des informations de l'événement
$stmt = $conn->prepare("SELECT id, titre, date, heure, lieu, point_rdv, informations, nombre_participants_souhaite FROM evenements WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Événement non trouvé');
}

$event = $result->fetch_assoc();
$stmt->close();

// Récupération des participants confirmés
$participants = get_event_participants_with_categories($conn, $event_id);

// Formatage de la date
$date_obj = new DateTime($event['date']);
$date_formatted = $date_obj->format('d/m/Y');
$date_export = date('d/m/Y');

// Configuration pour le PDF
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche Événement - <?php echo htmlspecialchars($event['titre']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
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
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .event-info h3 {
            color: #007bff;
            margin-top: 0;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            width: 150px;
            color: #495057;
        }
        .info-value {
            flex: 1;
        }
        .info-value.informations {
            background-color: #f1f3f4;
            padding: 10px;
            border-radius: 4px;
            border-left: 3px solid #007bff;
        }
        .participants-section {
            margin-top: 30px;
        }
        .participants-section h3 {
            color: #007bff;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .participants-count {
            background-color: #e7f3ff;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            color: #0056b3;
        }
        .participants-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .participants-table th,
        .participants-table td {
            border: 1px solid #dee2e6;
            padding: 12px;
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
        .minor-participant {
            background-color: #fff3cd !important;
        }
        .guardian-info {
            font-size: 0.85em;
            color: #856404;
            font-style: italic;
        }
        .category-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.75em;
            font-weight: bold;
            color: white;
            margin: 1px;
        }
        .categories-container {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
        }
        .age-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.75em;
            font-weight: bold;
        }
        .age-badge.mineur {
            background-color: #ffc107;
            color: #856404;
        }
        .age-badge.majeur {
            background-color: #28a745;
            color: white;
        }
        .no-participants {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
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
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">🖨️ Imprimer cette fiche</button>
    
    <div class="header">
        <h1>FICHE ÉVÉNEMENT</h1>
        <h2>Association Vélo</h2>
        <p>Fiche générée le <?php echo $date_export; ?></p>
    </div>
    
    <div class="event-info">
        <h3>📅 Informations de l'événement</h3>
        <div class="info-row">
            <div class="info-label">Titre :</div>
            <div class="info-value"><?php echo htmlspecialchars($event['titre']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Date :</div>
            <div class="info-value"><?php echo $date_formatted; ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Heure :</div>
            <div class="info-value"><?php echo htmlspecialchars($event['heure']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Lieu (secteur) :</div>
            <div class="info-value"><?php echo htmlspecialchars($event['lieu']); ?></div>
        </div>
        <?php if (!empty($event['point_rdv'])): ?>
        <div class="info-row">
            <div class="info-label">Point de RDV :</div>
            <div class="info-value"><?php echo htmlspecialchars($event['point_rdv']); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($event['informations'])): ?>
        <div class="info-row">
            <div class="info-label">Informations :</div>
            <div class="info-value informations"><?php echo nl2br(htmlspecialchars($event['informations'])); ?></div>
        </div>
        <?php endif; ?>
        <!-- Suppression de la section coordonnées -->
    </div>
    
    <div class="participants-section">
        <h3>👥 Participants confirmés</h3>
        
        <div class="participants-count">
            Nombre total de participants : <?php echo count($participants); ?>
        </div>
        
        <?php if (count($participants) > 0): ?>
            <table class="participants-table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Âge</th>
                        <th>Catégorie</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th class="signature-header">Signature</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $index => $participant): ?>
                    <tr class="<?php echo $participant['est_mineur'] ? 'minor-participant' : ''; ?>">
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <?php echo htmlspecialchars($participant['nom']); ?>
                            <?php if ($participant['est_mineur']): ?>
                                <?php 
                                $tuteurs = [];
                                if (!empty($participant['tuteur_nom']) || !empty($participant['tuteur_prenom'])) {
                                    $tuteurs[] = 'Parent 1: ' . htmlspecialchars(trim($participant['tuteur_prenom'] . ' ' . $participant['tuteur_nom']));
                                }
                                if (!empty($participant['tuteur2_nom']) || !empty($participant['tuteur2_prenom'])) {
                                    $tuteurs[] = 'Parent 2: ' . htmlspecialchars(trim($participant['tuteur2_prenom'] . ' ' . $participant['tuteur2_nom']));
                                }
                                if (!empty($tuteurs)): ?>
                                    <div class="guardian-info">
                                        <?php echo implode('<br>', $tuteurs); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($participant['prenom']); ?>
                        </td>
                        <td>
                            <?php echo $participant['age']; ?> ans
                            <span class="age-badge <?php echo $participant['est_mineur'] ? 'mineur' : 'majeur'; ?>">
                                <?php echo $participant['est_mineur'] ? 'Mineur' : 'Majeur'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($participant['categories_noms'])): ?>
                                <?php 
                                $categories_noms = explode(', ', $participant['categories_noms']);
                                $categories_couleurs = explode(',', $participant['categories_couleurs']);
                                ?>
                                <div class="categories-container">
                                    <?php for ($i = 0; $i < count($categories_noms); $i++): ?>
                                        <span class="category-badge" style="background-color: <?php echo htmlspecialchars(trim($categories_couleurs[$i])); ?>">
                                            <?php echo htmlspecialchars(trim($categories_noms[$i])); ?>
                                        </span>
                                    <?php endfor; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted-italic">Non définie</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($participant['email']); ?>
                            <?php if ($participant['est_mineur']): ?>
                                <?php 
                                $emails_tuteurs = [];
                                if (!empty($participant['tuteur_email'])) {
                                    $emails_tuteurs[] = 'Parent 1: ' . htmlspecialchars($participant['tuteur_email']);
                                }
                                if (!empty($participant['tuteur2_email'])) {
                                    $emails_tuteurs[] = 'Parent 2: ' . htmlspecialchars($participant['tuteur2_email']);
                                }
                                if (!empty($emails_tuteurs)): ?>
                                    <div class="guardian-info">
                                        <?php echo implode('<br>', $emails_tuteurs); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($participant['telephone']); ?>
                            <?php if ($participant['est_mineur']): ?>
                                <?php 
                                $telephones_tuteurs = [];
                                if (!empty($participant['tuteur_telephone'])) {
                                    $telephones_tuteurs[] = 'Parent 1: ' . htmlspecialchars($participant['tuteur_telephone']);
                                }
                                if (!empty($participant['tuteur2_telephone'])) {
                                    $telephones_tuteurs[] = 'Parent 2: ' . htmlspecialchars($participant['tuteur2_telephone']);
                                }
                                if (!empty($telephones_tuteurs)): ?>
                                    <div class="guardian-info">
                                        <?php echo implode('<br>', $telephones_tuteurs); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="signature-cell"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-participants">
                Aucun participant confirmé pour cet événement.
            </div>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <p>Document généré automatiquement par le système de gestion des événements</p>
        <p>Association Vélo - <?php echo $date_export; ?></p>
    </div>

   
</body>
</html>
