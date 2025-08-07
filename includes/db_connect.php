<?php
// Paramètres de connexion à la base de données
$host = '192.168.1.254';
$db_user = 'root';
$db_password = 'Lap1nrjK';
$db_name = 'assovelo3';

// Création de la connexion
$conn = new mysqli($host, $db_user, $db_password, $db_name);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Échec de la connexion à la base de données: " . $conn->connect_error);
}

// Configuration de l'encodage UTF-8
$conn->set_charset("utf8mb4");
?>