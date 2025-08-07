-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : 192.168.1.254:3306
-- Généré le : jeu. 07 août 2025 à 21:46
-- Version du serveur : 11.8.2-MariaDB-ubu2404
-- Version de PHP : 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `assovelo2`
--

-- --------------------------------------------------------

--
-- Structure de la table `adherents`
--

CREATE TABLE `adherents` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `date_naissance` date NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `tuteur_nom` varchar(100) DEFAULT NULL,
  `tuteur_prenom` varchar(100) DEFAULT NULL,
  `tuteur_telephone` varchar(20) DEFAULT NULL,
  `tuteur_email` varchar(100) DEFAULT NULL,
  `tuteur2_nom` varchar(100) DEFAULT NULL,
  `tuteur2_prenom` varchar(100) DEFAULT NULL,
  `tuteur2_telephone` varchar(20) DEFAULT NULL,
  `tuteur2_email` varchar(100) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `droit_image` enum('oui','non') NOT NULL DEFAULT 'non',
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `date_modification` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci COMMENT='Table des adhérents avec informations des tuteurs légaux pour les mineurs (jusqu''à 2 tuteurs)';

-- --------------------------------------------------------

--
-- Structure de la table `adherent_categories`
--

CREATE TABLE `adherent_categories` (
  `id` int(11) NOT NULL,
  `adherent_id` int(11) NOT NULL,
  `categorie_id` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `couleur` varchar(7) DEFAULT '#007bff',
  `actif` tinyint(1) DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `date_modification` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `chemin` varchar(500) NOT NULL,
  `type` enum('fichier','dossier') NOT NULL DEFAULT 'fichier',
  `taille` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `date_modification` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `description` text DEFAULT NULL,
  `visible_adherents` tinyint(1) DEFAULT 1,
  `visible_admins` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `evenements`
--

CREATE TABLE `evenements` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `heure` time NOT NULL,
  `lieu` text NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `date_modification` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `point_rdv` text DEFAULT NULL,
  `informations` text DEFAULT NULL,
  `nombre_participants_souhaite` int(11) DEFAULT NULL COMMENT 'Nombre de participants souhaité pour cet événement'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `evenement_taches_adherents`
--

CREATE TABLE `evenement_taches_adherents` (
  `id` int(11) NOT NULL,
  `evenement_id` int(11) NOT NULL,
  `adherent_id` int(11) NOT NULL,
  `tache_id` int(11) NOT NULL,
  `date_assignation` timestamp NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `participation`
--

CREATE TABLE `participation` (
  `id` int(11) NOT NULL,
  `evenement_id` int(11) NOT NULL,
  `adherent_id` int(11) NOT NULL,
  `statut` enum('en attente','confirmé','pas disponible') NOT NULL DEFAULT 'en attente',
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `date_modification` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `permissions_documents`
--

CREATE TABLE `permissions_documents` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `adherent_id` int(11) DEFAULT NULL,
  `permission` enum('lecture','ecriture','admin') NOT NULL DEFAULT 'lecture',
  `date_creation` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `taches`
--

CREATE TABLE `taches` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `couleur` varchar(7) DEFAULT '#007bff',
  `actif` tinyint(1) DEFAULT 1,
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `date_modification` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','user','adherent') NOT NULL DEFAULT 'user',
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs_adherents`
--

CREATE TABLE `utilisateurs_adherents` (
  `id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `adherent_id` int(11) NOT NULL,
  `principal` tinyint(1) DEFAULT 0,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `adherents`
--
ALTER TABLE `adherents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nom_prenom` (`nom`,`prenom`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_tuteur_email` (`tuteur_email`),
  ADD KEY `idx_tuteur2_email` (`tuteur2_email`);

--
-- Index pour la table `adherent_categories`
--
ALTER TABLE `adherent_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_adherent_categorie` (`adherent_id`,`categorie_id`),
  ADD KEY `idx_adherent` (`adherent_id`),
  ADD KEY `idx_categorie` (`categorie_id`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom` (`nom`);

--
-- Index pour la table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parent` (`parent_id`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_visible_adherents` (`visible_adherents`);

--
-- Index pour la table `evenements`
--
ALTER TABLE `evenements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`date`);

--
-- Index pour la table `evenement_taches_adherents`
--
ALTER TABLE `evenement_taches_adherents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignation` (`evenement_id`,`adherent_id`,`tache_id`),
  ADD KEY `adherent_id` (`adherent_id`),
  ADD KEY `tache_id` (`tache_id`);

--
-- Index pour la table `participation`
--
ALTER TABLE `participation`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_participation` (`evenement_id`,`adherent_id`),
  ADD KEY `adherent_id` (`adherent_id`);

--
-- Index pour la table `permissions_documents`
--
ALTER TABLE `permissions_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document` (`document_id`),
  ADD KEY `idx_adherent` (`adherent_id`);

--
-- Index pour la table `taches`
--
ALTER TABLE `taches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_nom` (`nom`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Index pour la table `utilisateurs_adherents`
--
ALTER TABLE `utilisateurs_adherents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_liaison` (`utilisateur_id`,`adherent_id`),
  ADD KEY `idx_utilisateur` (`utilisateur_id`),
  ADD KEY `idx_adherent` (`adherent_id`),
  ADD KEY `idx_principal` (`principal`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `adherents`
--
ALTER TABLE `adherents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `adherent_categories`
--
ALTER TABLE `adherent_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `evenements`
--
ALTER TABLE `evenements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `evenement_taches_adherents`
--
ALTER TABLE `evenement_taches_adherents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `participation`
--
ALTER TABLE `participation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `permissions_documents`
--
ALTER TABLE `permissions_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `taches`
--
ALTER TABLE `taches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateurs_adherents`
--
ALTER TABLE `utilisateurs_adherents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `adherent_categories`
--
ALTER TABLE `adherent_categories`
  ADD CONSTRAINT `fk_ac_adherent` FOREIGN KEY (`adherent_id`) REFERENCES `adherents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ac_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `evenement_taches_adherents`
--
ALTER TABLE `evenement_taches_adherents`
  ADD CONSTRAINT `evenement_taches_adherents_ibfk_1` FOREIGN KEY (`evenement_id`) REFERENCES `evenements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evenement_taches_adherents_ibfk_2` FOREIGN KEY (`adherent_id`) REFERENCES `adherents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evenement_taches_adherents_ibfk_3` FOREIGN KEY (`tache_id`) REFERENCES `taches` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `participation`
--
ALTER TABLE `participation`
  ADD CONSTRAINT `participation_ibfk_1` FOREIGN KEY (`evenement_id`) REFERENCES `evenements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `participation_ibfk_2` FOREIGN KEY (`adherent_id`) REFERENCES `adherents` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `permissions_documents`
--
ALTER TABLE `permissions_documents`
  ADD CONSTRAINT `permissions_documents_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `permissions_documents_ibfk_2` FOREIGN KEY (`adherent_id`) REFERENCES `adherents` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `utilisateurs_adherents`
--
ALTER TABLE `utilisateurs_adherents`
  ADD CONSTRAINT `fk_ua_adherent` FOREIGN KEY (`adherent_id`) REFERENCES `adherents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ua_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
