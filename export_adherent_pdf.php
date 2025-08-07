<?php
session_start();

// Inclusion des fonctions communes et de la connexion à la base de données
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Vérification de l'authentification et des droits d'accès
check_authentication('admin');

// Vérification de l'ID de l'adhérent
if (!isset($_GET['adherent_id']) || empty($_GET['adherent_id'])) {
    die('ID de l\'adhérent manquant');
}

$adherent_id = intval($_GET['adherent_id']);

// Récupération des informations de l'adhérent avec catégories multiples
$adherent = get_adherent_with_categories($conn, $adherent_id);

if (!$adherent) {
    die('Adhérent non trouvé');
}

// Formatage de la date
$date_naissance_formatted = $date_naissance->format('d/m/Y');
$date_export = date('d/m/Y');

// Configuration pour le PDF
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche Adhérent - <?php echo htmlspecialchars($adherent['prenom'] . ' ' . $adherent['nom']); ?></title>
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
        .adherent-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .adherent-info h3 {
            color: #007bff;
            margin-top: 0;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .guardian-info {
            background-color: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #ffc107;
        }
        .guardian-info h3 {
            color: #856404;
            margin-top: 0;
            border-bottom: 1px solid #ffeaa7;
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
        .photo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        .photo-section img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }
        .age-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
            margin-left: 10px;
        }
        .age-badge.mineur {
            background-color: #ffc107;
            color: #856404;
        }
        .age-badge.majeur {
            background-color: #28a745;
            color: white;
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
        .category-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            margin: 2px;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.3);
        }
        .categories-container {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">🖨️ Imprimer cette fiche</button>
    
    <div class="header">
        <h1>FICHE ADHÉRENT</h1>
        <h2>Association Vélo</h2>
        <p>Fiche générée le <?php echo $date_export; ?></p>
    </div>
    
    <?php if (!empty($adherent['photo']) && file_exists($adherent['photo'])): ?>
    <div class="photo-section">
        <img src="<?php echo htmlspecialchars($adherent['photo']); ?>" alt="Photo de <?php echo htmlspecialchars($adherent['prenom'] . ' ' . $adherent['nom']); ?>">
    </div>
    <?php endif; ?>
    
    <div class="adherent-info">
        <h3>👤 Informations personnelles
            <span class="age-badge <?php echo $est_mineur ? 'mineur' : 'majeur'; ?>">
                <?php echo $age; ?> ans <?php echo $est_mineur ? '(Mineur)' : '(Majeur)'; ?>
            </span>
            <?php if (!empty($adherent['categories_noms'])): ?>
                <div class="categories-container">
                    <?php 
                    $categories_noms = explode(', ', $adherent['categories_noms']);
                    $categories_couleurs = explode(',', $adherent['categories_couleurs']);
                    for ($i = 0; $i < count($categories_noms); $i++): 
                    ?>
                        <span class="category-badge" style="background-color: <?php echo htmlspecialchars(trim($categories_couleurs[$i])); ?>">
                            <?php echo htmlspecialchars(trim($categories_noms[$i])); ?>
                        </span>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </h3>
        <div class="info-row">
            <div class="info-label">Nom :</div>
            <div class="info-value"><?php echo htmlspecialchars($adherent['nom']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Prénom :</div>
            <div class="info-value"><?php echo htmlspecialchars($adherent['prenom']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Date de naissance :</div>
            <div class="info-value"><?php echo $date_naissance_formatted; ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Âge :</div>
            <div class="info-value"><?php echo $age; ?> ans</div>
        </div>
        <div class="info-row">
            <div class="info-label">Email :</div>
            <div class="info-value"><?php echo !empty($adherent['email']) ? htmlspecialchars($adherent['email']) : 'Non renseigné'; ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Téléphone :</div>
            <div class="info-value"><?php echo !empty($adherent['telephone']) ? htmlspecialchars($adherent['telephone']) : 'Non renseigné'; ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Catégories :</div>
            <div class="info-value">
                <?php if (!empty($adherent['categories_noms'])): ?>
                    <div class="categories-container">
                        <?php 
                        $categories_noms = explode(', ', $adherent['categories_noms']);
                        $categories_couleurs = explode(',', $adherent['categories_couleurs']);
                        for ($i = 0; $i < count($categories_noms); $i++): 
                        ?>
                            <span class="category-badge" style="background-color: <?php echo htmlspecialchars(trim($categories_couleurs[$i])); ?>">
                                <?php echo htmlspecialchars(trim($categories_noms[$i])); ?>
                            </span>
                        <?php endfor; ?>
                    </div>
                <?php else: ?>
                    Non renseignées
                <?php endif; ?>
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Droit à l'image :</div>
            <div class="info-value">
                <span class="image-rights <?php echo $adherent['droit_image'] === 'oui' ? 'oui' : 'non'; ?>">
                    <?php echo $adherent['droit_image'] === 'oui' ? '✓ Autorisé' : '✗ Non autorisé'; ?>
                </span>
            </div>
        </div>
    </div>
    
    <?php if ($est_mineur): ?>
    <?php 
    // Vérifier si au moins un tuteur est renseigné
    $tuteur1_existe = !empty($adherent['tuteur_nom']) || !empty($adherent['tuteur_prenom']) || !empty($adherent['tuteur_email']) || !empty($adherent['tuteur_telephone']);
    $tuteur2_existe = !empty($adherent['tuteur2_nom']) || !empty($adherent['tuteur2_prenom']) || !empty($adherent['tuteur2_email']) || !empty($adherent['tuteur2_telephone']);
    ?>
    
    <?php if ($tuteur1_existe): ?>
    <div class="guardian-info">
        <h3>👨‍👩‍👧‍👦 Informations du Parent 1</h3>
        <div class="info-row">
            <div class="info-label">Nom :</div>
            <div class="info-value"><?php echo !empty($adherent['tuteur_nom']) ? htmlspecialchars($adherent['tuteur_nom']) : 'Non renseigné'; ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Prénom :</div>
            <div class="info-value"><?php echo !empty($adherent['tuteur_prenom']) ? htmlspecialchars($adherent['tuteur_prenom']) : 'Non renseigné'; ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Email :</div>
            <div class="info-value"><?php echo !empty($adherent['tuteur_email']) ? htmlspecialchars($adherent['tuteur_email']) : 'Non renseigné'; ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Téléphone :</div>
            <div class="info-value"><?php echo !empty($adherent['tuteur_telephone']) ? htmlspecialchars($adherent['tuteur_telephone']) : 'Non renseigné'; ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($tuteur2_existe): ?>
    <div class="guardian-info">
        <h3>👨‍👩‍👧‍👦 Informations du Parent 2</h3>
        <div class="info-row">
            <div class="info-label">Nom :</div>
            <div class="info-value"><?php echo !empty($adherent['tuteur2_nom']) ? htmlspecialchars($adherent['tuteur2_nom']) : 'Non renseigné'; ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Prénom :</div>
            <div class="info-value"><?php echo !empty($adherent['tuteur2_prenom']) ? htmlspecialchars($adherent['tuteur2_prenom']) : 'Non renseigné'; ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Email :</div>
            <div class="info-value"><?php echo !empty($adherent['tuteur2_email']) ? htmlspecialchars($adherent['tuteur2_email']) : 'Non renseigné'; ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Téléphone :</div>
            <div class="info-value"><?php echo !empty($adherent['tuteur2_telephone']) ? htmlspecialchars($adherent['tuteur2_telephone']) : 'Non renseigné'; ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!$tuteur1_existe && !$tuteur2_existe): ?>
    <div class="guardian-info">
        <h3>👨‍👩‍👧‍👦 Informations des tuteurs légaux</h3>
        <p class="centered-warning">Aucune information de tuteur légal renseignée</p>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    
    <div class="footer">
        <p>Document généré automatiquement par le système de gestion des adhérents</p>
        <p>Association Vélo - <?php echo $date_export; ?></p>
    </div>
</body>
</html>