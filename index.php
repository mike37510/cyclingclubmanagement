<?php
session_start();

// Redirection vers la page de connexion si l'utilisateur n'est pas connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupération des informations de l'utilisateur connecté
$username = $_SESSION['username'] ?? 'Utilisateur';
$role = $_SESSION['role'] ?? '';

// Configuration de la page
$page_title = 'Asso Vélo - Accueil';
?>

<?php include 'includes/header.php'; ?>

    <div class="container mt-5">
        <!-- Section d'accueil -->
        <div class="row mb-5">
            <div class="col-md-12">
                <div class="jumbotron bg-primary text-white p-5 rounded">
                    <h1 class="display-4">Bienvenue sur Asso Vélo</h1>
                    <p class="lead">La plateforme de gestion complète pour votre association cycliste</p>
                    <hr class="my-4 bg-white">
                    <p>Connecté en tant que <strong><?php echo htmlspecialchars($username); ?></strong> (<?php echo ucfirst($role); ?>)</p>
                </div>
            </div>
        </div>

        <!-- Section présentation -->
        <div class="row mb-5">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Qu'est-ce qu'Asso Vélo ?</h2>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="text-justify">
                                    <strong>Asso Vélo</strong> est une plateforme web moderne conçue spécialement pour la gestion 
                                    des associations cyclistes. Elle centralise tous les aspects administratifs et organisationnels 
                                    de votre association en un seul endroit accessible et facile à utiliser.
                                </p>
                                <p class="text-justify">
                                    Notre solution vous permet de gérer efficacement vos adhérents, organiser vos événements 
                                    et courses, et maintenir une bibliothèque de documents partagés, le tout dans une interface 
                                    intuitive et responsive.
                                </p>
                            </div>
                            <div class="col-md-6">
                                <div class="bg-light p-4 rounded">
                                    <h5 class="text-primary">🚴‍♂️ Pourquoi Asso Vélo ?</h5>
                                    <ul class="list-unstyled">
                                        <li>✅ <strong>Simplicité :</strong> Interface intuitive et moderne</li>
                                        <li>✅ <strong>Efficacité :</strong> Gestion centralisée de tous vos besoins</li>
                                        <li>✅ <strong>Flexibilité :</strong> Adaptée aux associations de toutes tailles</li>
                                        <li>✅ <strong>Sécurité :</strong> Données protégées et accès contrôlé</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

       

        <!-- Section fonctionnalités principales -->
        <div class="row mb-5">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">🛠️ Fonctionnalités Principales</h2>
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="card h-100 border-primary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                        <h5 class="card-title">Gestion des Adhérents</h5>
                                        <p class="card-text">Ajout, modification et suivi complet des membres de votre association avec photos, informations personnelles et catégories.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100 border-success">
                                    <div class="card-body text-center">
                                        <i class="fas fa-calendar-alt fa-3x text-success mb-3"></i>
                                        <h5 class="card-title">Organisation d'Événements</h5>
                                        <p class="card-text">Planification et gestion des sorties, courses et événements avec inscriptions, tâches et suivi des participants.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100 border-info">
                                    <div class="card-body text-center">
                                        <i class="fas fa-file-alt fa-3x text-info mb-3"></i>
                                        <h5 class="card-title">Bibliothèque de Documents</h5>
                                        <p class="card-text">Stockage et partage centralisé des documents importants, règlements et ressources de l'association.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="card h-100 border-warning">
                                    <div class="card-body text-center">
                                        <i class="fas fa-tasks fa-3x text-warning mb-3"></i>
                                        <h5 class="card-title">Gestion des Tâches</h5>
                                        <p class="card-text">Attribution et suivi des responsabilités pour chaque événement avec système de couleurs et notifications.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100 border-secondary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-chart-bar fa-3x text-secondary mb-3"></i>
                                        <h5 class="card-title">Exports & Rapports</h5>
                                        <p class="card-text">Génération automatique de PDF, CSV et calendriers pour vos adhérents, événements et statistiques.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100 border-dark">
                                    <div class="card-body text-center">
                                        <i class="fas fa-envelope fa-3x text-dark mb-3"></i>
                                        <h5 class="card-title">Communication</h5>
                                        <p class="card-text">Système de mailing intégré pour communiquer efficacement avec vos adhérents et participants.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

       

        <!-- Section contact/aide -->
        <div class="row">
            <div class="col-md-12">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h4 class="card-title text-primary">Besoin d'aide ?</h4>
                        <p class="card-text">
                            Si vous rencontrez des difficultés ou avez des questions sur l'utilisation d'Asso Vélo, 
                            n'hésitez pas à contacter l'équipe d'administration de votre association.
                        </p>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Plateforme Asso Vélo - Version 2.0 - Gestion moderne d'associations cyclistes
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>