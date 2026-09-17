
-- Utilisateur central de la plateforme (MCD : ADMINISTRATEUR).
CREATE TABLE IF NOT EXISTS administrators (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(180) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_administrators_email (email)
) ENGINE=InnoDB;

-- Table historique conservee pour la compatibilite avec api.php.
CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NULL,
    company_name VARCHAR(180) NOT NULL,
    logo LONGTEXT NULL,
    approved TINYINT(1) NOT NULL DEFAULT 0,
    payments JSON NOT NULL,
    required_docs JSON NOT NULL,
    fleet JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_companies_approved (approved),
    INDEX idx_companies_administrator (administrator_id),
    CONSTRAINT fk_companies_administrator FOREIGN KEY (administrator_id) REFERENCES administrators(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS boats (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    boat_name VARCHAR(180) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    boat_status VARCHAR(30) NOT NULL DEFAULT 'Actif',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_boats_company_name (company_id, boat_name),
    CONSTRAINT fk_boats_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS boat_levels (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    boat_id BIGINT UNSIGNED NOT NULL,
    level_label VARCHAR(120) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    capacity INT UNSIGNED NOT NULL DEFAULT 1,
    available_seats INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_boat_level (boat_id, level_label),
    CONSTRAINT fk_levels_boat FOREIGN KEY (boat_id) REFERENCES boats(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS boat_schedules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    boat_id BIGINT UNSIGNED NOT NULL,
    day_name VARCHAR(20) NOT NULL,
    morning_departure TIME NULL,
    morning_arrival TIME NULL,
    afternoon_departure TIME NULL,
    afternoon_arrival TIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_boat_schedule_day (boat_id, day_name),
    CONSTRAINT fk_schedules_boat FOREIGN KEY (boat_id) REFERENCES boats(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(180) NOT NULL,
    email VARCHAR(255) NOT NULL,
    whatsapp VARCHAR(40) NOT NULL DEFAULT '',
    telephone VARCHAR(40) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_clients_name (full_name),
    INDEX idx_clients_email (email)
) ENGINE=InnoDB;

-- Les colonnes texte/JSON de cette table assurent la compatibilite avec l'application actuelle.
CREATE TABLE IF NOT EXISTS reservations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NULL,
    boat_id BIGINT UNSIGNED NULL,
    level_id BIGINT UNSIGNED NULL,
    client VARCHAR(180) NOT NULL,
    email VARCHAR(255) NOT NULL,
    whatsapp VARCHAR(40) NOT NULL DEFAULT '',
    telephone VARCHAR(40) NOT NULL DEFAULT '',
    compagnie VARCHAR(180) NOT NULL,
    bateau VARCHAR(180) NOT NULL,
    niveau VARCHAR(120) NOT NULL,
    prix DECIMAL(10,2) NOT NULL DEFAULT 0,
    reservation_date VARCHAR(30) NOT NULL,
    reservation_time VARCHAR(30) NOT NULL,
    statut VARCHAR(50) NOT NULL DEFAULT 'En attente de validation',
    docs_soumis JSON NOT NULL,
    pay_img LONGTEXT NULL,
    acceptance_logo LONGTEXT NULL,
    acceptance_message TEXT NULL,
    ticket_code VARCHAR(80) NULL,
    rejection_reason TEXT NULL,
    checked_in TINYINT(1) NOT NULL DEFAULT 0,
    accepted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_reservations_company_boat (compagnie, bateau),
    INDEX idx_reservations_status (statut),
    INDEX idx_reservations_client (client_id),
    CONSTRAINT fk_reservations_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_reservations_boat FOREIGN KEY (boat_id) REFERENCES boats(id) ON DELETE SET NULL,
    CONSTRAINT fk_reservations_level FOREIGN KEY (level_id) REFERENCES boat_levels(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservation_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id BIGINT UNSIGNED NOT NULL,
    document_name VARCHAR(180) NOT NULL,
    file_data LONGTEXT NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_documents_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_proof LONGTEXT NULL,
    payment_status VARCHAR(30) NOT NULL DEFAULT 'En attente',
    paid_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_reservation (reservation_id),
    CONSTRAINT fk_payments_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id BIGINT UNSIGNED NOT NULL,
    ticket_code VARCHAR(80) NOT NULL,
    company_logo LONGTEXT NULL,
    acceptance_message TEXT NOT NULL,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ticket_reservation (reservation_id),
    UNIQUE KEY uq_ticket_code (ticket_code),
    CONSTRAINT fk_tickets_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS checkins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    checkin_status VARCHAR(30) NOT NULL DEFAULT 'Arrivé',
    arrived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_checkin_ticket (ticket_id),
    CONSTRAINT fk_checkins_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB;
