<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';

class HTMLElementCounter {
    private mysqli $conn;
    private array $post;

    public function __construct(mysqli $conn, array $post) {
        $this->conn = $conn;
        $this->post = $post;
    }

    public function process(): array {
        $validation = $this->validateInput();
        if (!$validation['success']) {
            return $validation;
        }

        $url = trim($this->post['url']);
        $element = strtolower(trim($this->post['element']));
        $domain = parse_url($url, PHP_URL_HOST);

        if (!$domain) {
            return ['success' => false, 'message' => 'Invalid domain in URL'];
        }

        // Check cache first
        $cached = $this->getCachedResult($url, $element);
        if ($cached) {
            return $cached;
        }

        // Fetch and process
        $start = microtime(true);
        $html = $this->fetchPage($url);
        $duration = (int)((microtime(true) - $start) * 1000);

        if ($html === null) {
            return ['success' => false, 'message' => 'Unable to fetch URL. It may be blocked or inaccessible.'];
        }

        $count = $this->countElements($html, $element);
        $this->saveRequest($domain, $url, $element, $count, $duration);
        $stats = $this->buildStatistics($domain, $element);

        return [
            'success' => true,
            'url' => $url,
            'fetched' => date('d/m/Y H:i'),
            'duration' => $duration,
            'count' => $count,
            'stats' => $stats
        ];
    }

    private function validateInput(): array {
        $url = $this->post['url'] ?? '';
        $element = $this->post['element'] ?? '';

        if (empty($url) || empty($element)) {
            return ['success' => false, 'message' => 'Both URL and element are required'];
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Invalid URL format'];
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) {
            return ['success' => false, 'message' => 'Only HTTP and HTTPS URLs are allowed'];
        }

        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/', $element)) {
            return ['success' => false, 'message' => 'Invalid HTML element name'];
        }

        if (strlen($url) > 1000) {
            return ['success' => false, 'message' => 'URL too long'];
        }

        return ['success' => true];
    }

    private function fetchPage(string $url): ?string {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_ENCODING => '', // Accept all encodings
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
            ]
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0 || $httpCode !== 200 || empty($html)) {
            return null;
        }

        return $html;
    }

    private function countElements(string $html, string $tag): int {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        
        $result = $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $result ? $dom->getElementsByTagName($tag)->length : 0;
    }

    private function getCachedResult(string $url, string $element): ?array {
        $stmt = $this->conn->prepare("
            SELECT r.count, r.duration, r.created_at, d.name as domain
            FROM requests r
            JOIN urls u ON r.url_id = u.id
            JOIN domains d ON u.domain_id = d.id
            JOIN elements e ON r.element_id = e.id
            WHERE u.full_url = ? AND e.name = ? AND r.created_at >= NOW() - INTERVAL 5 MINUTE
            ORDER BY r.created_at DESC 
            LIMIT 1
        ");
        
        $stmt->bind_param('ss', $url, $element);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            $stats = $this->buildStatistics($row['domain'], $element);
            
            return [
                'success' => true,
                'url' => $url,
                'fetched' => date('d/m/Y H:i', strtotime($row['created_at'])) . ' (cached)',
                'duration' => (int)$row['duration'],
                'count' => (int)$row['count'],
                'stats' => $stats
            ];
        }
        
        $stmt->close();
        return null;
    }

    private function saveRequest(string $domain, string $url, string $element, int $count, int $duration): void {
        $domainId = $this->getOrCreateId('domains', 'name', $domain);
        $urlId = $this->getOrCreateUrl($domainId, $url);
        $elementId = $this->getOrCreateId('elements', 'name', $element);

        $stmt = $this->conn->prepare("
            INSERT INTO requests (url_id, element_id, count, duration) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->bind_param('iiii', $urlId, $elementId, $count, $duration);
        $stmt->execute();
        $stmt->close();
    }

    private function getOrCreateId(string $table, string $column, string $value): int {
        $stmt = $this->conn->prepare("SELECT id FROM $table WHERE $column = ?");
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return (int)$row['id'];
        }
        $stmt->close();

        $stmt = $this->conn->prepare("INSERT INTO $table ($column) VALUES (?)");
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $id = $this->conn->insert_id;
        $stmt->close();
        
        return $id;
    }

    private function getOrCreateUrl(int $domainId, string $url): int {
        $stmt = $this->conn->prepare("SELECT id FROM urls WHERE full_url = ?");
        $stmt->bind_param('s', $url);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return (int)$row['id'];
        }
        $stmt->close();

        $stmt = $this->conn->prepare("INSERT INTO urls (domain_id, full_url) VALUES (?, ?)");
        $stmt->bind_param('is', $domainId, $url);
        $stmt->execute();
        $id = $this->conn->insert_id;
        $stmt->close();
        
        return $id;
    }

    private function buildStatistics(string $domain, string $element): array {
        $stats = [];
        
        // Get domain statistics in one query
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(DISTINCT u.id) as domain_urls,
                COALESCE(AVG(CASE WHEN r.created_at >= NOW() - INTERVAL 24 HOUR THEN r.duration END), 0) as avg_duration,
                COALESCE(SUM(r.count), 0) as domain_total
            FROM domains d
            LEFT JOIN urls u ON d.id = u.domain_id
            LEFT JOIN requests r ON u.id = r.url_id
            LEFT JOIN elements e ON r.element_id = e.id AND e.name = ?
            WHERE d.name = ?
        ");
        
        $stmt->bind_param('ss', $element, $domain);
        $stmt->execute();
        $stmt->bind_result($stats['domain_urls'], $stats['avg_duration'], $stats['domain_total']);
        $stmt->fetch();
        $stmt->close();
        
        // Get global total
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(r.count), 0) 
            FROM requests r 
            JOIN elements e ON r.element_id = e.id 
            WHERE e.name = ?
        ");
        
        $stmt->bind_param('s', $element);
        $stmt->execute();
        $stmt->bind_result($stats['all_total']);
        $stmt->fetch();
        $stmt->close();
        
        return [
            'domain_urls' => (int)$stats['domain_urls'],
            'avg_duration' => round((float)$stats['avg_duration']),
            'domain_total' => (int)$stats['domain_total'],
            'all_total' => (int)$stats['all_total']
        ];
    }
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Method not allowed');
    }
    
    $counter = new HTMLElementCounter($conn, $_POST);
    $response = $counter->process();
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    
} catch (Throwable $e) {
    error_log("HTMLElementCounter Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error occurred'
    ]);
}