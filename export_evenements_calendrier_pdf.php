<?php
session_start();

// Inclusion des fonctions communes et de la connexion à la base de données
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Vérification de l'authentification et des droits d'accès
check_authentication('admin');

// Date actuelle pour filtrer les événements à venir
$date_actuelle = date('Y-m-d');

// Tableau de correspondance pour les jours de la semaine en français
$jours_fr = [
    'Monday' => 'Lundi',
    'Tuesday' => 'Mardi',
    'Wednesday' => 'Mercredi',
    'Thursday' => 'Jeudi',
    'Friday' => 'Vendredi',
    'Saturday' => 'Samedi',
    'Sunday' => 'Dimanche'
];

$jours_fr_court = [
    'Monday' => 'Lun',
    'Tuesday' => 'Mar',
    'Wednesday' => 'Mer',
    'Thursday' => 'Jeu',
    'Friday' => 'Ven',
    'Saturday' => 'Sam',
    'Sunday' => 'Dim'
];

// Tableau de correspondance pour les mois en français
$mois_fr = [
    'January' => 'Janvier',
    'February' => 'Février',
    'March' => 'Mars',
    'April' => 'Avril',
    'May' => 'Mai',
    'June' => 'Juin',
    'July' => 'Juillet',
    'August' => 'Août',
    'September' => 'Septembre',
    'October' => 'Octobre',
    'November' => 'Novembre',
    'December' => 'Décembre'
];

// Calculer les 3 prochains mois
$mois_a_afficher = [];
for ($i = 0; $i < 3; $i++) {
    $date_mois = new DateTime($date_actuelle);
    $date_mois->modify("+$i month");
    $date_mois->modify('first day of this month');
    $mois_a_afficher[] = $date_mois;
}

// Récupération des événements pour les 3 prochains mois
$date_fin = clone $mois_a_afficher[2];
$date_fin->modify('last day of this month');
$date_fin_str = $date_fin->format('Y-m-d');

$events_data = get_events_by_date_range($conn, $date_actuelle, $date_fin_str);

$evenements = [];
foreach ($events_data as $row) {
    // Formatage de la date
    $date_obj = new DateTime($row['date']);
    $mois_annee = $date_obj->format('Y-m');
    $jour = $date_obj->format('j');
    
    // Récupération des participants confirmés pour cet événement
    $stmt_participants = $conn->prepare("SELECT COUNT(*) as total FROM participation WHERE evenement_id = ? AND statut = 'confirmé'");
    $stmt_participants->bind_param("i", $row['id']);
    $stmt_participants->execute();
    $result_participants = $stmt_participants->get_result();
    $participants = $result_participants->fetch_assoc();
    $row['nb_participants'] = $participants['total'];
    $stmt_participants->close();
    
    // Ajouter l'événement au tableau indexé par mois et jour
    if (!isset($evenements[$mois_annee])) {
        $evenements[$mois_annee] = [];
    }
    if (!isset($evenements[$mois_annee][$jour])) {
        $evenements[$mois_annee][$jour] = [];
    }
    $evenements[$mois_annee][$jour][] = $row;
}

// Date d'export
$date_export = date('d/m/Y');

// Configuration pour le PDF
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier des Événements - 3 Mois à Venir - Association Vélo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #0d6efd;
            margin: 0;
            font-size: 24px;
        }
        .header h2 {
            color: #666;
            margin: 5px 0;
            font-size: 18px;
        }
        .print-button {
            background-color: #0d6efd;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 20px;
        }
        .print-button:hover {
            background-color: #0b5ed7;
        }
        .month-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        .month-header {
            background-color: #0d6efd;
            color: white;
            text-align: center;
            padding: 15px;
            font-size: 20px;
            font-weight: bold;
        }
        .calendar-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .calendar-table th {
            background-color: #6c757d;
            color: white;
            text-align: center;
            padding: 10px;
            font-weight: bold;
        }
        .calendar-day {
            height: 100px;
            vertical-align: top;
            padding: 5px;
            border: 1px solid #dee2e6;
            position: relative;
        }
        .empty-day {
            background-color: #f8f9fa;
        }
        .day-number {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        .today {
            background-color: #e8f4f8;
            border: 2px solid #0d6efd;
        }
        .day-events {
            overflow-y: auto;
            max-height: 75px;
        }
        .event {
            background-color: #0dcaf0;
            color: white;
            border-radius: 3px;
            padding: 2px 4px;
            margin-bottom: 2px;
            font-size: 10px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .event-time {
            font-weight: bold;
            font-size: 9px;
        }
        .event-title {
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
        }
        .no-events {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
            margin-top: 20px;
        }
        @media print {
            .print-button {
                display: none;
            }
            body {
                margin: 0;
                background-color: white;
            }
            .container {
                max-width: 100%;
                padding: 0;
            }
            .month-container {
                box-shadow: none;
                margin-bottom: 20px;
                page-break-inside: avoid;
            }
            @page {
                size: landscape;
                margin: 1cm;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="print-button" onclick="window.print()">🖨️ Imprimer ce calendrier</button>
        
        <div class="header">
            <h1>CALENDRIER DES ÉVÉNEMENTS - 3 MOIS À VENIR</h1>
            <h2>Association Vélo</h2>
            <p>Document généré le <?php echo $date_export; ?></p>
        </div>
        
        <?php foreach ($mois_a_afficher as $date_mois): 
            $mois_annee = $date_mois->format('Y-m');
            $nom_mois = $mois_fr[$date_mois->format('F')];
            $annee = $date_mois->format('Y');
        ?>
            <div class="month-container">
                <div class="month-header">
                    <?php echo $nom_mois . ' ' . $annee; ?>
                </div>
                
                <table class="calendar-table">
                    <thead>
                        <tr>
                            <?php foreach (array_values($jours_fr_court) as $jour): ?>
                                <th><?php echo $jour; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Déterminer le jour de la semaine du premier jour du mois (0 = lundi, 6 = dimanche)
                        $premier_jour_semaine = $date_mois->format('N') - 1; // 1 = lundi, 7 = dimanche
                        
                        // Nombre de jours dans le mois
                        $nb_jours = intval($date_mois->format('t'));
                        
                        // Calculer le nombre de semaines nécessaires
                        $nb_semaines = ceil(($premier_jour_semaine + $nb_jours) / 7);
                        
                        // Générer le calendrier
                        $jour_courant = 1;
                        for ($semaine = 0; $semaine < $nb_semaines; $semaine++) {
                            echo "<tr>";
                            
                            for ($jour_semaine = 0; $jour_semaine < 7; $jour_semaine++) {
                                // Déterminer si la cellule contient un jour du mois actuel
                                if (($semaine == 0 && $jour_semaine < $premier_jour_semaine) || ($jour_courant > $nb_jours)) {
                                    // Cellule vide
                                    echo "<td class=\"calendar-day empty-day\"></td>";
                                } else {
                                    // Vérifier si c'est aujourd'hui
                                    $date_jour = $date_mois->format('Y-m-') . sprintf('%02d', $jour_courant);
                                    $est_aujourdhui = ($date_jour == date('Y-m-d'));
                                    $classe_aujourdhui = $est_aujourdhui ? 'today' : '';
                                    
                                    echo "<td class=\"calendar-day $classe_aujourdhui\">";
                                    echo "<div class=\"day-number\">$jour_courant</div>";
                                    
                                    // Afficher les événements de ce jour
                                    if (isset($evenements[$mois_annee][$jour_courant]) && !empty($evenements[$mois_annee][$jour_courant])) {
                                        echo "<div class=\"day-events\">";
                                        foreach ($evenements[$mois_annee][$jour_courant] as $evt) {
                                            echo "<div class=\"event\">";
                                            echo "<div class=\"event-time\">" . substr($evt['heure'], 0, 5) . "</div>";
                                            echo "<div class=\"event-title\" title=\"" . htmlspecialchars($evt['titre']) . " - " . htmlspecialchars($evt['lieu']) . "\">" . htmlspecialchars($evt['titre']) . "</div>";
                                            if (!empty($evt['lieu'])) {
                                                echo "<div style=\"font-size: 8px; color: #e6f7ff;\">" . htmlspecialchars($evt['lieu']) . "</div>";
                                            }
                                            if ($evt['nb_participants'] > 0) {
                                                echo "<div style=\"font-size: 8px; color: #e6f7ff;\">" . $evt['nb_participants'] . " part.</div>";
                                            }
                                            echo "</div>";
                                        }
                                        echo "</div>";
                                    }
                                    
                                    echo "</td>";
                                    $jour_courant++;
                                }
                            }
                            
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($evenements)): ?>
            <div class="no-events">
                Aucun événement n'est programmé pour les 3 prochains mois.
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Document généré automatiquement par le système de gestion des événements</p>
            <p>Association Vélo - <?php echo $date_export; ?></p>
        </div>
    </div>
</body>
</html>