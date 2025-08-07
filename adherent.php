<?php
session_start();

// Fonction pour générer un mot de passe aléatoire
function generateRandomPassword($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $allChars = $uppercase . $lowercase . $numbers;
    
    $password = '';
    
    // S'assurer qu'il y a au moins un caractère de chaque type
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    
    // Compléter avec des caractères aléatoires
    for ($i = 3; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }
    
    // Mélanger les caractères
    return str_shuffle($password);
}

// Inclusion des fonctions communes et de la connexion à la base de données
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Vérification de l'authentification et des droits d'accès
check_authentication('admin');

// Configuration de la page
$page_title = 'Asso Vélo - Gestion des Adhérents';

// Fonction pour redimensionner une image
function resizeImage($source, $destination, $maxWidth) {
    // Vérification que le fichier source existe
    if (!file_exists($source)) {
        error_log("resizeImage: Le fichier source n'existe pas: $source");
        return false;
    }
    
    // Vérification de la taille du fichier source
    $fileSize = filesize($source);
    error_log("resizeImage: Taille du fichier source: $fileSize octets pour $source");
    if ($fileSize === false || $fileSize === 0) {
        error_log("resizeImage: Fichier source vide ou impossible de déterminer sa taille: $source");
        return false;
    }
    
    // Vérification que le dossier de destination existe
    $destDir = dirname($destination);
    if (!file_exists($destDir)) {
        if (!mkdir($destDir, 0777, true)) {
            error_log("resizeImage: Impossible de créer le dossier de destination: $destDir");
            return false;
        }
    }
    
    // Vérification que le dossier de destination est accessible en écriture
    if (!is_writable($destDir)) {
        error_log("resizeImage: Le dossier de destination n'est pas accessible en écriture: $destDir");
        return false;
    }
    
    // Vérification que l'extension GD est chargée
    if (!extension_loaded('gd')) {
        error_log("resizeImage: L'extension GD n'est pas chargée");
        return false;
    }
    
    try {
        // Obtention des dimensions de l'image
        $imageInfo = @getimagesize($source);
        if ($imageInfo === false) {
            error_log("resizeImage: Impossible d'obtenir les dimensions de l'image: $source");
            return false;
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        error_log("resizeImage: Dimensions de l'image source: $width x $height pour $source");
        
        // Calcul des nouvelles dimensions
        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = ($height / $width) * $maxWidth;
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }
        
        // Arrondir les dimensions
        $newWidth = round($newWidth);
        $newHeight = round($newHeight);
        error_log("resizeImage: Nouvelles dimensions: $newWidth x $newHeight pour $destination");
        
        // Lecture du contenu du fichier source
        $sourceContent = @file_get_contents($source);
        if ($sourceContent === false) {
            error_log("resizeImage: Impossible de lire le contenu du fichier source: $source");
            return false;
        }
        
        // Création de la nouvelle image
        $sourceImage = @imagecreatefromstring($sourceContent);
        if (!$sourceImage) {
            error_log("resizeImage: Impossible de créer l'image source à partir du contenu: $source");
            error_log("resizeImage: Type MIME détecté: " . mime_content_type($source));
            // Vérifier les erreurs PHP
            $lastError = error_get_last();
            if ($lastError) {
                error_log("resizeImage: Dernière erreur PHP: " . print_r($lastError, true));
            }
            return false;
        }
        
        $destImage = imagecreatetruecolor($newWidth, $newHeight);
        if (!$destImage) {
            error_log("resizeImage: Impossible de créer l'image de destination");
            imagedestroy($sourceImage);
            return false;
        }
        
        // Préservation de la transparence pour les PNG
        imagealphablending($destImage, false);
        imagesavealpha($destImage, true);
        $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
        imagefilledrectangle($destImage, 0, 0, $newWidth, $newHeight, $transparent);
        
        // Redimensionnement
        $resampleResult = imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        if (!$resampleResult) {
            error_log("resizeImage: Échec du redimensionnement de l'image");
            imagedestroy($sourceImage);
            imagedestroy($destImage);
            return false;
        }
        
        // Enregistrement de l'image redimensionnée
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $result = false;
        
        // Vérification de l'extension avant de tenter d'enregistrer
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($extension, $allowed_extensions)) {
            error_log("resizeImage: Extension de fichier non prise en charge: $extension");
            imagedestroy($sourceImage);
            imagedestroy($destImage);
            return false;
        }
        
        // Tentative d'enregistrement selon le format
        try {
            if ($extension === 'jpg' || $extension === 'jpeg') {
                $result = imagejpeg($destImage, $destination, 90);
            } elseif ($extension === 'png') {
                $result = imagepng($destImage, $destination, 9);
            } elseif ($extension === 'gif') {
                $result = imagegif($destImage, $destination);
            }
            
            // Vérification du résultat de l'enregistrement
            if (!$result) {
                error_log("resizeImage: Échec de l'enregistrement de l'image redimensionnée: $destination");
                error_log("resizeImage: Vérification des permissions d'écriture: " . (is_writable(dirname($destination)) ? 'OK' : 'NON'));
                error_log("resizeImage: Espace disque disponible: " . disk_free_space($destDir) . " octets");
            }
        } catch (Exception $e) {
            error_log("resizeImage: Exception lors de l'enregistrement: " . $e->getMessage());
            $result = false;
        }
        
        // Libération de la mémoire
        imagedestroy($sourceImage);
        imagedestroy($destImage);
        
        if (!$result) {
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("resizeImage: Exception: " . $e->getMessage());
        return false;
    }
}

// Traitement AJAX pour l'ajout/modification/suppression/récupération d'adhérents
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    // Création des dossiers uploads s'ils n'existent pas
    if (!file_exists('uploads/adherents')) {
        mkdir('uploads/adherents', 0777, true);
    }
    
    // Ajout d'un adhérent
    if ($_POST['action'] === 'add') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $date_naissance = $_POST['date_naissance'] ?? '';
        
        if (empty($nom) || empty($prenom) || empty($date_naissance)) {
            $response['message'] = 'Tous les champs sont obligatoires';
        } else {
            $photo_path = 'img/default_user.svg'; // Image par défaut
            
            // Traitement de l'upload de photo
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['photo']['tmp_name'];
                $name = basename($_FILES['photo']['name']);
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                
                error_log("Upload photo: Traitement de la photo pour l'ajout d'un adhérent");
                error_log("Upload photo: Informations sur le fichier: " . print_r($_FILES['photo'], true));
                
                // Vérification que le fichier temporaire existe
                if (!file_exists($tmp_name)) {
                    $response['message'] = 'Erreur: Le fichier temporaire n\'existe pas';
                    error_log("Upload photo: Le fichier temporaire n'existe pas: $tmp_name");
                    echo json_encode($response);
                    exit;
                }
                
                // Vérification de la taille du fichier
                $file_size = filesize($tmp_name);
                if ($file_size > 5 * 1024 * 1024) { // 5 Mo max
                    $response['message'] = 'La taille du fichier dépasse 5 Mo';
                    error_log("Upload photo: Fichier trop volumineux: $file_size octets");
                    echo json_encode($response);
                    exit;
                }
                
                // Vérification de l'extension
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                error_log("Upload photo: Extension du fichier: $extension");
                
                if (in_array($extension, $allowed_extensions)) {
                    // Génération d'un nom unique
                    $new_name = uniqid('adherent_') . '.' . $extension;
                    $upload_path = 'uploads/adherents/' . $new_name;
                    error_log("Upload photo: Destination de la photo: $upload_path");
                    
                    // Vérification du dossier de destination
                    $upload_dir = dirname($upload_path);
                    if (!file_exists($upload_dir)) {
                        error_log("Upload photo: Tentative de création du répertoire: $upload_dir");
                        if (!mkdir($upload_dir, 0777, true)) {
                            $response['message'] = 'Erreur: Impossible de créer le dossier de destination';
                            error_log("Upload photo: Impossible de créer le dossier: $upload_dir");
                            echo json_encode($response);
                            exit;
                        }
                        error_log("Upload photo: Répertoire créé avec succès: $upload_dir");
                    }
                    
                    if (!is_writable($upload_dir)) {
                        $response['message'] = 'Erreur: Le dossier de destination n\'est pas accessible en écriture';
                        error_log("Upload photo: Dossier non accessible en écriture: $upload_dir");
                        echo json_encode($response);
                        exit;
                    }
                    
                    // Vérification du type MIME
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime_type = $finfo->file($tmp_name);
                    error_log("Upload photo: Type MIME détecté: $mime_type");
                    
                    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!in_array($mime_type, $allowed_mimes)) {
                        error_log("Upload photo: Type MIME non autorisé: $mime_type");
                        $response['message'] = 'Le fichier n\'est pas une image valide';
                        echo json_encode($response);
                        exit;
                    }
                    
                    // Redimensionnement et enregistrement
                    error_log("Upload photo: Tentative de redimensionnement de l'image: $tmp_name vers $upload_path");
                    if (resizeImage($tmp_name, $upload_path, 400)) {
                        $photo_path = $upload_path;
                        error_log("Upload photo: Image redimensionnée avec succès: $upload_path");
                    } else {
                        error_log("Upload photo: Échec du redimensionnement pour: $tmp_name vers $upload_path");
                        // Tentative de copie directe sans redimensionnement
                        error_log("Upload photo: Tentative de copie directe du fichier");
                        if (copy($tmp_name, $upload_path)) {
                            $photo_path = $upload_path;
                            error_log("Upload photo: Copie directe réussie: $upload_path");
                        } else {
                            $response['message'] = 'Erreur lors du traitement de l\'image: ' . error_get_last()['message'];
                            error_log("Upload photo: Échec de la copie directe: " . error_get_last()['message']);
                            echo json_encode($response);
                            exit;
                        }
                    }
                } else {
                    $response['message'] = 'Extension de fichier non autorisée. Utilisez JPG, JPEG, PNG ou GIF.';
                    error_log("Upload photo: Extension non autorisée: $extension");
                    echo json_encode($response);
                    exit;
                }
            } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Gestion des erreurs d'upload
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE => 'La taille du fichier dépasse la limite autorisée par PHP.',
                    UPLOAD_ERR_FORM_SIZE => 'La taille du fichier dépasse la limite autorisée par le formulaire.',
                    UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Pas de répertoire temporaire pour stocker le fichier.',
                    UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque.',
                    UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'envoi de fichier.'
                ];
                $error_code = $_FILES['photo']['error'];
                $error_message = isset($upload_errors[$error_code]) ? $upload_errors[$error_code] : 'Erreur inconnue lors de l\'upload.';
                
                $response['message'] = 'Erreur lors de l\'upload de la photo : ' . $error_message;
                error_log("Upload photo: Erreur PHP: " . $error_message . " (code: $error_code)");
                echo json_encode($response);
                exit;
            }
            
            // Récupération des nouveaux champs
            $email = trim($_POST['email'] ?? '');
            $telephone = trim($_POST['telephone'] ?? '');
            $droit_image = $_POST['droit_image'] ?? 'non';
            $categorie_id = !empty($_POST['categorie_id']) ? (int)$_POST['categorie_id'] : null;
            
            // Récupération des champs du tuteur légal
            $tuteur_nom = trim($_POST['tuteur_nom'] ?? '');
            $tuteur_prenom = trim($_POST['tuteur_prenom'] ?? '');
            $tuteur_telephone = trim($_POST['tuteur_telephone'] ?? '');
            $tuteur_email = trim($_POST['tuteur_email'] ?? '');
            $tuteur2_nom = trim($_POST['tuteur2_nom'] ?? '');
            $tuteur2_prenom = trim($_POST['tuteur2_prenom'] ?? '');
            $tuteur2_telephone = trim($_POST['tuteur2_telephone'] ?? '');
            $tuteur2_email = trim($_POST['tuteur2_email'] ?? '');

            // Insertion dans la base de données
            $stmt = $conn->prepare("INSERT INTO adherents (nom, prenom, date_naissance, email, telephone, photo, droit_image, tuteur_nom, tuteur_prenom, tuteur_telephone, tuteur_email, tuteur2_nom, tuteur2_prenom, tuteur2_telephone, tuteur2_email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssssssss", $nom, $prenom, $date_naissance, $email, $telephone, $photo_path, $droit_image, $tuteur_nom, $tuteur_prenom, $tuteur_telephone, $tuteur_email, $tuteur2_nom, $tuteur2_prenom, $tuteur2_telephone, $tuteur2_email);
            
            if ($stmt->execute()) {
                $adherent_id = $conn->insert_id;
                
                // Gestion des catégories multiples
                if (isset($_POST['categories']) && is_array($_POST['categories'])) {
                    $stmt_cat = $conn->prepare("INSERT INTO adherent_categories (adherent_id, categorie_id) VALUES (?, ?)");
                    foreach ($_POST['categories'] as $categorie_id) {
                        if (!empty($categorie_id)) {
                            $stmt_cat->bind_param("ii", $adherent_id, $categorie_id);
                            $stmt_cat->execute();
                        }
                    }
                    $stmt_cat->close();
                }
                
                // Création automatique du compte utilisateur
                $user_created = false;
                $generated_password = '';
                
$user_created = false;
$generated_password = '';
$username = ''; // Initialiser la variable

if (!empty($email)) {
    // Générer un nom d'utilisateur unique
    $username_base = strtolower($prenom . '.' . $nom);
    $username_base = preg_replace('/[^a-z0-9.]/', '', $username_base);
    $username = $username_base;
    
    // Vérifier l'unicité du nom d'utilisateur
    $counter = 1;
    while (true) {
        $check_username_stmt = $conn->prepare("SELECT id FROM utilisateurs WHERE username = ?");
        $check_username_stmt->bind_param("s", $username);
        $check_username_stmt->execute();
        $username_result = $check_username_stmt->get_result();
        
        if ($username_result->num_rows === 0) {
            break;
        }
        
        $username = $username_base . $counter;
        $counter++;
        $check_username_stmt->close();
    }
    $check_username_stmt->close();
    
    // Générer le mot de passe aléatoire
    $generated_password = generateRandomPassword(12);
    $hashed_password = password_hash($generated_password, PASSWORD_DEFAULT);
    
    // Créer le compte utilisateur avec le rôle 'user'
    $user_stmt = $conn->prepare("INSERT INTO utilisateurs (username, mot_de_passe, email, role) VALUES (?, ?, ?, 'user')");
    $user_stmt->bind_param("sss", $username, $hashed_password, $email);
    
    if ($user_stmt->execute()) {
        $user_id = $conn->insert_id;
        
        // Créer la liaison dans la table utilisateurs_adherents
        $liaison_stmt = $conn->prepare("INSERT INTO utilisateurs_adherents (utilisateur_id, adherent_id, principal) VALUES (?, ?, 1)");
        $liaison_stmt->bind_param("ii", $user_id, $adherent_id);
        
        if ($liaison_stmt->execute()) {
            $user_created = true;
            error_log("Compte utilisateur créé automatiquement: $username pour l'adhérent $adherent_id");
        } else {
            error_log("Erreur lors de la création de la liaison utilisateur-adhérent: " . $liaison_stmt->error);
            $response['debug_error'] = "Erreur liaison: " . $liaison_stmt->error;
            // Supprimer l'utilisateur créé en cas d'échec
            $delete_stmt = $conn->prepare("DELETE FROM utilisateurs WHERE id = ?");
            $delete_stmt->bind_param("i", $user_id);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
        $liaison_stmt->close();
    } else {
        error_log("Erreur lors de la création du compte utilisateur: " . $user_stmt->error);
        $response['debug_error'] = "Erreur utilisateur: " . $user_stmt->error;
    }
    $user_stmt->close();
                }
                
                $response['success'] = true;
                $response['message'] = 'Adhérent ajouté avec succès';
                $response['id'] = $adherent_id;
                
                // Ajouter les informations du compte créé dans la réponse
                if ($user_created) {
                    $response['user_created'] = true;
                    $response['username'] = $username;
                    $response['password'] = $generated_password;
                    $response['message'] .= "\n\nCompte utilisateur créé automatiquement:\nNom d'utilisateur: $username\nMot de passe: $generated_password";
                } else {
                    $response['user_created'] = false;
                    if (empty($email)) {
                        $response['message'] .= "\n\nAucun compte utilisateur créé (email manquant)";
                    } else {
                        $response['message'] .= "\n\nErreur lors de la création du compte utilisateur";
                        // Ajouter les détails de l'erreur si disponibles
                        if (isset($response['debug_error'])) {
                            $response['message'] .= ": " . $response['debug_error'];
                        }
                    }
                }
            } else {
                $response['message'] = 'Erreur lors de l\'ajout : ' . $stmt->error;
            }
            
            $stmt->close();
        }
    }
    
    // Modification d'un adhérent
    elseif ($_POST['action'] === 'edit') {
        $id = $_POST['id'] ?? 0;
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $date_naissance = $_POST['date_naissance'] ?? '';
        
        if (empty($id) || empty($nom) || empty($prenom) || empty($date_naissance)) {
            $response['message'] = 'Tous les champs sont obligatoires';
        } else {
            // Récupération de l'adhérent existant
            $stmt = $conn->prepare("SELECT photo FROM adherents WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $adherent = $result->fetch_assoc();
                $photo_path = $adherent['photo'];
                error_log("Modification adhérent: Photo actuelle: $photo_path");
                
                // Traitement de l'upload de photo si une nouvelle est fournie
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['photo']['tmp_name'];
                    $name = basename($_FILES['photo']['name']);
                    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    
                    error_log("Modification adhérent: Traitement de la nouvelle photo");
                    error_log("Modification adhérent: Informations sur le fichier: " . print_r($_FILES['photo'], true));
                    
                    // Vérification que le fichier temporaire existe
                    if (!file_exists($tmp_name)) {
                        $response['message'] = 'Erreur: Le fichier temporaire n\'existe pas';
                        error_log("Modification adhérent: Le fichier temporaire n'existe pas: $tmp_name");
                        echo json_encode($response);
                        exit;
                    }
                    
                    // Vérification de la taille du fichier
                    $file_size = filesize($tmp_name);
                    if ($file_size > 5 * 1024 * 1024) { // 5 Mo max
                        $response['message'] = 'La taille du fichier dépasse 5 Mo';
                        error_log("Modification adhérent: Fichier trop volumineux: $file_size octets");
                        echo json_encode($response);
                        exit;
                    }
                    
                    // Vérification de l'extension
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    error_log("Modification adhérent: Extension du fichier: $extension");
                    
                    if (in_array($extension, $allowed_extensions)) {
                        // Génération d'un nom unique
                        $new_name = uniqid('adherent_') . '.' . $extension;
                        $upload_path = 'uploads/adherents/' . $new_name;
                        error_log("Modification adhérent: Destination de la photo: $upload_path");
                        
                        // Vérification du dossier de destination
                        $upload_dir = dirname($upload_path);
                        if (!file_exists($upload_dir)) {
                            error_log("Modification adhérent: Tentative de création du répertoire: $upload_dir");
                            if (!mkdir($upload_dir, 0777, true)) {
                                $response['message'] = 'Erreur: Impossible de créer le dossier de destination';
                                error_log("Modification adhérent: Impossible de créer le dossier: $upload_dir");
                                echo json_encode($response);
                                exit;
                            }
                            error_log("Modification adhérent: Répertoire créé avec succès: $upload_dir");
                        }
                        
                        if (!is_writable($upload_dir)) {
                            $response['message'] = 'Erreur: Le dossier de destination n\'est pas accessible en écriture';
                            error_log("Modification adhérent: Dossier non accessible en écriture: $upload_dir");
                            echo json_encode($response);
                            exit;
                        }
                        
                        // Vérification du type MIME
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime_type = $finfo->file($tmp_name);
                        error_log("Modification adhérent: Type MIME détecté: $mime_type");
                        
                        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!in_array($mime_type, $allowed_mimes)) {
                            error_log("Modification adhérent: Type MIME non autorisé: $mime_type");
                            $response['message'] = 'Le fichier n\'est pas une image valide';
                            echo json_encode($response);
                            exit;
                        }
                        
                        // Redimensionnement et enregistrement
                        error_log("Modification adhérent: Tentative de redimensionnement de l'image: $tmp_name vers $upload_path");
                        if (resizeImage($tmp_name, $upload_path, 400)) {
                            // Suppression de l'ancienne photo si ce n'est pas l'image par défaut
                            if ($photo_path !== 'img/default_user.svg' && file_exists($photo_path)) {
                                error_log("Modification adhérent: Suppression de l'ancienne photo: $photo_path");
                                if (!unlink($photo_path)) {
                                    error_log("Modification adhérent: Impossible de supprimer l'ancienne photo: $photo_path");
                                } else {
                                    error_log("Modification adhérent: Ancienne photo supprimée avec succès");
                                }
                            }
                            $photo_path = $upload_path;
                            error_log("Modification adhérent: Image redimensionnée avec succès: $upload_path");
                        } else {
                            error_log("Modification adhérent: Échec du redimensionnement pour: $tmp_name vers $upload_path");
                            // Tentative de copie directe sans redimensionnement
                            error_log("Modification adhérent: Tentative de copie directe du fichier");
                            if (copy($tmp_name, $upload_path)) {
                                error_log("Modification adhérent: Copie directe réussie: $upload_path");
                                // Suppression de l'ancienne photo si ce n'est pas l'image par défaut
                                if ($photo_path !== 'img/default_user.svg' && file_exists($photo_path)) {
                                    error_log("Modification adhérent: Suppression de l'ancienne photo: $photo_path");
                                    if (!unlink($photo_path)) {
                                        error_log("Modification adhérent: Impossible de supprimer l'ancienne photo: " . error_get_last()['message']);
                                    } else {
                                        error_log("Modification adhérent: Ancienne photo supprimée avec succès");
                                    }
                                }
                                $photo_path = $upload_path;
                            } else {
                                error_log("Modification adhérent: Échec de la copie directe: " . error_get_last()['message']);
                                $response['message'] = 'Erreur lors du traitement de la photo: ' . error_get_last()['message'];
                                echo json_encode($response);
                                exit;
                            }
                        }
                    } else {
                        $response['message'] = 'Extension de fichier non autorisée. Utilisez JPG, JPEG, PNG ou GIF.';
                        error_log("Modification adhérent: Extension non autorisée: $extension");
                        echo json_encode($response);
                        exit;
                    }
                } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    // Gestion des erreurs d'upload
                    $upload_errors = [
                        UPLOAD_ERR_INI_SIZE => 'La taille du fichier dépasse la limite autorisée par PHP.',
                        UPLOAD_ERR_FORM_SIZE => 'La taille du fichier dépasse la limite autorisée par le formulaire.',
                        UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Pas de répertoire temporaire pour stocker le fichier.',
                        UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque.',
                        UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'envoi de fichier.'
                    ];
                    $error_code = $_FILES['photo']['error'];
                    $error_message = isset($upload_errors[$error_code]) ? $upload_errors[$error_code] : 'Erreur inconnue lors de l\'upload.';
                    
                    $response['message'] = 'Erreur lors de l\'upload de la photo : ' . $error_message;
                    error_log("Modification adhérent: Erreur PHP: " . $error_message . " (code: $error_code)");
                    echo json_encode($response);
                    exit;
                }
                
                // Récupération des nouveaux champs
                $email = trim($_POST['email'] ?? '');
                $telephone = trim($_POST['telephone'] ?? '');
                
                // Récupération des champs du tuteur légal
                $tuteur_nom = trim($_POST['tuteur_nom'] ?? '');
                $tuteur_prenom = trim($_POST['tuteur_prenom'] ?? '');
                $tuteur_telephone = trim($_POST['tuteur_telephone'] ?? '');
                $tuteur_email = trim($_POST['tuteur_email'] ?? '');
                
                // Récupération des nouveaux champs
                $email = trim($_POST['email'] ?? '');
                $telephone = trim($_POST['telephone'] ?? '');
                $droit_image = $_POST['droit_image'] ?? 'non';
                $categorie_id = !empty($_POST['categorie_id']) ? (int)$_POST['categorie_id'] : null;

                // Récupération des champs du tuteur légal
                $tuteur_nom = trim($_POST['tuteur_nom'] ?? '');
                $tuteur_prenom = trim($_POST['tuteur_prenom'] ?? '');
                $tuteur_telephone = trim($_POST['tuteur_telephone'] ?? '');
                $tuteur_email = trim($_POST['tuteur_email'] ?? '');
                $tuteur2_nom = trim($_POST['tuteur2_nom'] ?? '');
                $tuteur2_prenom = trim($_POST['tuteur2_prenom'] ?? '');
                $tuteur2_telephone = trim($_POST['tuteur2_telephone'] ?? '');
                $tuteur2_email = trim($_POST['tuteur2_email'] ?? '');

                // Mise à jour dans la base de données
                $stmt = $conn->prepare("UPDATE adherents SET nom = ?, prenom = ?, date_naissance = ?, email = ?, telephone = ?, photo = ?, droit_image = ?, tuteur_nom = ?, tuteur_prenom = ?, tuteur_telephone = ?, tuteur_email = ?, tuteur2_nom = ?, tuteur2_prenom = ?, tuteur2_telephone = ?, tuteur2_email = ? WHERE id = ?");
                $stmt->bind_param("sssssssssssssssi", $nom, $prenom, $date_naissance, $email, $telephone, $photo_path, $droit_image, $tuteur_nom, $tuteur_prenom, $tuteur_telephone, $tuteur_email, $tuteur2_nom, $tuteur2_prenom, $tuteur2_telephone, $tuteur2_email, $id);
                
                if ($stmt->execute()) {
                    // Supprimer les anciennes catégories
                    $stmt_del = $conn->prepare("DELETE FROM adherent_categories WHERE adherent_id = ?");
                    $stmt_del->bind_param("i", $id);
                    $stmt_del->execute();
                    $stmt_del->close();
                    
                    // Ajouter les nouvelles catégories
                    if (isset($_POST['categories']) && is_array($_POST['categories'])) {
                        $stmt_cat = $conn->prepare("INSERT INTO adherent_categories (adherent_id, categorie_id) VALUES (?, ?)");
                        foreach ($_POST['categories'] as $categorie_id) {
                            if (!empty($categorie_id)) {
                                $stmt_cat->bind_param("ii", $id, $categorie_id);
                                $stmt_cat->execute();
                            }
                        }
                        $stmt_cat->close();
                    }
                    $response['success'] = true;
                    $response['message'] = 'Adhérent modifié avec succès';
                } else {
                    $response['message'] = 'Erreur lors de la modification : ' . $stmt->error;
                }
            } else {
                $response['message'] = 'Adhérent non trouvé';
            }
            
            $stmt->close();
        }
    }
    
    // Suppression d'un adhérent
    elseif ($_POST['action'] === 'delete') {
        $id = $_POST['id'] ?? 0;
        
        if (empty($id)) {
            $response['message'] = 'ID de l\'adhérent manquant';
        } else {
            // Récupération du chemin de la photo
            $stmt = $conn->prepare("SELECT photo FROM adherents WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $adherent = $result->fetch_assoc();
                $photo_path = $adherent['photo'];
                
                // Suppression de la photo si ce n'est pas l'image par défaut
                if ($photo_path !== 'img/default_user.svg' && file_exists($photo_path)) {
                    unlink($photo_path);
                }
                
                // Suppression des participations associées
                $stmt = $conn->prepare("DELETE FROM participation WHERE adherent_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                
                // Suppression de l'adhérent
                $stmt = $conn->prepare("DELETE FROM adherents WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Adhérent supprimé avec succès';
                } else {
                    $response['message'] = 'Erreur lors de la suppression : ' . $stmt->error;
                }
            } else {
                $response['message'] = 'Adhérent non trouvé';
            }
            
            $stmt->close();
        }
    }
    
    // Récupération des catégories
    elseif ($_POST['action'] === 'get_categories') {
        $categories = get_active_categories($conn);
        $response['success'] = true;
        $response['categories'] = $categories;
    }
    
    // Recherche d'adhérents (AJAX)
    elseif ($_POST['action'] === 'search') {
        $page = intval($_POST['page'] ?? 1);
        $search = trim($_POST['search'] ?? '');
        $categories_filter = json_decode($_POST['categories_filter'] ?? '[]', true);
        $limit = 12; // Nombre d'adhérents par page
        $offset = ($page - 1) * $limit;
        
        $where_conditions = [];
        $params = [];
        $types = "";
        
        // Condition de recherche par nom/prénom
        if (!empty($search)) {
            $where_conditions[] = "(a.nom LIKE ? OR a.prenom LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= "ss";
        }
        
        // Condition de filtrage par catégories
        if (!empty($categories_filter) && is_array($categories_filter)) {
            $placeholders = str_repeat('?,', count($categories_filter) - 1) . '?';
            $where_conditions[] = "a.id IN (SELECT DISTINCT ac.adherent_id FROM adherent_categories ac WHERE ac.categorie_id IN ($placeholders))";
            foreach ($categories_filter as $cat_id) {
                $params[] = intval($cat_id);
                $types .= "i";
            }
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        // Requête pour compter le nombre total d'adhérents
        $count_sql = "SELECT COUNT(DISTINCT a.id) as total FROM adherents a 
                      LEFT JOIN adherent_categories ac ON a.id = ac.adherent_id
                      $where_clause";
        $stmt = $conn->prepare($count_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $total = $result->fetch_assoc()['total'];
        $total_pages = ceil($total / $limit);
        
        // Requête pour récupérer les adhérents avec leur catégorie
        $sql = "SELECT a.*, 
                COALESCE(GROUP_CONCAT(c.nom SEPARATOR ', '), '') as categories_noms,
                COALESCE(GROUP_CONCAT(c.couleur SEPARATOR ','), '') as categories_couleurs,
                COALESCE(GROUP_CONCAT(c.id SEPARATOR ','), '') as categories_ids
                FROM adherents a 
                LEFT JOIN adherent_categories ac ON a.id = ac.adherent_id
                LEFT JOIN categories c ON ac.categorie_id = c.id
                $where_clause
                GROUP BY a.id
                ORDER BY a.nom, a.prenom 
                LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($sql);
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $adherents = [];
        while ($row = $result->fetch_assoc()) {
            // Formatage de la date de naissance
            $date = new DateTime($row['date_naissance']);
            $row['date_naissance_formatted'] = $date->format('d/m/Y');
            $adherents[] = $row;
        }
        
        $response['success'] = true;
        $response['adherents'] = $adherents;
        $response['pagination'] = [
            'current' => $page,
            'total' => $total_pages
        ];
        
        $stmt->close();
    }
    
    echo json_encode($response);
    exit;
}
?>
<?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Gestion des adhérents</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdherentModal">
                <i class="bi bi-plus-circle"></i> Ajouter un adhérent
            </button>
        </div>
        
        <!-- Barre de recherche -->
        <div class="search-container mb-4">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un adhérent...">
                <button class="btn btn-outline-secondary" type="button" id="clearSearch">Effacer</button>
            </div>
        </div>
        
        <!-- Filtres par catégorie -->
        <div class="filters-container mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <button class="btn btn-link text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#categoryFilters" aria-expanded="false" aria-controls="categoryFilters">
                            <i class="bi bi-funnel"></i> Filtrer par catégorie
                        </button>
                    </h6>
                </div>
                <div id="categoryFilters" class="collapse">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-3" id="categoryCheckboxes">
                            <!-- Les checkboxes des catégories seront chargées ici -->
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearFilters">Effacer les filtres</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Indicateur de chargement -->
        <div id="loading" class="text-center mb-4 hidden">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
        </div>
        
        <!-- Liste des adhérents -->
        <div id="adherentsList" class="row">
            <!-- Les cartes des adhérents seront chargées ici via AJAX -->
        </div>
        
        <!-- Pagination -->
        <nav aria-label="Pagination des adhérents">
            <ul id="pagination" class="pagination">
                <!-- La pagination sera générée via JavaScript -->
            </ul>
        </nav>
    </div>
    
    <!-- Modal d'ajout d'adhérent -->
    <div class="modal fade" id="addAdherentModal" tabindex="-1" aria-labelledby="addAdherentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAdherentModalLabel">Ajouter un adhérent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <form id="addAdherentForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        <div class="mb-3">
                            <label for="prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                        </div>
                        <div class="mb-3">
                            <label for="date_naissance" class="form-label">Date de naissance</label>
                            <input type="date" class="form-control" id="date_naissance" name="date_naissance" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="exemple@email.com">
                        </div>
                        <div class="mb-3">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone" placeholder="0612345678">
                        </div>

                        <div class="mb-3">
                            <label for="droit_image" class="form-label">Droit à l'image</label>
                            <select class="form-select" id="droit_image" name="droit_image" required>
                                <option value="non">Non</option>
                                <option value="oui">Oui</option>
                            </select>
                            <div class="form-text">Autorisation d'utiliser l'image de l'adhérent pour la communication de l'association</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Catégories</label>
                            <div id="categories-checkboxes" class="border rounded p-2 categories-checkboxes">
                                <!-- Les checkboxes seront chargées via JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Champs tuteur légal (affichés seulement pour les mineurs) -->
                        <div id="guardianFields" class="border rounded p-3 mb-3 guardian-fields hidden">
                            <h6 class="text-primary mb-3"><i class="fas fa-user-shield"></i> Informations des tuteurs légaux</h6>
                            
                            <!-- Premier tuteur -->
                            <div class="border rounded p-2 mb-3 guardian-section">
                                <h6 class="text-secondary mb-2">Parent 1</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="tuteur_nom" class="form-label">Nom du parent 1 <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="tuteur_nom" name="tuteur_nom">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="tuteur_prenom" class="form-label">Prénom du parent 1 <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="tuteur_prenom" name="tuteur_prenom">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="tuteur_telephone" class="form-label">Téléphone du parent 1 <span class="text-danger">*</span></label>
                                            <input type="tel" class="form-control" id="tuteur_telephone" name="tuteur_telephone" placeholder="0612345678">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="tuteur_email" class="form-label">Email du parent 1 <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="tuteur_email" name="tuteur_email" placeholder="exemple@email.com">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Second tuteur -->
                            <div class="border rounded p-2 mb-3 guardian-section">
                                <h6 class="text-secondary mb-2">Parent 2 (optionnel)</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="tuteur2_nom" class="form-label">Nom du parent 2</label>
                                            <input type="text" class="form-control" id="tuteur2_nom" name="tuteur2_nom">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="tuteur2_prenom" class="form-label">Prénom du parent 2</label>
                                            <input type="text" class="form-control" id="tuteur2_prenom" name="tuteur2_prenom">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="tuteur2_telephone" class="form-label">Téléphone du parent 2</label>
                                            <input type="tel" class="form-control" id="tuteur2_telephone" name="tuteur2_telephone" placeholder="0612345678">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="tuteur2_email" class="form-label">Email du parent 2</label>
                                            <input type="email" class="form-control" id="tuteur2_email" name="tuteur2_email" placeholder="exemple@email.com">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="photo" class="form-label">Photo</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg,image/png,image/gif">
                            <div class="form-text">Format accepté : JPG, PNG ou GIF (max 5 Mo)</div>
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
    
    <!-- Modal de modification d'adhérent -->
    <div class="modal fade" id="editAdherentModal" tabindex="-1" aria-labelledby="editAdherentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAdherentModalLabel">Modifier un adhérent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <form id="editAdherentForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="mb-3">
                            <label for="edit_nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="edit_nom" name="nom" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="edit_prenom" name="prenom" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_date_naissance" class="form-label">Date de naissance</label>
                            <input type="date" class="form-control" id="edit_date_naissance" name="date_naissance" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" placeholder="exemple@email.com">
                        </div>
                        <div class="mb-3">
                            <label for="edit_telephone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="edit_telephone" name="telephone" placeholder="0612345678">
                        </div>

                        <div class="mb-3">
                            <label for="edit_droit_image" class="form-label">Droit à l'image</label>
                            <select class="form-select" id="edit_droit_image" name="droit_image" required>
                                <option value="non">Non</option>
                                <option value="oui">Oui</option>
                            </select>
                            <div class="form-text">Autorisation d'utiliser l'image de l'adhérent pour la communication de l'association</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Catégories</label>
                            <div id="edit-categories-checkboxes" class="border rounded p-2 categories-checkboxes">
                                <!-- Les checkboxes seront chargées via JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Champs tuteur légal pour modification (affichés seulement pour les mineurs) -->
                        <div id="editGuardianFields" class="border rounded p-3 mb-3 guardian-fields hidden">
                            <h6 class="text-primary mb-3"><i class="fas fa-user-shield"></i> Informations des tuteurs légaux</h6>
                            
                            <!-- Premier tuteur -->
                            <div class="border rounded p-2 mb-3 guardian-section">
                                <h6 class="text-secondary mb-2">Parent 1</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_tuteur_nom" class="form-label">Nom du parent 1 <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="edit_tuteur_nom" name="tuteur_nom">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_tuteur_prenom" class="form-label">Prénom du parent 1 <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="edit_tuteur_prenom" name="tuteur_prenom">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_tuteur_telephone" class="form-label">Téléphone du parent 1 <span class="text-danger">*</span></label>
                                            <input type="tel" class="form-control" id="edit_tuteur_telephone" name="tuteur_telephone" placeholder="0612345678">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_tuteur_email" class="form-label">Email du parent 1 <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="edit_tuteur_email" name="tuteur_email" placeholder="exemple@email.com">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Second tuteur -->
                            <div class="border rounded p-2 mb-3 guardian-section-white">
                                <h6 class="text-secondary mb-2">Parent 2 (optionnel)</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_tuteur2_nom" class="form-label">Nom du parent 2</label>
                                            <input type="text" class="form-control" id="edit_tuteur2_nom" name="tuteur2_nom">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_tuteur2_prenom" class="form-label">Prénom du parent 2</label>
                                            <input type="text" class="form-control" id="edit_tuteur2_prenom" name="tuteur2_prenom">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_tuteur2_telephone" class="form-label">Téléphone du parent 2</label>
                                            <input type="tel" class="form-control" id="edit_tuteur2_telephone" name="tuteur2_telephone" placeholder="0612345678">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_tuteur2_email" class="form-label">Email du parent 2</label>
                                            <input type="email" class="form-control" id="edit_tuteur2_email" name="tuteur2_email" placeholder="exemple@email.com">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_photo" class="form-label">Photo</label>
                            <div class="mb-2">
                                <img id="current_photo" src="" alt="Photo actuelle" class="img-thumbnail profile-photo">
                            </div>
                            <input type="file" class="form-control" id="edit_photo" name="photo" accept="image/jpeg,image/png,image/gif">
                            <div class="form-text">Format accepté : JPG, PNG ou GIF (max 5 Mo). Laissez vide pour conserver la photo actuelle.</div>
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
    
    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="deleteAdherentModal" tabindex="-1" aria-labelledby="deleteAdherentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAdherentModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer cet adhérent ? Cette action est irréversible.</p>
                    <p><strong>Nom : </strong><span id="delete_nom_prenom"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" id="confirmDelete" class="btn btn-danger">Supprimer</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour charger les catégories
function loadCategories() {
    const formData = new FormData();
    formData.append('action', 'get_categories');
    
    fetch('adherent.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const containerAdd = document.getElementById('categories-checkboxes');
            const containerEdit = document.getElementById('edit-categories-checkboxes');
            const containerFilter = document.getElementById('categoryCheckboxes');
            
            // Vider les conteneurs
            containerAdd.innerHTML = '';
            containerEdit.innerHTML = '';
            containerFilter.innerHTML = '';
            
            // Ajouter les catégories sous forme de checkboxes
            data.categories.forEach(category => {
                const checkboxHtml = `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="categories[]" value="${category.id}" id="cat_${category.id}">
                        <label class="form-check-label" for="cat_${category.id}">
                            <span class="badge" style="background-color: ${category.couleur}">${category.nom}</span>
                        </label>
                    </div>
                `;
                containerAdd.innerHTML += checkboxHtml;
                
                // Checkbox pour les filtres
                const filterCheckboxHtml = `
                    <div class="form-check form-check-inline">
                        <input class="form-check-input filter-checkbox" type="checkbox" name="filter_categories[]" value="${category.id}" id="filter_cat_${category.id}">
                        <label class="form-check-label" for="filter_cat_${category.id}">
                            <span class="badge" style="background-color: ${category.couleur}">${category.nom}</span>
                        </label>
                    </div>
                `;
                containerFilter.innerHTML += filterCheckboxHtml;
                
                const editCheckboxHtml = `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="categories[]" value="${category.id}" id="edit_cat_${category.id}">
                        <label class="form-check-label" for="edit_cat_${category.id}">
                            <span class="badge" style="background-color: ${category.couleur}">${category.nom}</span>
                        </label>
                    </div>
                `;
                containerEdit.innerHTML += editCheckboxHtml;
            });
            

        }
    })
    .catch(error => {
        console.error('Erreur lors du chargement des catégories:', error);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Variables globales
            let currentPage = 1;
            let currentSearch = '';
            let currentFilters = [];
            let deleteAdherentId = null;
            
    loadCategories();
            
            // Éléments DOM
            const searchInput = document.getElementById('searchInput');
            const clearSearchBtn = document.getElementById('clearSearch');

            const clearFiltersBtn = document.getElementById('clearFilters');
            const adherentsList = document.getElementById('adherentsList');
            const pagination = document.getElementById('pagination');
            const loading = document.getElementById('loading');
            
            // Formulaires
            const addAdherentForm = document.getElementById('addAdherentForm');
            const editAdherentForm = document.getElementById('editAdherentForm');
            
            // Modals
            const addAdherentModal = new bootstrap.Modal(document.getElementById('addAdherentModal'));
            const editAdherentModal = new bootstrap.Modal(document.getElementById('editAdherentModal'));
            const deleteAdherentModal = new bootstrap.Modal(document.getElementById('deleteAdherentModal'));
            
            // Chargement initial des adhérents
            loadAdherents();
            
            // Gestion de l'affichage des champs tuteur selon l'âge
            const dateNaissanceInput = document.getElementById('date_naissance');
            const editDateNaissanceInput = document.getElementById('edit_date_naissance');
            const guardianFields = document.getElementById('guardianFields');
            const editGuardianFields = document.getElementById('editGuardianFields');
            
            // Fonction pour calculer l'âge
            function calculateAge(birthDate) {
                const today = new Date();
                const birth = new Date(birthDate);
                let age = today.getFullYear() - birth.getFullYear();
                const monthDiff = today.getMonth() - birth.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                    age--;
                }
                return age;
            }
            
            // Fonction pour afficher/masquer les champs tuteur
            function toggleGuardianFields(dateInput, fieldsContainer) {
                if (dateInput.value) {
                    const age = calculateAge(dateInput.value);
                    if (age < 18) {
                        fieldsContainer.style.display = 'block';
                        // Rendre les champs obligatoires
                        fieldsContainer.querySelectorAll('input[required]').forEach(input => {
                            input.setAttribute('required', 'required');
                        });
                    } else {
                        fieldsContainer.style.display = 'none';
                        // Retirer l'obligation des champs
                        fieldsContainer.querySelectorAll('input').forEach(input => {
                            input.removeAttribute('required');
                            input.value = ''; // Vider les champs
                        });
                    }
                } else {
                    fieldsContainer.style.display = 'none';
                }
            }
            
            // Événements pour le formulaire d'ajout
            if (dateNaissanceInput && guardianFields) {
                dateNaissanceInput.addEventListener('change', function() {
                    toggleGuardianFields(this, guardianFields);
                });
            }
            
            // Événements pour le formulaire de modification
            if (editDateNaissanceInput && editGuardianFields) {
                editDateNaissanceInput.addEventListener('change', function() {
                    toggleGuardianFields(this, editGuardianFields);
                });
            }
            
            // Événement de recherche
            searchInput.addEventListener('input', function() {
                currentSearch = this.value.trim();
                currentPage = 1;
                loadAdherents();
            });
            
            // Effacer la recherche
            clearSearchBtn.addEventListener('click', function() {
                searchInput.value = '';
                currentSearch = '';
                currentPage = 1;
                loadAdherents();
            });
            
            // Effacer les filtres
            clearFiltersBtn.addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('input[name="filter_categories[]"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                currentFilters = [];
                currentPage = 1;
                loadAdherents();
            });
            
            // Soumission du formulaire d'ajout
            addAdherentForm.addEventListener('submit', function(e) {
                e.preventDefault();
                submitForm(this, function() {
                    addAdherentModal.hide();
                    addAdherentForm.reset();
                    loadAdherents();
                });
            });
            
            // Soumission du formulaire de modification
            editAdherentForm.addEventListener('submit', function(e) {
                e.preventDefault();
                submitForm(this, function() {
                    editAdherentModal.hide();
                    loadAdherents();
                });
            });
            
            // Confirmation de suppression
            document.getElementById('confirmDelete').addEventListener('click', function() {
                if (deleteAdherentId) {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id', deleteAdherentId);
                    
                    fetch('adherent.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            deleteAdherentModal.hide();
                            loadAdherents();
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
            
            // Fonction pour charger les adhérents
            function loadAdherents() {
                loading.style.display = 'block';
                adherentsList.innerHTML = '';
                
                const formData = new FormData();
                formData.append('action', 'search');
                formData.append('search', currentSearch);
                formData.append('page', currentPage);
                formData.append('categories_filter', JSON.stringify(currentFilters));
                
                fetch('adherent.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';
                    
                    if (data.success) {
                        // Affichage des adhérents
                        if (data.adherents.length === 0) {
                            adherentsList.innerHTML = '<div class="col-12 text-center"><p>Aucun adhérent trouvé</p></div>';
                        } else {
                            data.adherents.forEach(adherent => {
                                adherentsList.appendChild(createAdherentCard(adherent));
                            });
                        }
                        
                        // Mise à jour de la pagination
                        updatePagination(data.pagination);
                    } else {
                        showAlert('danger', 'Erreur lors du chargement des adhérents');
                    }
                })
                .catch(error => {
                    loading.style.display = 'none';
                    console.error('Erreur:', error);
                    showAlert('danger', 'Une erreur est survenue lors du chargement des adhérents');
                });
            }
            
            // Fonction pour créer une carte d'adhérent
            function createAdherentCard(adherent) {
                const col = document.createElement('div');
                col.className = 'col-md-6 col-lg-4 col-xl-3 mb-4';
                
                const categories = adherent.categories_noms ? adherent.categories_noms.split(', ').map((nom, index) => {
                    const couleurs = adherent.categories_couleurs.split(',');
                    return `<span class="badge" style="background-color: ${couleurs[index]}">${nom}</span>`;
                }).join(' ') : '<span class="badge bg-secondary">Aucune catégorie</span>';
                const categorieHtml = categories;

                const droitImageHtml = adherent.droit_image === 'oui' ? 
                    '<i class="fas fa-camera text-success" title="Droit à l\'image accordé"></i>' : 
                    '<i class="fas fa-camera-slash text-danger" title="Droit à l\'image refusé"></i>';
                
                col.innerHTML = `
                    <div class="card adherent-card">
                        <img src="${adherent.photo}" class="adherent-photo" alt="${adherent.prenom} ${adherent.nom}">
                        <div class="card-body adherent-info">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title">${adherent.prenom} ${adherent.nom}</h5>
                                ${droitImageHtml}
                            </div>
                            <div class="mb-2">${categorieHtml}</div>
                            <p class="card-text">Né(e) le ${adherent.date_naissance_formatted}</p>
                            <div class="adherent-actions">
                                <button type="button" class="btn btn-sm btn-outline-primary edit-adherent" data-id="${adherent.id}">Modifier</button>
                                <button type="button" class="btn btn-sm btn-outline-info export-pdf" data-id="${adherent.id}" title="Exporter en PDF">📄 PDF</button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-adherent" data-id="${adherent.id}" data-nom="${adherent.prenom} ${adherent.nom}">Supprimer</button>
                            </div>
                        </div>
                    </div>
                `;
                
                // Ajout des événements sur les boutons
                col.querySelector('.edit-adherent').addEventListener('click', function() {
                    openEditModal(adherent);
                });
                
                col.querySelector('.export-pdf').addEventListener('click', function() {
                    window.open(`export_adherent_pdf.php?adherent_id=${adherent.id}`, '_blank');
                });
                
                col.querySelector('.delete-adherent').addEventListener('click', function() {
                    openDeleteModal(adherent.id, `${adherent.prenom} ${adherent.nom}`);
                });
                
                return col;
            }
            
            // Fonction pour mettre à jour la pagination
            function updatePagination(paginationData) {
                const paginationElement = document.querySelector('.pagination');
                paginationElement.innerHTML = '';
                
                if (paginationData.total <= 1) return;
                
                // Bouton précédent
                const prevLi = document.createElement('li');
                prevLi.className = `page-item ${paginationData.current === 1 ? 'disabled' : ''}`;
                prevLi.innerHTML = `<button class="page-link" ${paginationData.current === 1 ? 'disabled' : ''}>Précédent</button>`;
                prevLi.addEventListener('click', function() {
                    if (paginationData.current > 1) {
                        currentPage--;
                        loadAdherents();
                    }
                });
                paginationElement.appendChild(prevLi);
                
                // Pages
                for (let i = 1; i <= paginationData.total; i++) {
                    const pageLi = document.createElement('li');
                    pageLi.className = `page-item ${paginationData.current === i ? 'active' : ''}`;
                    pageLi.innerHTML = `<button class="page-link">${i}</button>`;
                    pageLi.addEventListener('click', function() {
                        currentPage = i;
                        loadAdherents();
                    });
                    paginationElement.appendChild(pageLi);
                }
                
                // Bouton suivant
                const nextLi = document.createElement('li');
                nextLi.className = `page-item ${paginationData.current === paginationData.total ? 'disabled' : ''}`;
                nextLi.innerHTML = `<button class="page-link" ${paginationData.current === paginationData.total ? 'disabled' : ''}>Suivant</button>`;
                nextLi.addEventListener('click', function() {
                    if (paginationData.current < paginationData.total) {
                        currentPage++;
                        loadAdherents();
                    }
                });
                paginationElement.appendChild(nextLi);
            }
            
            // Fonction pour ouvrir le modal de modification
            function openEditModal(adherent) {
                document.getElementById('edit_id').value = adherent.id;
                document.getElementById('edit_nom').value = adherent.nom;
                document.getElementById('edit_prenom').value = adherent.prenom;
                document.getElementById('edit_date_naissance').value = adherent.date_naissance;
                document.getElementById('edit_email').value = adherent.email || '';
                document.getElementById('edit_telephone').value = adherent.telephone || '';
                document.getElementById('current_photo').src = adherent.photo;
                
                // Remplissage des champs du tuteur légal
                document.getElementById('edit_tuteur_nom').value = adherent.tuteur_nom || '';
                document.getElementById('edit_tuteur_prenom').value = adherent.tuteur_prenom || '';
                document.getElementById('edit_tuteur_telephone').value = adherent.tuteur_telephone || '';
                document.getElementById('edit_tuteur_email').value = adherent.tuteur_email || '';
                document.getElementById('edit_tuteur2_nom').value = adherent.tuteur2_nom || '';
                document.getElementById('edit_tuteur2_prenom').value = adherent.tuteur2_prenom || '';
                document.getElementById('edit_tuteur2_telephone').value = adherent.tuteur2_telephone || '';
                document.getElementById('edit_tuteur2_email').value = adherent.tuteur2_email || '';

                // Gestion des catégories - d'abord décocher toutes les checkboxes
                const editCheckboxes = document.querySelectorAll('#edit-categories-checkboxes input[type="checkbox"]');
                editCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });

                // Ensuite cocher les catégories de l'adhérent en utilisant les IDs
                if (adherent.categories_ids && adherent.categories_ids.trim() !== '') {
                    const categoriesIds = adherent.categories_ids.split(',');
                    editCheckboxes.forEach(checkbox => {
                        if (categoriesIds.includes(checkbox.value)) {
                            checkbox.checked = true;
                        }
                    });
                }
                
                // Vérifier si l'adhérent est mineur et afficher les champs tuteur si nécessaire
                const editDateInput = document.getElementById('edit_date_naissance');
                const editGuardianFieldsDiv = document.getElementById('editGuardianFields');
                if (editDateInput && editGuardianFieldsDiv) {
                    toggleGuardianFields(editDateInput, editGuardianFieldsDiv);
                }
                
                editAdherentModal.show();
            }
            
            // Fonction pour ouvrir le modal de suppression
            function openDeleteModal(id, nomPrenom) {
                deleteAdherentId = id;
                document.getElementById('delete_nom_prenom').textContent = nomPrenom;
                deleteAdherentModal.show();
            }
            
            // Fonction pour soumettre un formulaire via AJAX
            function submitForm(form, callback) {
                const formData = new FormData(form);
                
                // Afficher des informations de débogage sur le formulaire
                console.log('Soumission du formulaire:', form.id);
                if (form.querySelector('input[type="file"]')) {
                    const fileInput = form.querySelector('input[type="file"]');
                    if (fileInput.files.length > 0) {
                        const file = fileInput.files[0];
                        console.log('Fichier sélectionné:', file.name, 'Type:', file.type, 'Taille:', file.size, 'octets');
                    } else {
                        console.log('Aucun fichier sélectionné');
                    }
                }
                
                fetch('adherent.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        console.error('Erreur HTTP:', response.status, response.statusText);
                    }
                    return response.json().catch(error => {
                        console.error('Erreur de parsing JSON:', error);
                        throw new Error('Erreur de parsing JSON');
                    });
                })
                .then(data => {
                    console.log('Réponse du serveur:', data);
                    if (data.success) {
                        showAlert('success', data.message);
                        if (typeof callback === 'function') callback();
                    } else {
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showAlert('danger', 'Une erreur est survenue lors de la soumission du formulaire');
                });
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