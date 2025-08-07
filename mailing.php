<?php
session_start();

// Inclusion des fonctions communes et de la connexion à la base de données
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Vérification de l'authentification et des droits d'accès
check_authentication('admin');

// Configuration de la page
$page_title = 'Asso Vélo - Gestion des Mailings';

// Traitement des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    // Récupération des catégories
    if ($_POST['action'] === 'get_categories') {
        $categories = get_active_categories($conn);
        $response['success'] = true;
        $response['categories'] = $categories;
    }
    
    // Recherche d'adhérents avec emails (AJAX)
    elseif ($_POST['action'] === 'search_emails') {
        $page = intval($_POST['page'] ?? 1);
        $search = trim($_POST['search'] ?? '');
        $categories_filter = json_decode($_POST['categories_filter'] ?? '[]', true);
        $limit = 20; // Nombre d'adhérents par page
        $offset = ($page - 1) * $limit;
        
        $where_conditions = [];
        $params = [];
        $types = "";
        
        // Condition de recherche par nom/prénom/email
        if (!empty($search)) {
            $where_conditions[] = "(a.nom LIKE ? OR a.prenom LIKE ? OR a.email LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= "sss";
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
        
        // Condition pour exclure les emails vides
        $where_conditions[] = "a.email IS NOT NULL AND a.email != ''";
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        // Requête pour compter le nombre total d'adhérents avec email
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
        
        // Requête pour récupérer les adhérents avec leurs emails et catégories
        $sql = "SELECT a.id, a.nom, a.prenom, a.email, a.date_naissance,
                COALESCE(GROUP_CONCAT(c.nom SEPARATOR ', '), '') as categories_noms,
                COALESCE(GROUP_CONCAT(c.couleur SEPARATOR ','), '') as categories_couleurs
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
            // Calcul de l'âge
            $row['age'] = calculate_age($row['date_naissance']);
            $row['est_mineur'] = is_minor($row['date_naissance']);
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
            <h1>Gestion des Mailings</h1>
            <div>
                <button type="button" class="btn btn-success me-2" id="copyAllEmails">
                    <i class="bi bi-clipboard"></i> Copier tous les emails
                </button>
                <button type="button" class="btn btn-primary" id="exportEmails">
                    <i class="bi bi-download"></i> Exporter la liste
                </button>
            </div>
        </div>
        
        <!-- Barre de recherche -->
        <div class="search-container mb-4">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="searchInput" class="form-control" placeholder="Rechercher par nom, prénom ou email...">
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
        
        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total des emails</h5>
                        <h3 id="totalEmails">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Emails sélectionnés</h5>
                        <h3 id="selectedEmails">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Filtres actifs</h5>
                        <h3 id="activeFilters">0</h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Zone de copie des emails -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Emails sélectionnés</h5>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllEmails">
                        <i class="bi bi-check-all"></i> Tout sélectionner
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllEmails">
                        <i class="bi bi-x-square"></i> Tout désélectionner
                    </button>
                </div>
            </div>
            <div class="card-body">
                <textarea id="emailsList" class="form-control" rows="6" placeholder="Les emails sélectionnés apparaîtront ici, séparés par des virgules..." readonly></textarea>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-success" id="copyEmailsList">
                        <i class="bi bi-clipboard"></i> Copier la liste
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Indicateur de chargement -->
        <div id="loading" class="text-center mb-4 hidden">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
        </div>
        
        <!-- Liste des adhérents avec emails -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Liste des adhérents avec email</h5>
            </div>
            <div class="card-body">
                <div id="adherentsList">
                    <!-- Les adhérents seront chargés ici via AJAX -->
                </div>
            </div>
        </div>
        
        <!-- Pagination -->
        <nav aria-label="Pagination des adhérents" class="mt-4">
            <ul id="pagination" class="pagination justify-content-center">
                <!-- La pagination sera générée via JavaScript -->
            </ul>
        </nav>
    </div>

    <script>
    // Variables globales
    let currentPage = 1;
    let currentSearch = '';
    let currentFilters = [];
    let selectedEmails = new Set();
    
    // Fonction pour charger les catégories
    function loadCategories() {
        fetch('mailing.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_categories'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const containerFilter = document.getElementById('categoryCheckboxes');
                containerFilter.innerHTML = '';
                
                // Ajouter les catégories sous forme de checkboxes
                data.categories.forEach(category => {
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
                });
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des catégories:', error);
        });
    }
    
    // Fonction pour charger les adhérents avec emails
    function loadAdherents() {
        const loading = document.getElementById('loading');
        const adherentsList = document.getElementById('adherentsList');
        
        loading.style.display = 'block';
        adherentsList.innerHTML = '';
        
        const formData = new FormData();
        formData.append('action', 'search_emails');
        formData.append('search', currentSearch);
        formData.append('page', currentPage);
        formData.append('categories_filter', JSON.stringify(currentFilters));
        
        fetch('mailing.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            loading.style.display = 'none';
            
            if (data.success) {
                displayAdherents(data.adherents);
                displayPagination(data.pagination);
                updateStats();
            } else {
                adherentsList.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des données</div>';
            }
        })
        .catch(error => {
            loading.style.display = 'none';
            console.error('Erreur:', error);
            adherentsList.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des données</div>';
        });
    }
    
    // Fonction pour afficher les adhérents
    function displayAdherents(adherents) {
        const adherentsList = document.getElementById('adherentsList');
        
        if (adherents.length === 0) {
            adherentsList.innerHTML = '<div class="alert alert-info">Aucun adhérent trouvé avec les critères sélectionnés.</div>';
            return;
        }
        
        let html = '<div class="table-responsive"><table class="table table-striped"><thead><tr>';
        html += '<th><input type="checkbox" id="selectAllCheckbox"> Sélectionner</th>';
        html += '<th>Nom</th><th>Prénom</th><th>Email</th><th>Âge</th><th>Catégories</th></tr></thead><tbody>';
        
        adherents.forEach(adherent => {
            const categorieHtml = adherent.categories_noms ? 
                adherent.categories_noms.split(', ').map((nom, index) => {
                    const couleur = adherent.categories_couleurs.split(',')[index];
                    return `<span class="badge me-1" style="background-color: ${couleur}">${nom}</span>`;
                }).join('') : 
                '<span class="text-muted">Aucune</span>';
            
            const isChecked = selectedEmails.has(adherent.email) ? 'checked' : '';
            
            html += `
                <tr>
                    <td><input type="checkbox" class="email-checkbox" value="${adherent.email}" data-name="${adherent.prenom} ${adherent.nom}" ${isChecked}></td>
                    <td>${adherent.nom}</td>
                    <td>${adherent.prenom}</td>
                    <td><a href="mailto:${adherent.email}">${adherent.email}</a></td>
                    <td>${adherent.age} ans ${adherent.est_mineur ? '(Mineur)' : '(Majeur)'}</td>
                    <td>${categorieHtml}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div>';
        adherentsList.innerHTML = html;
        
        // Ajouter les événements pour les checkboxes
        document.querySelectorAll('.email-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    selectedEmails.add(this.value);
                } else {
                    selectedEmails.delete(this.value);
                }
                updateEmailsList();
                updateStats();
            });
        });
        
        // Événement pour "Sélectionner tout"
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const emailCheckboxes = document.querySelectorAll('.email-checkbox');
                emailCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    if (this.checked) {
                        selectedEmails.add(checkbox.value);
                    } else {
                        selectedEmails.delete(checkbox.value);
                    }
                });
                updateEmailsList();
                updateStats();
            });
        }
    }
    
    // Fonction pour afficher la pagination
    function displayPagination(pagination) {
        const paginationElement = document.getElementById('pagination');
        let html = '';
        
        if (pagination.total > 1) {
            // Bouton précédent
            if (pagination.current > 1) {
                html += `<li class="page-item"><a class="page-link" href="#" data-page="${pagination.current - 1}">Précédent</a></li>`;
            }
            
            // Numéros de page
            for (let i = 1; i <= pagination.total; i++) {
                const activeClass = i === pagination.current ? 'active' : '';
                html += `<li class="page-item ${activeClass}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
            }
            
            // Bouton suivant
            if (pagination.current < pagination.total) {
                html += `<li class="page-item"><a class="page-link" href="#" data-page="${pagination.current + 1}">Suivant</a></li>`;
            }
        }
        
        paginationElement.innerHTML = html;
        
        // Ajouter les événements de pagination
        paginationElement.querySelectorAll('a[data-page]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                currentPage = parseInt(this.dataset.page);
                loadAdherents();
            });
        });
    }
    
    // Fonction pour mettre à jour la liste des emails
    function updateEmailsList() {
        const emailsList = document.getElementById('emailsList');
        emailsList.value = Array.from(selectedEmails).join(', ');
    }
    
    // Fonction pour mettre à jour les statistiques
    function updateStats() {
        document.getElementById('selectedEmails').textContent = selectedEmails.size;
        document.getElementById('activeFilters').textContent = currentFilters.length;
        
        // Compter le total des emails affichés
        const emailCheckboxes = document.querySelectorAll('.email-checkbox');
        document.getElementById('totalEmails').textContent = emailCheckboxes.length;
    }
    
    // Initialisation au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        loadCategories();
        loadAdherents();
        
        // Événements de recherche
        const searchInput = document.getElementById('searchInput');
        const clearSearchBtn = document.getElementById('clearSearch');
        
        searchInput.addEventListener('input', function() {
            currentSearch = this.value;
            currentPage = 1;
            setTimeout(() => loadAdherents(), 300); // Délai pour éviter trop de requêtes
        });
        
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            currentSearch = '';
            currentPage = 1;
            loadAdherents();
        });
        
        // Événements de filtrage
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('filter-checkbox')) {
                const selectedCategories = [];
                const checkboxes = document.querySelectorAll('input[name="filter_categories[]"]:checked');
                checkboxes.forEach(checkbox => {
                    selectedCategories.push(checkbox.value);
                });
                currentFilters = selectedCategories;
                currentPage = 1;
                loadAdherents();
            }
        });
        
        // Bouton effacer les filtres
        document.getElementById('clearFilters').addEventListener('click', function() {
            document.querySelectorAll('input[name="filter_categories[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            currentFilters = [];
            currentPage = 1;
            loadAdherents();
        });
        
        // Boutons de sélection
        document.getElementById('selectAllEmails').addEventListener('click', function() {
            document.querySelectorAll('.email-checkbox').forEach(checkbox => {
                checkbox.checked = true;
                selectedEmails.add(checkbox.value);
            });
            updateEmailsList();
            updateStats();
        });
        
        document.getElementById('deselectAllEmails').addEventListener('click', function() {
            document.querySelectorAll('.email-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            selectedEmails.clear();
            updateEmailsList();
            updateStats();
        });
        
        // Bouton copier la liste
        document.getElementById('copyEmailsList').addEventListener('click', function() {
            const emailsList = document.getElementById('emailsList');
            emailsList.select();
            document.execCommand('copy');
            
            // Feedback visuel
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check"></i> Copié !';
            btn.classList.remove('btn-success');
            btn.classList.add('btn-success');
            
            setTimeout(() => {
                btn.innerHTML = originalText;
            }, 2000);
        });
    });
    </script>

<?php include 'includes/footer.php'; ?>