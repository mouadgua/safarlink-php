-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : mer. 05 nov. 2025 à 05:32
-- Version du serveur : 10.4.28-MariaDB
-- Version de PHP : 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `safarilink`
--

-- --------------------------------------------------------

--
-- Structure de la table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `passenger_id` int(11) NOT NULL,
  `seats_reserved` int(11) NOT NULL DEFAULT 1,
  `total_price` decimal(6,2) NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `confirmation_code` varchar(20) DEFAULT NULL,
  `passenger_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `detours`
--

CREATE TABLE `detours` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `passenger_id` int(11) NOT NULL,
  `address` text NOT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `estimated_delay` int(11) DEFAULT NULL,
  `status` enum('proposed','accepted','rejected') DEFAULT 'proposed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `driver_applications`
--

CREATE TABLE `driver_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `license_number` varchar(100) NOT NULL,
  `license_date` date NOT NULL,
  `car_brand` varchar(100) NOT NULL,
  `car_model` varchar(100) NOT NULL,
  `car_year` int(11) NOT NULL,
  `car_color` varchar(50) NOT NULL,
  `license_plate` varchar(20) NOT NULL,
  `seats` int(11) NOT NULL,
  `license_document_path` varchar(500) DEFAULT NULL,
  `registration_document_path` varchar(500) DEFAULT NULL,
  `insurance_document_path` varchar(500) DEFAULT NULL,
  `car_photo_path` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `establishments`
--

CREATE TABLE `establishments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `establishments`
--

INSERT INTO `establishments` (`id`, `name`, `address`, `city`, `postal_code`, `created_at`, `updated_at`) VALUES
(1, 'Cité des Metièrs et Competences', '123 ', 'tamesna', '75016', '2025-11-03 22:36:06', '2025-11-03 22:36:06'),
(2, 'Université de Rabat', '456 Rue Université', 'Rabat', '73405', '2025-11-03 22:36:06', '2025-11-03 22:36:06'),
(3, 'IUT de la Ville', '789 Boulevard Technique', 'Lyon', '69000', '2025-11-03 22:36:06', '2025-11-03 22:36:06');

-- --------------------------------------------------------

--
-- Structure de la table `friends`
--

CREATE TABLE `friends` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `friend_id` int(11) NOT NULL,
  `status` enum('pending','accepted','blocked') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `friends`
--

INSERT INTO `friends` (`id`, `user_id`, `friend_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'accepted', '2025-11-03 22:36:06', '2025-11-03 22:36:06'),
(2, 2, 1, 'accepted', '2025-11-03 22:36:06', '2025-11-03 22:36:06');

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `trip_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('trip_confirmation','friend_request','trip_reminder','review_received','friend_online','promotion','system') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `action_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `related_id`, `is_read`, `action_url`, `created_at`) VALUES
(1, 1, 'trip_confirmation', 'Trajet confirmé !', 'Votre réservation pour Paris → Lyon a été confirmée', 1, 0, NULL, '2025-11-03 22:36:06'),
(2, 1, 'friend_request', 'Nouvelle invitation', 'Alexandre Petit veut vous ajouter comme ami', 3, 0, NULL, '2025-11-03 22:36:06');

-- --------------------------------------------------------

--
-- Structure de la table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(6,2) NOT NULL,
  `payment_method` enum('card','paypal','transfer') DEFAULT 'card',
  `transaction_id` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reported_user_id` int(11) DEFAULT NULL,
  `trip_id` int(11) DEFAULT NULL,
  `type` enum('delay','behavior','payment','safety','vehicle','other') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `urgency` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `admin_response` text DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `report_attachments`
--

CREATE TABLE `report_attachments` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `reviewed_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `response` text DEFAULT NULL,
  `is_driver_review` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `trips`
--

CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `departure_address` text NOT NULL,
  `departure_lat` decimal(10,8) DEFAULT NULL,
  `departure_lng` decimal(11,8) DEFAULT NULL,
  `destination_address` text NOT NULL,
  `destination_lat` decimal(10,8) DEFAULT NULL,
  `destination_lng` decimal(11,8) DEFAULT NULL,
  `departure_datetime` datetime NOT NULL,
  `estimated_duration` int(11) DEFAULT NULL,
  `available_seats` int(11) NOT NULL,
  `price_per_seat` decimal(6,2) NOT NULL,
  `description` text DEFAULT NULL,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `confirmation_code` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `trips`
--

INSERT INTO `trips` (`id`, `driver_id`, `vehicle_id`, `departure_address`, `departure_lat`, `departure_lng`, `destination_address`, `destination_lat`, `destination_lng`, `departure_datetime`, `estimated_duration`, `available_seats`, `price_per_seat`, `description`, `preferences`, `status`, `confirmation_code`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 'Paris, Gare du Nord', NULL, NULL, 'Lyon, Part-Dieu', NULL, NULL, '2024-03-20 08:30:00', NULL, 3, 12.00, NULL, '{\"silence\": false, \"music\": true, \"animals\": true, \"smoking\": false}', 'scheduled', 'ABC123XY', '2025-11-03 22:36:06', '2025-11-03 22:36:06'),
(2, 2, 2, 'Versailles, Château', NULL, NULL, 'Orléans, Centre', NULL, NULL, '2024-03-20 14:00:00', NULL, 2, 15.00, NULL, '{\"silence\": true, \"music\": false, \"animals\": false, \"smoking\": false}', 'scheduled', 'DEF456YZ', '2025-11-03 22:36:06', '2025-11-03 22:36:06');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `birth_date` date NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(500) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `establishment_id` int(11) DEFAULT NULL,
  `role` enum('passenger','driver','admin') DEFAULT 'passenger',
  `document_path` varchar(500) DEFAULT NULL,
  `document_type` enum('attestation_scolarite','fiche_paie','carte_etudiant','carte_pro','autres') DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_online` tinyint(1) DEFAULT 0,
  `last_seen` timestamp NOT NULL DEFAULT current_timestamp(),
  `rating` decimal(3,2) DEFAULT 5.00,
  `total_ratings` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `first_name`, `last_name`, `birth_date`, `phone`, `profile_picture`, `bio`, `establishment_id`, `role`, `document_path`, `document_type`, `is_verified`, `is_online`, `last_seen`, `rating`, `total_ratings`, `created_at`, `updated_at`) VALUES
(1, 'user@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jean', 'Dupont', '1995-05-15', '+33123456789', NULL, NULL, 1, 'passenger', NULL, NULL, 1, 0, '2025-11-04 04:07:56', 4.50, 0, '2025-11-03 22:36:06', '2025-11-03 22:36:06'),
(2, 'driver@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Marie', 'Curie', '1990-08-20', '+33123456790', NULL, NULL, 2, 'driver', NULL, NULL, 1, 0, '2025-11-04 04:07:56', 4.80, 0, '2025-11-03 22:36:06', '2025-11-03 22:36:06'),
(3, 'admin@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Système', '1985-01-01', '+33123456791', NULL, NULL, 1, 'admin', NULL, NULL, 1, 0, '2025-11-04 04:07:56', 5.00, 0, '2025-11-03 22:36:06', '2025-11-03 22:36:06'),
(4, 'mouadg1234@gmail.com', '$2y$10$GPHvUNKXNbDtNbmkihrDx.H3ufZpcWt9/.T1IMy0lCghc5QCL8BHy', 'Mouad', 'guarraz', '2006-05-05', '0661597594', NULL, NULL, 1, 'driver', NULL, NULL, 0, 1, '2025-11-05 02:14:00', 5.00, 0, '2025-11-04 08:58:34', '2025-11-05 02:14:00'),
(5, 'saad@gmail.com', '$2y$10$TfOaUr0pt48mEAGRQHsjrey/3DU0zL6Ax4pMLLrxFwAFn3u2CDQTm', 'saad', 'gua', '1991-05-05', '0674451180', NULL, NULL, 2, 'passenger', NULL, NULL, 0, 0, '2025-11-05 02:13:52', 5.00, 0, '2025-11-04 12:38:16', '2025-11-05 02:13:52');

-- --------------------------------------------------------

--
-- Structure de la table `user_stats`
--

CREATE TABLE `user_stats` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reliability_score` decimal(3,2) DEFAULT 5.00,
  `punctuality_score` decimal(3,2) DEFAULT 5.00,
  `friendliness_score` decimal(3,2) DEFAULT 5.00,
  `cleanliness_score` decimal(3,2) DEFAULT 5.00,
  `total_trips` int(11) DEFAULT 0,
  `total_reviews` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user_stats`
--

INSERT INTO `user_stats` (`id`, `user_id`, `reliability_score`, `punctuality_score`, `friendliness_score`, `cleanliness_score`, `total_trips`, `total_reviews`, `created_at`, `updated_at`) VALUES
(1, 4, 5.00, 5.00, 5.00, 5.00, 0, 0, '2025-11-04 08:58:34', '2025-11-04 08:58:34'),
(2, 5, 5.00, 5.00, 5.00, 5.00, 0, 0, '2025-11-04 12:38:16', '2025-11-04 12:38:16');

-- --------------------------------------------------------

--
-- Structure de la table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `brand` varchar(100) NOT NULL,
  `model` varchar(100) NOT NULL,
  `year` int(11) NOT NULL,
  `color` varchar(50) NOT NULL,
  `license_plate` varchar(20) NOT NULL,
  `seats` int(11) NOT NULL,
  `photo_path` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `vehicles`
--

INSERT INTO `vehicles` (`id`, `user_id`, `brand`, `model`, `year`, `color`, `license_plate`, `seats`, `photo_path`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 2, 'Peugeot', '308', 2020, 'Gris', 'AB-123-CD', 5, NULL, 1, '2025-11-03 22:36:06', '2025-11-03 22:36:06'),
(2, 2, 'Renault', 'Clio', 2019, 'Bleu', 'EF-456-GH', 4, NULL, 1, '2025-11-03 22:36:06', '2025-11-03 22:36:06');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_trip_passenger` (`trip_id`,`passenger_id`),
  ADD UNIQUE KEY `confirmation_code` (`confirmation_code`),
  ADD KEY `idx_bookings_trip_id` (`trip_id`),
  ADD KEY `idx_bookings_passenger_id` (`passenger_id`);

--
-- Index pour la table `detours`
--
ALTER TABLE `detours`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trip_id` (`trip_id`),
  ADD KEY `passenger_id` (`passenger_id`);

--
-- Index pour la table `driver_applications`
--
ALTER TABLE `driver_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `establishments`
--
ALTER TABLE `establishments`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_friendship` (`user_id`,`friend_id`),
  ADD KEY `friend_id` (`friend_id`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `trip_id` (`trip_id`),
  ADD KEY `idx_messages_sender_receiver` (`sender_id`,`receiver_id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user_id` (`user_id`),
  ADD KEY `idx_notifications_is_read` (`is_read`);

--
-- Index pour la table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Index pour la table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reporter_id` (`reporter_id`),
  ADD KEY `reported_user_id` (`reported_user_id`),
  ADD KEY `trip_id` (`trip_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Index pour la table `report_attachments`
--
ALTER TABLE `report_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Index pour la table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `idx_reviews_reviewed_id` (`reviewed_id`);

--
-- Index pour la table `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `confirmation_code` (`confirmation_code`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `idx_trips_departure_datetime` (`departure_datetime`),
  ADD KEY `idx_trips_status` (`status`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `establishment_id` (`establishment_id`);

--
-- Index pour la table `user_stats`
--
ALTER TABLE `user_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_plate` (`license_plate`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `detours`
--
ALTER TABLE `detours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `driver_applications`
--
ALTER TABLE `driver_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `establishments`
--
ALTER TABLE `establishments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `friends`
--
ALTER TABLE `friends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `report_attachments`
--
ALTER TABLE `report_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `trips`
--
ALTER TABLE `trips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `user_stats`
--
ALTER TABLE `user_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`passenger_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `detours`
--
ALTER TABLE `detours`
  ADD CONSTRAINT `detours_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detours_ibfk_2` FOREIGN KEY (`passenger_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `driver_applications`
--
ALTER TABLE `driver_applications`
  ADD CONSTRAINT `driver_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `friends`
--
ALTER TABLE `friends`
  ADD CONSTRAINT `friends_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `friends_ibfk_2` FOREIGN KEY (`friend_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reports_ibfk_4` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `report_attachments`
--
ALTER TABLE `report_attachments`
  ADD CONSTRAINT `report_attachments_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`reviewed_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `trips`
--
ALTER TABLE `trips`
  ADD CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trips_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `user_stats`
--
ALTER TABLE `user_stats`
  ADD CONSTRAINT `user_stats_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
