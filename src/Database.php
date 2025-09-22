<?php
namespace App;

use PDO;

class Database
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            "%s:host=%s;port=%s;dbname=%s;charset=utf8mb4",
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database']
        );
        $this->pdo = new PDO($dsn, $config['username'], $config['password']);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function exec(string $sql)
    {
        return $this->pdo->exec($sql);
    }

    public function insertReview(array $data): bool
    {
        $sql = "INSERT IGNORE INTO reviews 
                (review_id, source_url, user_name, user_reviews_count, rating, title, body, review_date, experience_date, country, avatar_id)
                VALUES (:review_id, :source_url, :user_name, :user_reviews_count, :rating, :title, :body, :review_date, :experience_date, :country, :avatar_id)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    public function reviewExists(string $reviewId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM reviews WHERE review_id = :id LIMIT 1');
        $stmt->execute([':id' => $reviewId]);
        return (bool)$stmt->fetchColumn();
    }

    public function insertAvatar(string $avatarId, string $path): bool
    {
        $sql = "INSERT IGNORE INTO avatars (id, path) VALUES (:id, :path)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $avatarId,
            ':path' => $path
        ]);
    }

    public function avatarExists(string $avatarId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT path FROM avatars WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $avatarId]);
        return $stmt->fetchColumn() ?: null;
    }
}