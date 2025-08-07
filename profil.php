<?php
session_start();

// Inclusion des fonctions communes et de la connexion à la base de données
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Vérification de l'authentification
check_authentication();

// Récupération des adhérents liés à l'utilisateur
$adherents_lies = get_user_linked_adherents($conn, $_SESSION['user_id']);

if (empty($adherents_lies)) {
    // L'utilisateur n'a pas de compte adhérent lié
    header('Location: index.php');
    exit;
}

// Déterminer l'adhérent à afficher (principal ou premier de la liste)
$adherent_actuel = $adherents_lies[0];
$adherent_id = $adherent_actuel['adherent_id'];

// Si un adhérent spécifique est demandé via GET et qu'il fait partie des adhérents liés
if (isset($_GET['adherent_id'])) {
    $requested_id = (int)$_GET['adherent_id'];
    foreach ($adherents_lies as $adherent) {
        if ($adherent['adherent_id'] == $requested_id) {
            $adherent_id = $requested_id;
            $adherent_actuel = $adherent;
            break;
        }
    }
}

// Configuration de la page
$page_title = 'Asso Vélo - Mon Profil';

// Fonction pour redimensionner une image (même que dans adherent.php)
function resizeImage($source, $destination, $maxWidth) {
    // ... (copier la fonction complète depuis adherent.php)
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

// Traitement AJAX pour la modification du profil
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    // Vérifier que l'adhérent modifié appartient bien à l'utilisateur connecté
    $adherent_to_modify = $_POST['adherent_id'] ?? $adherent_id;
    $is_authorized = false;
    foreach ($adherents_lies as $adherent) {
        if ($adherent['adherent_id'] == $adherent_to_modify) {
            $is_authorized = true;
            break;
        }
    }
    
    if (!$is_authorized) {
        $response['message'] = 'Vous n\'êtes pas autorisé à modifier cet adhérent';
        echo json_encode($response);
        exit;
    }
    
    // Création des dossiers uploads s'ils n'existent pas
    if (!file_exists('uploads/adherents')) {
        mkdir('uploads/adherents', 0777, true);
    }
    
    if ($_POST['action'] === 'update_profile') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $date_naissance = $_POST['date_naissance'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $droit_image = $_POST['droit_image'] ?? 'non';
        $categories_ids = $_POST['categories'] ?? []; // Changer de 'categories_ids' à 'categories'
        
        if (empty($nom) || empty($prenom) || empty($date_naissance)) {
            $response['message'] = 'Les champs nom, prénom et date de naissance sont obligatoires';
        } else {
            // Récupération de l'adhérent existant
            $stmt = $conn->prepare("SELECT photo FROM adherents WHERE id = ?");
            $stmt->bind_param("i", $adherent_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $adherent = $result->fetch_assoc();
                $photo_path = $adherent['photo'];
                
                // Traitement de l'upload de photo si une nouvelle est fournie
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['photo']['tmp_name'];
                    $name = basename($_FILES['photo']['name']);
                    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    
                    // Vérification de la taille du fichier
                    $file_size = filesize($tmp_name);
                    if ($file_size > 5 * 1024 * 1024) { // 5 Mo max
                        $response['message'] = 'La taille du fichier dépasse 5 Mo';
                        echo json_encode($response);
                        exit;
                    }
                    
                    // Vérification de l'extension
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($extension, $allowed_extensions)) {
                        // Génération d'un nom unique
                        $new_name = uniqid('adherent_') . '.' . $extension;
                        $upload_path = 'uploads/adherents/' . $new_name;
                        
                        // Vérification du type MIME
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime_type = $finfo->file($tmp_name);
                        
                        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!in_array($mime_type, $allowed_mimes)) {
                            $response['message'] = 'Le fichier n\'est pas une image valide';
                            echo json_encode($response);
                            exit;
                        }
                        
                        // Redimensionnement et enregistrement
                        if (resizeImage($tmp_name, $upload_path, 400)) {
                            // Suppression de l'ancienne photo si ce n'est pas l'image par défaut
                            if ($photo_path !== 'img/default_user.svg' && file_exists($photo_path)) {
                                unlink($photo_path);
                            }
                            $photo_path = $upload_path;
                        } else {
                            // Tentative de copie directe sans redimensionnement
                            if (copy($tmp_name, $upload_path)) {
                                // Suppression de l'ancienne photo si ce n'est pas l'image par défaut
                                if ($photo_path !== 'img/default_user.svg' && file_exists($photo_path)) {
                                    unlink($photo_path);
                                }
                                $photo_path = $upload_path;
                            } else {
                                $response['message'] = 'Erreur lors du traitement de la photo';
                                echo json_encode($response);
                                exit;
                            }
                        }
                    } else {
                        $response['message'] = 'Extension de fichier non autorisée. Utilisez JPG, JPEG, PNG ou GIF.';
                        echo json_encode($response);
                        exit;
                    }
                }
                
                // Récupération des champs du tuteur légal
                $tuteur_nom = trim($_POST['tuteur_nom'] ?? '');
                $tuteur_prenom = trim($_POST['tuteur_prenom'] ?? '');
                $tuteur_telephone = trim($_POST['tuteur_telephone'] ?? '');
                $tuteur_email = trim($_POST['tuteur_email'] ?? '');
                
                // Mise à jour dans la base de données (sans categorie_id)
                $stmt = $conn->prepare("UPDATE adherents SET nom = ?, prenom = ?, date_naissance = ?, email = ?, telephone = ?, photo = ?, droit_image = ?, tuteur_nom = ?, tuteur_prenom = ?, tuteur_telephone = ?, tuteur_email = ? WHERE id = ?");
                $stmt->bind_param("sssssssssssi", $nom, $prenom, $date_naissance, $email, $telephone, $photo_path, $droit_image, $tuteur_nom, $tuteur_prenom, $tuteur_telephone, $tuteur_email, $adherent_id);
                
                if ($stmt->execute()) {
                    // Mise à jour des catégories
                    // Supprimer les anciennes associations
                    $stmt_del = $conn->prepare("DELETE FROM adherent_categories WHERE adherent_id = ?");
                    $stmt_del->bind_param("i", $adherent_id);
                    $stmt_del->execute();
                    $stmt_del->close();
                    
                    // Ajouter les nouvelles associations
                    if (!empty($categories_ids)) {
                        $stmt_cat = $conn->prepare("INSERT INTO adherent_categories (adherent_id, categorie_id) VALUES (?, ?)");
                        foreach ($categories_ids as $categorie_id) {
                            if (!empty($categorie_id)) {
                                $stmt_cat->bind_param("ii", $adherent_id, $categorie_id);
                                $stmt_cat->execute();
                            }
                        }
                        $stmt_cat->close();
                    }
                    
                    $response['success'] = true;
                    $response['message'] = 'Profil mis à jour avec succès';
                } else {
                    $response['message'] = 'Erreur lors de la mise à jour : ' . $stmt->error;
                }
                
                $stmt->close();
            }
        }
    }
    
    // Récupération des catégories
    elseif ($_POST['action'] === 'get_categories') {
        $categories = get_active_categories($conn);
        $response['success'] = true;
        $response['categories'] = $categories;
    }
    
    // Récupération du profil
    elseif ($_POST['action'] === 'get_profile') {
        // Récupération des informations de base de l'adhérent
        $stmt = $conn->prepare("SELECT * FROM adherents WHERE id = ?");
        $stmt->bind_param("i", $adherent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $adherent = $result->fetch_assoc();
            
            // Récupération des catégories de l'adhérent
            $stmt_cat = $conn->prepare("
                SELECT c.id, c.nom, c.couleur 
                FROM adherent_categories ac 
                JOIN categories c ON ac.categorie_id = c.id 
                WHERE ac.adherent_id = ?
            ");
            $stmt_cat->bind_param("i", $adherent_id);
            $stmt_cat->execute();
            $result_cat = $stmt_cat->get_result();
            
            $categories = [];
            while ($row = $result_cat->fetch_assoc()) {
                $categories[] = $row;
            }
            $adherent['categories'] = $categories;
            $stmt_cat->close();
            
            // Formatage de la date de naissance
            $date = new DateTime($adherent['date_naissance']);
            $adherent['date_naissance_formatted'] = $date->format('d/m/Y');
            
            $response['success'] = true;
            $response['adherent'] = $adherent;
        } else {
            $response['message'] = 'Profil non trouvé';
        }
        
        $stmt->close();
    }
    
    echo json_encode($response);
    exit;
}
?>
<?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Mon Profil</h1>
        </div>
        
        <!-- Indicateur de chargement -->
        <div id="loading" class="text-center mb-4 hidden">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
        </div>
        
        <!-- Affichage du profil -->
        <div id="profileDisplay" class="row">
            <!-- Le profil sera chargé ici via AJAX -->
        </div>
        
        <!-- Bouton pour modifier -->
        <div class="text-center mt-4">
            <button type="button" id="editProfileBtn" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                <i class="bi bi-pencil"></i> Modifier mon profil
            </button>
        </div>
    </div>
    
    <!-- Modal de modification du profil -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Modifier mon profil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <form id="editProfileForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nom" name="nom" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="prenom" name="prenom" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_naissance" class="form-label">Date de naissance <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_naissance" name="date_naissance" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="exemple@email.com">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telephone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="telephone" name="telephone" placeholder="0612345678">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="droit_image" class="form-label">Droit à l'image</label>
                                    <select class="form-select" id="droit_image" name="droit_image" required>
                                        <option value="non">Non</option>
                                        <option value="oui">Oui</option>
                                    </select>
                                    <div class="form-text">Autorisation d'utiliser votre image pour la communication de l'association</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Catégories</label>
                            <div id="categories-checkboxes" class="border rounded p-2 categories-checkboxes">
                                <!-- Les checkboxes seront chargées via JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Champs tuteur légal (affichés seulement pour les mineurs) -->
                        <div id="guardianFields" class="border rounded p-3 mb-3 guardian-fields hidden">
                            <h6 class="text-primary mb-3"><i class="fas fa-user-shield"></i> Informations du tuteur légal</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tuteur_nom" class="form-label">Nom du tuteur</label>
                                        <input type="text" class="form-control" id="tuteur_nom" name="tuteur_nom">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tuteur_prenom" class="form-label">Prénom du tuteur</label>
                                        <input type="text" class="form-control" id="tuteur_prenom" name="tuteur_prenom">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tuteur_telephone" class="form-label">Téléphone du tuteur</label>
                                        <input type="tel" class="form-control" id="tuteur_telephone" name="tuteur_telephone" placeholder="0612345678">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tuteur_email" class="form-label">Email du tuteur</label>
                                        <input type="email" class="form-control" id="tuteur_email" name="tuteur_email" placeholder="exemple@email.com">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="photo" class="form-label">Photo</label>
                            <div class="mb-2">
                                <img id="current_photo" src="" alt="Photo actuelle" class="img-thumbnail profile-photo">
                            </div>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg,image/png,image/gif">
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

    <script>
        // Fonction pour charger les catégories
        function loadCategories() {
            const formData = new FormData();
            formData.append('action', 'get_categories');
            
            fetch('profil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('categories-checkboxes');
                    
                    // Vider le conteneur
                    container.innerHTML = '';
                    
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
                        container.innerHTML += checkboxHtml;
                    });
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des catégories:', error);
            });
        }
        
        // Fonction pour charger le profil
        function loadProfile() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('profileDisplay').innerHTML = '';
            
            const formData = new FormData();
            formData.append('action', 'get_profile');
            
            fetch('profil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').style.display = 'none';
                
                if (data.success) {
                    displayProfile(data.adherent);
                } else {
                    showAlert('danger', 'Erreur lors du chargement du profil');
                }
            })
            .catch(error => {
                document.getElementById('loading').style.display = 'none';
                console.error('Erreur:', error);
                showAlert('danger', 'Une erreur est survenue lors du chargement du profil');
            });
        }
        
        // Fonction pour afficher le profil
        // Fonction pour afficher le profil
        function displayProfile(adherent) {
            let categoriesHtml = '';
            if (adherent.categories && adherent.categories.length > 0) {
                categoriesHtml = adherent.categories.map(cat => 
                    `<span class="badge me-1" style="background-color: ${cat.couleur}">${cat.nom}</span>`
                ).join('');
            } else {
                categoriesHtml = '<span class="badge bg-secondary">Aucune catégorie</span>';
            }
            
            const droitImageHtml = adherent.droit_image === 'oui' ? 
                '<i class="fas fa-camera text-success" title="Droit à l\'image accordé"></i>' : 
                '<i class="fas fa-camera-slash text-danger" title="Droit à l\'image refusé"></i>';
            
            // Calcul de l'âge pour déterminer si c'est un mineur
            const age = calculateAge(adherent.date_naissance);
            const isMinor = age < 18;
            
            const profileHtml = `
                <div class="col-12">
                    <div class="card adherent-card">
                        <div class="row g-0">
                            <div class="col-md-4 text-center p-4">
                                <img src="${adherent.photo}" class="img-fluid mb-3 profile-display-photo" alt="${adherent.prenom} ${adherent.nom}">
                                <h2 class="h4">${adherent.prenom} ${adherent.nom}</h2>
                                <div class="mb-2">${categoriesHtml}</div>
                                <p class="text-muted">Âge : ${age} ans ${isMinor ? '(Mineur)' : ''}</p>
                            </div>
                            <div class="col-md-8">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <h5 class="text-primary mb-3"><i class="fas fa-user"></i> Informations personnelles</h5>
                                            <p><strong>Date de naissance :</strong> ${adherent.date_naissance_formatted}</p>
                                            <p><strong>Email :</strong> ${adherent.email || '<span class="text-muted">Non renseigné</span>'}</p>
                                            <p><strong>Téléphone :</strong> ${adherent.telephone || '<span class="text-muted">Non renseigné</span>'}</p>
                                            <p><strong>Droit à l'image :</strong> ${droitImageHtml} ${adherent.droit_image === 'oui' ? 'Autorisé' : 'Non autorisé'}</p>
                                        </div>
                                        <div class="col-sm-6">
                                            ${isMinor && adherent.tuteur_nom ? `
                                                <h5 class="text-primary mb-3"><i class="fas fa-user-shield"></i> Tuteur légal</h5>
                                                <p><strong>Nom :</strong> ${adherent.tuteur_prenom} ${adherent.tuteur_nom}</p>
                                                <p><strong>Téléphone :</strong> ${adherent.tuteur_telephone || '<span class="text-muted">Non renseigné</span>'}</p>
                                                <p><strong>Email :</strong> ${adherent.tuteur_email || '<span class="text-muted">Non renseigné</span>'}</p>
                                            ` : (isMinor ? `
                                                <h5 class="text-warning mb-3"><i class="fas fa-exclamation-triangle"></i> Attention</h5>
                                                <p class="text-warning">Aucun tuteur légal renseigné pour ce mineur.</p>
                                                <p class="small text-muted">Veuillez compléter les informations du tuteur légal.</p>
                                            ` : '')}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('profileDisplay').innerHTML = profileHtml;
        }
        
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
                } else {
                    fieldsContainer.style.display = 'none';
                    // Vider les champs
                    fieldsContainer.querySelectorAll('input').forEach(input => {
                        input.value = '';
                    });
                }
            } else {
                fieldsContainer.style.display = 'none';
            }
        }
        
        // Fonction pour ouvrir le modal de modification
        function openEditModal(adherent) {
            document.getElementById('nom').value = adherent.nom;
            document.getElementById('prenom').value = adherent.prenom;
            document.getElementById('date_naissance').value = adherent.date_naissance;
            document.getElementById('email').value = adherent.email || '';
            document.getElementById('telephone').value = adherent.telephone || '';
            document.getElementById('droit_image').value = adherent.droit_image;
            document.getElementById('current_photo').src = adherent.photo;
            
            // Gestion des catégories - d'abord décocher toutes les checkboxes
            const checkboxes = document.querySelectorAll('#categories-checkboxes input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });

            // Ensuite cocher les catégories de l'adhérent
            if (adherent.categories && adherent.categories.length > 0) {
                checkboxes.forEach(checkbox => {
                    if (adherent.categories.some(cat => cat.id == checkbox.value)) {
                        checkbox.checked = true;
                    }
                });
            }
            
            // Remplissage des champs du tuteur légal
            document.getElementById('tuteur_nom').value = adherent.tuteur_nom || '';
            document.getElementById('tuteur_prenom').value = adherent.tuteur_prenom || '';
            document.getElementById('tuteur_telephone').value = adherent.tuteur_telephone || '';
            document.getElementById('tuteur_email').value = adherent.tuteur_email || '';
            
            // Vérifier si l'adhérent est mineur et afficher les champs tuteur si nécessaire
            const dateInput = document.getElementById('date_naissance');
            const guardianFieldsDiv = document.getElementById('guardianFields');
            if (dateInput && guardianFieldsDiv) {
                toggleGuardianFields(dateInput, guardianFieldsDiv);
            }
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
        
        document.addEventListener('DOMContentLoaded', function() {
            loadCategories();
            loadProfile();
            
            // Variables globales
            let currentAdherent = null;
            
            // Éléments DOM
            const editProfileForm = document.getElementById('editProfileForm');
            const editProfileModal = new bootstrap.Modal(document.getElementById('editProfileModal'));
            const editProfileBtn = document.getElementById('editProfileBtn');
            
            // Gestion de l'affichage des champs tuteur selon l'âge
            const dateNaissanceInput = document.getElementById('date_naissance');
            const guardianFields = document.getElementById('guardianFields');
            
            // Événements pour le formulaire de modification
            if (dateNaissanceInput && guardianFields) {
                dateNaissanceInput.addEventListener('change', function() {
                    toggleGuardianFields(this, guardianFields);
                });
            }
            
            // Événement pour ouvrir le modal de modification
            editProfileBtn.addEventListener('click', function() {
                if (currentAdherent) {
                    // Charger les catégories avant d'ouvrir le modal
                    loadCategories();
                    // Attendre un peu que les catégories se chargent
                    setTimeout(() => {
                        openEditModal(currentAdherent);
                    }, 100);
                }
            });
            
            // Soumission du formulaire de modification
            editProfileForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('profil.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        editProfileModal.hide();
                        loadProfile();
                        showAlert('success', data.message);
                    } else {
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showAlert('danger', 'Une erreur est survenue lors de la mise à jour du profil');
                });
            });
            
            // Stocker les données de l'adhérent pour le modal
            window.currentAdherent = null;
            
            // Modifier la fonction displayProfile pour stocker les données
            const originalDisplayProfile = displayProfile;
            displayProfile = function(adherent) {
                currentAdherent = adherent;
                window.currentAdherent = adherent;
                originalDisplayProfile(adherent);
            };
        });
    </script>

<?php include 'includes/footer.php'; ?>