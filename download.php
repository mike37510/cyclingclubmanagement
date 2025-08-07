<?php
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$file = $_GET['file'] ?? '';

// Validation sécurisée
if (empty($file) || strpos($file, '..') !== false || strpos($file, '/') === 0) {
    die('Accès refusé');
}

// Chemin relatif vers le dossier sécurisé
$filepath = '../private_documents/' . $file;

if (!file_exists($filepath) || !is_file($filepath)) {
    die('Fichier non trouvé');
}

// Déterminer le type MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

// Headers pour le téléchargement
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Envoyer le fichier
readfile($filepath);
exit;
?>