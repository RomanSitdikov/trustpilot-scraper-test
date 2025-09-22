CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id VARCHAR(255) NOT NULL UNIQUE,
    source_url TEXT,
    user_name VARCHAR(255),
    user_reviews_count INT,
    rating INT,
    title TEXT,
    body TEXT,
    review_date VARCHAR(100),
    experience_date VARCHAR(100),
    country VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS avatars (
     id VARCHAR(50) PRIMARY KEY,
     path VARCHAR(255) NOT NULL
);

ALTER TABLE reviews
    ADD COLUMN avatar_id VARCHAR(50) NULL,
    ADD CONSTRAINT fk_avatar FOREIGN KEY (avatar_id) REFERENCES avatars(id);