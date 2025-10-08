CREATE TABLE domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_domain_name (name)
);

CREATE TABLE urls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    full_url VARCHAR(1024) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    UNIQUE KEY unique_url (full_url(500)),
    INDEX idx_url_domain (domain_id)
);

CREATE TABLE elements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_element_name (name)
);

CREATE TABLE requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url_id INT NOT NULL,
    element_id INT NOT NULL,
    count INT NOT NULL,
    duration INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (url_id) REFERENCES urls(id) ON DELETE CASCADE,
    FOREIGN KEY (element_id) REFERENCES elements(id) ON DELETE CASCADE,
    INDEX idx_requests_created (created_at),
    INDEX idx_requests_url_element (url_id, element_id)
);