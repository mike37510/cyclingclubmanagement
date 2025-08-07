<?php
session_start();

// Vérification de l'authentification (admin ou user)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupération du rôle de l'utilisateur
$user_role = $_SESSION['role'] ?? '';
$is_admin = ($user_role === 'admin');

// Configuration de la page
$page_title = $is_admin ? 'Asso Vélo - Gestion des Documents' : 'Asso Vélo - Documents';

// Traitement des actions (uniquement pour les admins)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_folder':
                $folder_name = trim($_POST['folder_name']);
                $parent_path = $_POST['parent_path'] ?? '';
                
                if (!empty($folder_name) && preg_match('/^[a-zA-Z0-9_\-\s]+$/', $folder_name)) {
                    $full_path = '../private_documents/' . $parent_path . '/' . $folder_name;
                    $full_path = rtrim($full_path, '/');
                    
                    if (!file_exists($full_path)) {
                        if (mkdir($full_path, 0755, true)) {
                            $success_message = "Dossier créé avec succès.";
                        } else {
                            $error_message = "Erreur lors de la création du dossier.";
                        }
                    } else {
                        $error_message = "Ce dossier existe déjà.";
                    }
                } else {
                    $error_message = "Nom de dossier invalide. Utilisez uniquement des lettres, chiffres, tirets et espaces.";
                }
                break;
                
            case 'upload_file':
                $target_path = $_POST['target_path'] ?? '';
                
                if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['document']['name'];
                    $file_tmp = $_FILES['document']['tmp_name'];
                    $file_size = $_FILES['document']['size'];
                    
                    // Vérifier la taille du fichier (max 10MB)
                    if ($file_size > 10 * 1024 * 1024) {
                        $error_message = "Le fichier est trop volumineux (max 10MB).";
                    } else {
                        // Nettoyer le nom du fichier
                        $file_name = preg_replace('/[^a-zA-Z0-9_\-\.\s]/', '', $file_name);
                        $upload_path = '../private_documents/' . $target_path . '/' . $file_name;
                        $upload_path = str_replace('//', '/', $upload_path);
                        
                        if (move_uploaded_file($file_tmp, $upload_path)) {
                            $success_message = "Fichier uploadé avec succès.";
                        } else {
                            $error_message = "Erreur lors de l'upload du fichier.";
                        }
                    }
                } else {
                    $error_message = "Aucun fichier sélectionné ou erreur d'upload.";
                }
                break;
                
            case 'delete_item':
                $item_path = $_POST['item_path'];
                $full_path = '../private_documents/' . $item_path;
                
                if (file_exists($full_path)) {
                    if (is_dir($full_path)) {
                        if (rmdir($full_path)) {
                            $success_message = "Dossier supprimé avec succès.";
                        } else {
                            $error_message = "Erreur lors de la suppression du dossier (vérifiez qu'il soit vide).";
                        }
                    } else {
                        if (unlink($full_path)) {
                            $success_message = "Fichier supprimé avec succès.";
                        } else {
                            $error_message = "Erreur lors de la suppression du fichier.";
                        }
                    }
                } else {
                    $error_message = "L'élément à supprimer n'existe pas.";
                }
                break;
        }
    }
}

// Fonction pour lister les fichiers et dossiers
function listDirectoryContents($path) {
    $items = [];
    $full_path = '../private_documents/' . $path;
    
    if (is_dir($full_path)) {
        $files = scandir($full_path);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $file_path = $full_path . '/' . $file;
                $relative_path = $path . '/' . $file;
                $relative_path = ltrim($relative_path, '/');
                
                $items[] = [
                    'name' => $file,
                    'path' => $relative_path,
                    'is_dir' => is_dir($file_path),
                    'size' => is_file($file_path) ? filesize($file_path) : 0,
                    'modified' => filemtime($file_path)
                ];
            }
        }
    }
    
    // Trier : dossiers d'abord, puis fichiers
    usort($items, function($a, $b) {
        if ($a['is_dir'] && !$b['is_dir']) return -1;
        if (!$a['is_dir'] && $b['is_dir']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $items;
}

// Récupérer le chemin actuel
$current_path = $_GET['path'] ?? '';
$current_path = trim($current_path, '/');

// Créer le dossier documents s'il n'existe pas
if (!file_exists('../private_documents')) {
    mkdir('../private_documents', 0755, true);
}

// Récupérer le contenu du dossier actuel
$directory_contents = listDirectoryContents($current_path);

// Fonction pour formater la taille des fichiers
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Fonction pour créer le fil d'Ariane
function createBreadcrumb($path) {
    $breadcrumb = '<a href="documents.php">Documents</a>';
    
    if (!empty($path)) {
        $parts = explode('/', $path);
        $current_path = '';
        
        foreach ($parts as $part) {
            $current_path .= ($current_path ? '/' : '') . $part;
            $breadcrumb .= ' / <a href="documents.php?path=' . urlencode($current_path) . '">' . htmlspecialchars($part) . '</a>';
        }
    }
    
    return $breadcrumb;
}
?>

<?php include 'includes/header.php'; ?>

    <div class="documents-container">
        <h1><?php echo $is_admin ? 'Gestion des Documents' : 'Documents'; ?></h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <div class="breadcrumb">
            <?php echo createBreadcrumb($current_path); ?>
        </div>
        
        <?php if ($is_admin): ?>
        <div class="actions-bar">
            <button class="btn btn-primary" onclick="openCreateFolderModal()">📁 Créer un dossier</button>
            <button class="btn btn-success" onclick="openUploadModal()">📤 Uploader un fichier</button>
            <?php if (!empty($current_path)): ?>
                <a href="documents.php?path=<?php echo urlencode(dirname($current_path) === '.' ? '' : dirname($current_path)); ?>" class="btn btn-secondary">⬆️ Dossier parent</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="actions-bar">
            <?php if (!empty($current_path)): ?>
                <a href="documents.php?path=<?php echo urlencode(dirname($current_path) === '.' ? '' : dirname($current_path)); ?>" class="btn btn-secondary">⬆️ Dossier parent</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="file-list">
            <?php if (empty($directory_contents)): ?>
                <div class="empty-state">
                    <p>Ce dossier est vide.</p>
                    <?php if ($is_admin): ?>
                        <p>Commencez par créer un dossier ou uploader un fichier.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($directory_contents as $item): ?>
                    <div class="file-item">
                        <div class="file-icon">
                            <?php if ($item['is_dir']): ?>
                                📁
                            <?php else: ?>
                                📄
                            <?php endif; ?>
                        </div>
                        <div class="file-info">
                            <div class="file-name">
                                <?php if ($item['is_dir']): ?>
                                    <a href="documents.php?path=<?php echo urlencode($item['path']); ?>" class="no-decoration">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($item['name']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="file-meta">
                                <?php if (!$item['is_dir']): ?>
                                    <?php echo formatFileSize($item['size']); ?> • 
                                <?php endif; ?>
                                Modifié le <?php echo date('d/m/Y H:i', $item['modified']); ?>
                            </div>
                        </div>
                        <div class="file-actions">
                            <?php if (!$item['is_dir']): ?>
                                <a href="download.php?file=<?php echo urlencode($item['path']); ?>" class="btn btn-primary file-action-btn">📥 Télécharger</a>
                            <?php endif; ?>
                            <?php if ($is_admin): ?>
                                <button class="btn btn-danger file-action-btn" onclick="confirmDelete('<?php echo htmlspecialchars($item['path']); ?>', '<?php echo htmlspecialchars($item['name']); ?>')">🗑️ Supprimer</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($is_admin): ?>
    <!-- Modal Créer Dossier -->
    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('createFolderModal')">&times;</span>
            <h2>Créer un nouveau dossier</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_folder">
                <input type="hidden" name="parent_path" value="<?php echo htmlspecialchars($current_path); ?>">
                <div class="form-group">
                    <label for="folder_name">Nom du dossier :</label>
                    <input type="text" id="folder_name" name="folder_name" required pattern="[a-zA-Z0-9_\-\s]+" title="Utilisez uniquement des lettres, chiffres, tirets et espaces">
                </div>
                <button type="submit" class="btn btn-primary">Créer</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('createFolderModal')">Annuler</button>
            </form>
        </div>
    </div>
    
    <!-- Modal Upload Fichier -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('uploadModal')">&times;</span>
            <h2>Uploader un fichier</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_file">
                <input type="hidden" name="target_path" value="<?php echo htmlspecialchars($current_path); ?>">
                <div class="form-group">
                    <label for="document">Sélectionner un fichier :</label>
                    <input type="file" id="document" name="document" required>
                    <small>Taille maximum : 10MB</small>
                </div>
                <button type="submit" class="btn btn-success">Uploader</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Annuler</button>
            </form>
        </div>
    </div>
    
    <!-- Modal Confirmation Suppression -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            <h2>Confirmer la suppression</h2>
            <p>Êtes-vous sûr de vouloir supprimer <strong id="deleteItemName"></strong> ?</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete_item">
                <input type="hidden" name="item_path" id="deleteItemPath">
                <button type="submit" class="btn btn-danger">Supprimer</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Annuler</button>
            </form>
        </div>
    </div>
    
    <script>
        function openCreateFolderModal() {
            document.getElementById('createFolderModal').style.display = 'block';
        }
        
        function openUploadModal() {
            document.getElementById('uploadModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function confirmDelete(itemPath, itemName) {
            document.getElementById('deleteItemPath').value = itemPath;
            document.getElementById('deleteItemName').textContent = itemName;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>