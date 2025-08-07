<?php
session_start();

// Inclusion des fonctions communes et de la connexion à la base de données
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Vérification de l'authentification et des droits d'accès
check_authentication('admin');

// Récupération de tous les adhérents avec leurs photos
$adherents = get_all_adherents_with_categories($conn);

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
    <title>Trombinoscope des Adhérents - Association Vélo</title>
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
        .adherents-count {
            background-color: #e7f3ff;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            color: #0056b3;
        }
        .trombinoscope {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            gap: 20px;
        }
        .adherent-card {
            width: 180px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background-color: white;
        }
        .adherent-card.minor {
            border-color: #ffc107;
            background-color: #fff9e6;
        }
        .photo-container {
            width: 100%;
            height: 180px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }
        .photo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .adherent-info {
            padding: 10px;
            text-align: center;
        }
        .adherent-name {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .adherent-category {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            color: white;
            margin: 1px;
        }
        .categories-container {
            margin-top: 5px;
        }
        .age-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 5px;
        }
        .age-badge.mineur {
            background-color: #ffc107;
            color: #856404;
        }
        .age-badge.majeur {
            background-color: #28a745;
            color: white;
        }
        .no-adherents {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .text-muted-italic {
            color: #6c757d;
            font-style: italic;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
            clear: both;
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
            .trombinoscope {
                page-break-inside: auto;
            }
            .adherent-card {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">🖨️ Imprimer ce trombinoscope</button>
    
    <div class="header">
        <h1>TROMBINOSCOPE DES ADHÉRENTS</h1>
        <h2>Association Vélo</h2>
        <p>Document généré le <?php echo $date_export; ?></p>
    </div>
    
    <div class="adherents-count">
        Nombre total d'adhérents : <?php echo count($adherents); ?>
    </div>
    
    <?php if (count($adherents) > 0): ?>
        <div class="trombinoscope">
            <?php foreach ($adherents as $adherent): ?>
                <div class="adherent-card <?php echo $adherent['est_mineur'] ? 'minor' : ''; ?>">
                    <div class="photo-container">
                        <img src="<?php echo htmlspecialchars($adherent['photo']); ?>" alt="Photo de <?php echo htmlspecialchars($adherent['prenom'] . ' ' . $adherent['nom']); ?>">
                    </div>
                    <div class="adherent-info">
                        <div class="adherent-name">
                            <?php echo htmlspecialchars($adherent['prenom'] . ' ' . $adherent['nom']); ?>
                        </div>
                        <span class="age-badge <?php echo $adherent['est_mineur'] ? 'mineur' : 'majeur'; ?>">
                            <?php echo $adherent['age']; ?> ans
                        </span>
                        <?php if (!empty($adherent['categories_noms'])): ?>
                            <?php 
                            $categories_noms = explode(', ', $adherent['categories_noms']);
                            $categories_couleurs = explode(',', $adherent['categories_couleurs']);
                            ?>
                            <div class="categories-container">
                                <?php for ($i = 0; $i < count($categories_noms); $i++): ?>
                                    <span class="adherent-category" style="background-color: <?php echo htmlspecialchars(trim($categories_couleurs[$i])); ?>">
                                        <?php echo htmlspecialchars(trim($categories_noms[$i])); ?>
                                    </span>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-adherents">
            Aucun adhérent enregistré.
        </div>
    <?php endif; ?>
    
    <div class="footer">
        <p>Document généré automatiquement par le système de gestion des adhérents</p>
        <p>Association Vélo - <?php echo $date_export; ?></p>
    </div>
</body>
</html>