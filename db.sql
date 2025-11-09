-- Création de la base de données
CREATE DATABASE IF NOT EXISTS safarlink;
USE safarlink;

-- Table des établissements
CREATE TABLE IF NOT EXISTS establishments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(20),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Assurez-vous que la table users existe avec tous les champs nécessaires
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(191) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    user_type ENUM('driver', 'passenger') NOT NULL DEFAULT 'passenger',
    establishment_id INT,
    avatar_url VARCHAR(500),
    license_plate VARCHAR(20),
    car_model VARCHAR(100),
    car_color VARCHAR(50),
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_ratings INT DEFAULT 0,
    is_verified BOOLEAN DEFAULT FALSE,
    verification_code VARCHAR(6),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (establishment_id) REFERENCES establishments(id)
);

-- Table des trajets
CREATE TABLE IF NOT EXISTS trips (
    id INT PRIMARY KEY AUTO_INCREMENT,
    driver_id INT NOT NULL,
    departure_establishment_id INT,
    destination_establishment_id INT,
    -- Ces colonnes stockent le nom textuel de l'adresse, même si un établissement est choisi
    departure_address TEXT NOT NULL,
    destination_address TEXT NOT NULL,
    departure_latitude DECIMAL(10, 8),
    departure_longitude DECIMAL(11, 8),
    destination_latitude DECIMAL(10, 8),
    destination_longitude DECIMAL(11, 8),
    departure_date DATE NOT NULL,
    departure_time TIME NOT NULL,
    available_seats INT NOT NULL,
    price_per_seat DECIMAL(8,2) NOT NULL,
    description TEXT,
    trip_rules TEXT,
    possible_detours TEXT,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES users(id),
    FOREIGN KEY (departure_establishment_id) REFERENCES establishments(id),
    FOREIGN KEY (destination_establishment_id) REFERENCES establishments(id)
);

-- Table des réservations
CREATE TABLE IF NOT EXISTS bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    trip_id INT NOT NULL,
    passenger_id INT NOT NULL,
    seats_booked INT NOT NULL DEFAULT 1,
    total_price DECIMAL(8,2) NOT NULL,
    booking_code VARCHAR(10) UNIQUE NOT NULL,
    qr_code_url VARCHAR(500),
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    driver_confirmed BOOLEAN DEFAULT FALSE,
    passenger_confirmed BOOLEAN DEFAULT FALSE,
    secret_code VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id),
    FOREIGN KEY (passenger_id) REFERENCES users(id)
);

-- Table des avis et notations
CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    reviewed_user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_user_id) REFERENCES users(id)
);

-- Table des notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('booking', 'confirmation', 'reminder', 'system') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    related_booking_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (related_booking_id) REFERENCES bookings(id)
);

-- Table des logs d'administration
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insertion des établissements de test
INSERT INTO establishments (name, address, city, postal_code, latitude, longitude) VALUES 
('Cité des Métiers et Compétences', '123 Avenue de la Formation', 'Casablanca', '20000', 33.573110, -7.589843),
('Université Mohammed VI', '456 Boulevard des Sciences', 'Rabat', '10000', 34.020882, -6.841650),
('École Nationale de Commerce', '789 Rue des Affaires', 'Marrakech', '40000', 31.629472, -8.008955),
('Institut de Technologie', '321 Avenue de l''Innovation', 'Tanger', '90000', 35.759465, -5.833954);