<?php
namespace App;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class TrustpilotScraper
{
    private Database $db;
    private Client $http;
    private string $imagesDir;

    public function __construct(Database $db, string $imagesDir = 'images')
    {
        $this->db = $db;
        $this->http = new Client([
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; TrustpilotScraper/1.0)'
            ]
        ]);
        $this->imagesDir = rtrim($imagesDir, '/');
        if (!is_dir($this->imagesDir)) {
            mkdir($this->imagesDir, 0777, true);
        }
    }

    /**
     * Парсинг списка отзывов по домену
     */
    public function scrapeListingPage(string $url)
    {
        $next = $url;
        while ($next) {
            echo "Fetching company page: $next\n";
            $res = $this->http->get($next);
            $html = (string)$res->getBody();
            $crawler = new Crawler($html);

            // собираем ссылки на отзывы
            $crawler->filter('a[data-review-title-typography]')->each(function (Crawler $node) use ($url) {
                $href = $node->attr('href'); // /reviews/{id}
                if (!$href) return;
                $reviewUrl = $this->absoluteUrl($href);
                $this->scrapeReviewPage($reviewUrl, $url);
            });

            // пагинация: ищем кнопку Next
            $next = null;
            try {
                $nextNode = $crawler->filter('a[data-pagination-button-next-link]')->first();
                if ($nextNode->count()) {
                    $href = $nextNode->attr('href');
                    if ($href) {
                        $next = $this->absoluteUrl($href);
                    }
                }
            } catch (\Exception $e) {
                $next = null;
            }

            sleep(1); // задержка между страницами
        }
    }

    /**
     * Парсинг конкретного отзыва
     */
    public function scrapeReviewPage(string $reviewUrl, string $sourceUrl)
    {
        echo "Fetching review page: $reviewUrl\n";
        $res = $this->http->get($reviewUrl);
        $html = (string)$res->getBody();
        $crawler = new Crawler($html);

        preg_match('#/reviews/([a-f0-9]+)#', $reviewUrl, $m);
        $reviewId = $m[1] ?? null;
        if (!$reviewId) return;

        if ($this->db->reviewExists($reviewId)) {
            echo "Review $reviewId already exists — skipping.\n";
            return;
        }

        // avatar
        $avatarId = null;
        $avatarPath = null;
        try {
            $avatarNode = $crawler->filter('[data-consumer-avatar-image]');
            if ($avatarNode->count() > 0) {
                $avatar = $avatarNode->attr('src');

                if ($avatar && preg_match('#/([a-f0-9]{24})/#', $avatar, $m)) {
                    $avatarId = $m[1];
                    echo "Found avatar ID: $avatarId\n";

                    // Проверяем, есть ли аватарка уже в БД
                    $existing = $this->db->avatarExists($avatarId);
                    if ($existing) {
                        echo "Avatar $avatarId already exists — skipping download.\n";
                        $avatarPath = $existing;
                    } else {
                        echo "Downloading new avatar $avatarId\n";
                        $avatarPath = $this->downloadImage($avatar, $avatarId);
                        if ($avatarPath) {
                            $this->db->insertAvatar($avatarId, $avatarPath);
                        }
                    }
                }
            } else {
                echo "No avatar found for this review.\n";
            }
        } catch (\Exception $e) {
            echo "Avatar parse error: " . $e->getMessage() . "\n";
        }
        // user name
        $userName = $this->safeText($crawler->filter('[data-consumer-name-typography]'));

        // user reviews count
        $userReviewsCount = null;
        try {
            $countText = $crawler->filter('[data-consumer-reviews-count-typography]')->text();
            $userReviewsCount = intval(preg_replace('/[^0-9]/', '', $countText));
        } catch (\Exception $e) {}

        // rating
        $rating = null;
        try {
            $rating = intval($crawler->filter('[data-service-review-rating]')->attr('data-service-review-rating'));
        } catch (\Exception $e) {}

        // title
        $title = $this->safeText($crawler->filter('[data-service-review-title-typography]'));

        // body
        $body = $this->safeText($crawler->filter('[data-service-review-text-typography]'));

        // review_date
        $reviewDate = null;
        try {
            $reviewDate = $crawler->filter('[data-service-review-date-time-ago]')->attr('datetime');
        } catch (\Exception $e) {}

        // country
        $country = null;
        try {
            $country = $this->safeText($crawler->filter('[data-name="consumer-country"] span'));
        } catch (\Exception $e) {}

        $data = [
            ':review_id' => $reviewId,
            ':source_url' => $sourceUrl,
            ':user_name' => $userName,
            ':user_reviews_count' => $userReviewsCount,
            ':rating' => $rating,
            ':title' => $title,
            ':body' => $body,
            ':review_date' => $reviewDate,
            ':experience_date' => null,
            ':country' => $country,
            ':avatar_id' => $avatarId
        ];

        $this->db->insertReview($data);
        echo "Saved review: $reviewId\n";
    }

    private function safeText(Crawler $node = null)
    {
        try {
            return trim($node->text());
        } catch (\Exception $e) {
            return null;
        }
    }

    private function downloadImage(string $url, string $avatarId): ?string
    {
        try {
            if (strpos($url, '//') === 0) $url = 'https:' . $url;
            $res = $this->http->get($url);
            $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = $this->imagesDir . '/' . $avatarId . '.' . $ext;
            file_put_contents($filename, $res->getBody()->getContents());
            return $filename;
        } catch (\Exception $e) {
            echo "Failed to download image: " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function absoluteUrl(string $href): string
    {
        if (strpos($href, 'http') === 0) return $href;
        return 'https://www.trustpilot.com' . $href;
    }
}