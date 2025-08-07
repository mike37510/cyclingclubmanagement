# 🚴‍♂️ AssoVelo - Plateforme de Gestion d'Associations Cyclistes

![Version](https://img.shields.io/badge/version-2.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4.svg)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1.svg)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3.2-7952B3.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

## 📋 Description

**AssoVelo** est une plateforme web moderne et complète conçue spécialement pour la gestion des associations cyclistes. Elle centralise tous les aspects administratifs et organisationnels de votre association en un seul endroit accessible et facile à utiliser.

Notre solution vous permet de gérer efficacement vos adhérents, organiser vos événements et courses, et maintenir une bibliothèque de documents partagés, le tout dans une interface intuitive et responsive.

## ✨ Fonctionnalités Principales

### 👥 Gestion des Adhérents
- **Profils complets** : Informations personnelles, photos, contacts
- **Gestion des mineurs** : Support pour jusqu'à 2 tuteurs légaux
- **Catégorisation** : Système de catégories personnalisables avec couleurs
- **Exports** : PDF individuels, trombinoscope, listes CSV
- **Droit à l'image** : Gestion des autorisations

### 📅 Organisation d'Événements
- **Planification complète** : Date, heure, lieu, point de rendez-vous
- **Inscriptions** : Système de participation avec statuts (en attente, confirmé, indisponible)
- **Gestion des tâches** : Attribution de responsabilités avec système de couleurs
- **Exports** : Calendriers PDF, listes de participants
- **Suivi** : Historique des événements et participations

### 📋 Gestion des Tâches
- **Organisation par événement** : Attribution de tâches spécifiques
- **Système de couleurs** : Identification visuelle des types de tâches
- **Affectation flexible** : Attribution multiple d'adhérents par tâche
- **Suivi** : Statut actif/inactif des tâches

### 🏷️ Système de Catégories
- **Catégorisation flexible** : Adhérents classés par catégories
- **Personnalisation** : Couleurs et descriptions personnalisables
- **Gestion hiérarchique** : Organisation structurée

### 📄 Bibliothèque de Documents
- **Stockage centralisé** : Documents et ressources de l'association
- **Gestion des permissions** : Contrôle d'accès par utilisateur
- **Organisation hiérarchique** : Dossiers et sous-dossiers
- **Types multiples** : Support de tous formats de fichiers

### ✉️ Communication
- **Système de mailing** : Communication avec les adhérents
- **Notifications** : Alertes et rappels automatiques
- **Gestion des contacts** : Base de données centralisée

### 📊 Exports & Rapports
- **PDF** : Fiches adhérents, calendriers, trombinoscopes
- **CSV** : Listes d'adhérents pour traitement externe
- **Calendriers** : Planning des événements
- **Statistiques** : Rapports de participation

## 🛠️ Technologies Utilisées

### Backend
- **PHP 8.0+** : Langage de programmation principal
- **MySQL 8.0+** : Base de données relationnelle
- **MySQLi** : Extension PHP pour MySQL
- **Sessions PHP** : Gestion de l'authentification

### Frontend
- **HTML5** : Structure des pages
- **CSS3** : Styles personnalisés
- **Bootstrap 5.3.2** : Framework CSS responsive
- **Bootstrap Icons** : Icônes vectorielles
- **FontAwesome** : Icônes complémentaires
- **JavaScript** : Interactions côté client

### Sécurité
- **Authentification par sessions** : Système de connexion sécurisé
- **Contrôle d'accès** : Rôles utilisateur (admin, user, adherent)
- **Protection CSRF** : Sécurisation des formulaires
- **Validation des données** : Filtrage et échappement
- **Gestion des permissions** : Accès contrôlé aux documents

## 🏗️ Architecture

```
AssoVelo/
├── 📁 css/                    # Styles CSS
│   └── style.css
├── 📁 img/                    # Images statiques
│   ├── default_logo.svg
│   ├── default_user.svg
│   └── login.png
├── 📁 includes/               # Fichiers PHP communs
│   ├── db_connect.php         # Connexion base de données
│   ├── functions.php          # Fonctions utilitaires
│   ├── header.php             # En-tête commun
│   ├── footer.php             # Pied de page
│   └── head.php               # Balises HTML head
├── 📁 sql/                    # Scripts de base de données
│   ├── assovelo2.sql          # Structure principale
│   └── organisation_taches.sql # Tables des tâches
├── 📁 uploads/                # Fichiers uploadés
│   ├── adherents/             # Photos des adhérents
│   ├── documents/             # Documents partagés
│   └── logo/                  # Logos de l'association
├── 📄 index.php               # Page d'accueil
├── 📄 login.php               # Authentification
├── 📄 admin.php               # Administration
├── 📄 adherent.php            # Gestion adhérents
├── 📄 evenement.php           # Gestion événements
├── 📄 organiser.php           # Organisation tâches
├── 📄 documents.php           # Bibliothèque documents
├── 📄 mailing.php             # Système de mailing
├── 📄 test_simulator.php      # Simulateur de tests
└── 📄 README.md               # Documentation
```

## 🗄️ Base de Données

### Tables Principales
- **adherents** : Informations des membres
- **evenements** : Événements et sorties
- **taches** : Tâches organisationnelles
- **categories** : Catégories d'adhérents
- **documents** : Bibliothèque de fichiers
- **utilisateurs** : Comptes d'accès
- **participation** : Inscriptions aux événements
- **evenement_taches_adherents** : Affectations de tâches

### Relations
- Relations Many-to-Many entre adhérents et événements
- Système de catégorisation flexible
- Gestion hiérarchique des documents
- Liens utilisateurs-adhérents pour l'authentification

## 🚀 Installation

### Prérequis
- **Serveur web** : Apache/Nginx avec PHP 8.0+
- **Base de données** : MySQL 8.0+ ou MariaDB 10.4+
- **Extensions PHP** : mysqli, gd, fileinfo, session

### Étapes d'installation

1. **Cloner le repository**
   ```bash
   git clone https://github.com/votre-username/assovelo.git
   cd assovelo
   ```

2. **Configuration de la base de données**
   ```bash
   # Créer la base de données
   mysql -u root -p -e "CREATE DATABASE assovelo3 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   
   # Importer la structure
   mysql -u root -p assovelo3 < sql/assovelo2.sql
   mysql -u root -p assovelo3 < sql/organisation_taches.sql
   ```

3. **Configuration de la connexion**
   ```php
   // Modifier includes/db_connect.php
   $host = 'localhost';          // Votre serveur MySQL
   $db_user = 'votre_user';      // Utilisateur MySQL
   $db_password = 'votre_pass';  // Mot de passe MySQL
   $db_name = 'assovelo3';       // Nom de la base
   ```

4. **Permissions des dossiers**
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/adherents/
   chmod 755 uploads/documents/
   chmod 755 uploads/logo/
   ```

5. **Créer un compte administrateur**
   ```sql
   INSERT INTO utilisateurs (username, mot_de_passe, email, role) 
   VALUES ('admin', PASSWORD('votre_mot_de_passe'), 'admin@assovelo.fr', 'admin');
   ```

## 🔧 Configuration

### Paramètres système
- **Logo** : Uploadable via l'interface d'administration
- **Utilisateurs** : Gestion des comptes et rôles
- **Permissions** : Contrôle d'accès aux fonctionnalités

### Personnalisation
- **CSS** : Styles modifiables dans `css/style.css`
- **Couleurs** : Thème Bootstrap personnalisable
- **Images** : Logos et images par défaut remplaçables

## 🧪 Tests

L'application inclut un **simulateur de tests complet** (`test_simulator.php`) permettant :

- **Tests CRUD** : Création, lecture, mise à jour, suppression
- **Données fictives** : Génération automatique de jeux de test
- **Simulations avancées** : Inscriptions et affectations aléatoires
- **Nettoyage** : Suppression des données de test

⚠️ **Attention** : Utilisez le simulateur uniquement en environnement de développement.

## 👥 Rôles Utilisateur

### Administrateur (`admin`)
- Accès complet à toutes les fonctionnalités
- Gestion des utilisateurs et paramètres système
- Administration des adhérents, événements et documents
- Accès aux outils de test et maintenance

### Utilisateur (`user`)
- Consultation des informations
- Participation aux événements
- Accès limité aux documents

### Adhérent (`adherent`)
- Profil personnel
- Inscription aux événements
- Consultation des documents autorisés

## 📱 Responsive Design

L'interface est entièrement responsive et s'adapte à tous les appareils :
- **Desktop** : Interface complète avec toutes les fonctionnalités
- **Tablette** : Navigation optimisée et formulaires adaptés
- **Mobile** : Interface tactile et menus collapsibles

## 🔒 Sécurité

- **Authentification** : Sessions sécurisées avec timeout
- **Autorisation** : Contrôle d'accès basé sur les rôles
- **Validation** : Filtrage et échappement des données
- **Upload** : Vérification des types de fichiers
- **SQL** : Requêtes préparées contre l'injection

## 🤝 Contribution

Les contributions sont les bienvenues ! Pour contribuer :

1. Fork le projet
2. Créez une branche feature (`git checkout -b feature/AmazingFeature`)
3. Committez vos changements (`git commit -m 'Add some AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrez une Pull Request

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 📞 Support

Pour toute question ou problème :
- **Issues** : Utilisez le système d'issues GitHub
- **Documentation** : Consultez ce README et les commentaires du code
- **Contact** : Contactez l'équipe de développement

## 🎯 Roadmap

### Version 2.1 (Prochaine)
- [ ] API REST pour intégrations externes
- [ ] Notifications push en temps réel
- [ ] Module de statistiques avancées
- [ ] Export iCal pour les calendriers

### Version 2.2 (Future)
- [ ] Application mobile companion
- [ ] Intégration réseaux sociaux
- [ ] Système de paiement en ligne
- [ ] Module de réservation de matériel

---

**AssoVelo** - *La solution complète pour votre association cycliste* 🚴‍♂️

Développé avec ❤️ pour la communauté cycliste
