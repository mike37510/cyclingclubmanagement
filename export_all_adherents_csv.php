<?php
session_start();

// Inclusion des fonctions communes et de la connexion à la base de données
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Vérification de l'authentification et des droits d'accès
check_authentication('admin');

// Récupération de tous les adhérents avec leurs catégories
$adherents = get_all_adherents_with_categories($conn);

// Configuration pour le CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="adherents_' . date('Y-m-d') . '.csv"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

// Créer le flux de sortie
$output = fopen('php://output', 'w');

// Ajouter le BOM UTF-8 pour Excel
fputs($output, "\xEF\xBB\xBF");

// En-têtes du CSV
$headers = [
    'N°',
    'Nom',
    'Prénom',
    'Date de naissance',
    'Âge',
    'Statut (Majeur/Mineur)',
    'Email',
    'Téléphone',
    'Catégories',
    'Droit à l\'image',
    'Tuteur 1 - Nom',
    'Tuteur 1 - Prénom',
    'Tuteur 1 - Email',
    'Tuteur 1 - Téléphone',
    'Tuteur 2 - Nom',
    'Tuteur 2 - Prénom',
    'Tuteur 2 - Email',
    'Tuteur 2 - Téléphone'
];

// Écrire les en-têtes
fputcsv($output, $headers, ';');

// Écrire les données des adhérents
foreach ($adherents as $index => $adherent) {
    // Formatage de la date de naissance
    $date_naissance = new DateTime($adherent['date_naissance']);
    $date_naissance_formatted = $date_naissance->format('d/m/Y');
    
    // Préparation des catégories
    $categories = !empty($adherent['categories_noms']) ? $adherent['categories_noms'] : 'Non définie';
    
    // Statut majeur/mineur
    $statut = $adherent['est_mineur'] ? 'Mineur' : 'Majeur';
    
    // Droit à l'image
    $droit_image = $adherent['droit_image'] ? 'Oui' : 'Non';
    
    // Ligne de données
    $row = [
        $index + 1,
        $adherent['nom'],
        $adherent['prenom'],
        $date_naissance_formatted,
        $adherent['age'] . ' ans',
        $statut,
        $adherent['email'],
        $adherent['telephone'],
        $categories,
        $droit_image,
        $adherent['tuteur_nom'] ?: '',
        $adherent['tuteur_prenom'] ?: '',
        $adherent['tuteur_email'] ?: '',
        $adherent['tuteur_telephone'] ?: '',
        $adherent['tuteur2_nom'] ?: '',
        $adherent['tuteur2_prenom'] ?: '',
        $adherent['tuteur2_email'] ?: '',
        $adherent['tuteur2_telephone'] ?: ''
    ];
    
    // Écrire la ligne
    fputcsv($output, $row, ';');
}

// Fermer le flux
fclose($output);
exit;
?>