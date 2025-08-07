<?php
session_start();

// Inclusion des fonctions communes et de la connexion à la base de données
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Vérification de l'authentification et des droits d'accès
check_authentication('admin');

// Récupération de tous les adhérents avec leurs catégories
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
    <title>Liste des Adhérents - Association Vélo</title>
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
        .adherents-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .adherents-table th,
        .adherents-table td {
            border: 1px solid #dee2e6;
            padding: 12px;
            text-align: left;
        }
        .adherents-table th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .adherents-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .minor-adherent {
            background-color: #fff3cd !important;
        }
        .guardian-info {
            font-size: 0.85em;
            color: #856404;
            font-style: italic;
        }
        .age-badge {
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
    <button class="print-button" onclick="window.print()">🖨️ Imprimer cette liste</button>
    
    <div class="header">
        <h1>LISTE DES ADHÉRENTS</h1>
        <h2>Association Vélo</h2>
        <p>Liste générée le <?php echo $date_export; ?></p>
    </div>
    
    <div class="adherents-count">
        Nombre total d'adhérents : <?php echo count($adherents); ?>
    </div>
    
    <?php if (count($adherents) > 0): ?>
        <table class="adherents-table">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Âge</th>
                    <th>Catégorie</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adherents as $index => $adherent): ?>
                <tr class="<?php echo $adherent['est_mineur'] ? 'minor-adherent' : ''; ?>">
                    <td><?php echo $index + 1; ?></td>
                    <td>
                        <?php echo htmlspecialchars($adherent['nom']); ?>
                        <?php if ($adherent['est_mineur']): ?>
                            <?php 
                            $tuteurs = [];
                            if (!empty($adherent['tuteur_nom']) || !empty($adherent['tuteur_prenom'])) {
                                $tuteurs[] = 'Parent 1: ' . htmlspecialchars(trim($adherent['tuteur_prenom'] . ' ' . $adherent['tuteur_nom']));
                            }
                            if (!empty($adherent['tuteur2_nom']) || !empty($adherent['tuteur2_prenom'])) {
                                $tuteurs[] = 'Parent 2: ' . htmlspecialchars(trim($adherent['tuteur2_prenom'] . ' ' . $adherent['tuteur2_nom']));
                            }
                            if (!empty($tuteurs)): ?>
                                <div class="guardian-info">
                                    <?php echo implode('<br>', $tuteurs); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($adherent['prenom']); ?>
                    </td>
                    <td>
                        <?php echo $adherent['age']; ?> ans
                        <span class="age-badge <?php echo $adherent['est_mineur'] ? 'mineur' : 'majeur'; ?>">
                            <?php echo $adherent['est_mineur'] ? 'Mineur' : 'Majeur'; ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($adherent['categories_noms'])): ?>
                            <?php 
                            $categories_noms = explode(', ', $adherent['categories_noms']);
                            $categories_couleurs = explode(',', $adherent['categories_couleurs']);
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
                        <?php echo htmlspecialchars($adherent['email']); ?>
                        <?php if ($adherent['est_mineur']): ?>
                            <?php 
                            $emails_tuteurs = [];
                            if (!empty($adherent['tuteur_email'])) {
                                $emails_tuteurs[] = 'Parent 1: ' . htmlspecialchars($adherent['tuteur_email']);
                            }
                            if (!empty($adherent['tuteur2_email'])) {
                                $emails_tuteurs[] = 'Parent 2: ' . htmlspecialchars($adherent['tuteur2_email']);
                            }
                            if (!empty($emails_tuteurs)): ?>
                                <div class="guardian-info">
                                    <?php echo implode('<br>', $emails_tuteurs); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($adherent['telephone']); ?>
                        <?php if ($adherent['est_mineur']): ?>
                            <?php 
                            $telephones_tuteurs = [];
                            if (!empty($adherent['tuteur_telephone'])) {
                                $telephones_tuteurs[] = 'Parent 1: ' . htmlspecialchars($adherent['tuteur_telephone']);
                            }
                            if (!empty($adherent['tuteur2_telephone'])) {
                                $telephones_tuteurs[] = 'Parent 2: ' . htmlspecialchars($adherent['tuteur2_telephone']);
                            }
                            if (!empty($telephones_tuteurs)): ?>
                                <div class="guardian-info">
                                    <?php echo implode('<br>', $telephones_tuteurs); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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