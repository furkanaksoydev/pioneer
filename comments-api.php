<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');

const COMMENT_MAX_LENGTH = 1200;
const COMMENT_MIN_LENGTH = 20;

function response(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function database(): PDO {
    $dataPath = __DIR__ . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dataPath) && !mkdir($dataPath, 0750, true) && !is_dir($dataPath)) {
        response(['ok' => false, 'message' => 'Yorum veritabanı dizini oluşturulamadı.'], 500);
    }

    $pdo = new PDO('sqlite:' . $dataPath . DIRECTORY_SEPARATOR . 'comments.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        status TEXT NOT NULL DEFAULT "pending" CHECK(status IN ("pending", "approved", "rejected")),
        full_name TEXT NOT NULL,
        email TEXT NOT NULL,
        location TEXT,
        rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5),
        body TEXT NOT NULL,
        image_filename TEXT,
        image_url TEXT,
        image_status TEXT NOT NULL DEFAULT "none" CHECK(image_status IN ("none", "awaiting-r2", "ready", "rejected")),
        consent_at TEXT NOT NULL,
        ip_hash TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at TEXT,
        reviewer_note TEXT
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_public ON comments(status, created_at DESC)');
    $columns = array_column($pdo->query('PRAGMA table_info(comments)')->fetchAll(), 'name');
    if (!in_array('admin_reply', $columns, true)) $pdo->exec('ALTER TABLE comments ADD COLUMN admin_reply TEXT');
    if (!in_array('replied_at', $columns, true)) $pdo->exec('ALTER TABLE comments ADD COLUMN replied_at TEXT');
    seedExamples($pdo);
    return $pdo;
}

function seedExamples(PDO $pdo): void {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
    if ($count > 0) return;

    $insert = $pdo->prepare('INSERT INTO comments (status, full_name, email, location, rating, body, image_filename, image_url, image_status, consent_at, ip_hash, created_at, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $examples = [
        ['approved', 'Mert A.', 'ornek1@pioneer.local', 'Süleymanpaşa', 5, 'Arsamız için yaptığımız ilk görüşmede fizibiliteyi ve olası proje senaryolarını çok net anlattılar. Süreç yaklaşımı güven verdi.', null, null, 'none', '2026-08-25 09:20:00', 'sample-1', '2026-08-25 09:20:00', '2026-08-25 10:00:00'],
        ['approved', 'Selin K.', 'ornek2@pioneer.local', 'Tekirdağ', 5, 'Ofis yenileme projesinde ihtiyaçlarımızı doğru okudular. Malzeme ve uygulama kararları beklediğimizden daha derli toplu ilerledi.', null, null, 'none', '2026-08-23 14:35:00', 'sample-2', '2026-08-23 14:35:00', '2026-08-23 15:00:00'],
        ['approved', 'Kaan D.', 'ornek3@pioneer.local', 'Altınova', 4, 'Cephe ve iç mekân detaylarını yerinde görmek karar vermemizi kolaylaştırdı. Görüşme süreci özenli ve anlaşılırdı.', 'ornek-proje-detayi.jpeg', 'https://cdn.lavira360.com/pioneer/gorseller/WhatsApp%20Image%202026-08-20%20at%2022.24.24%20(2).jpeg', 'ready', '2026-08-21 11:10:00', 'sample-3', '2026-08-21 11:10:00', '2026-08-21 12:00:00'],
        ['approved', 'Ece T.', 'ornek4@pioneer.local', 'Değirmenaltı', 5, 'Villa projemiz için planlama aşamasında sundukları alternatifler sayesinde arsayı çok daha doğru değerlendirme şansı bulduk.', 'ornek-villa-gorunumu.jpeg', 'https://cdn.lavira360.com/pioneer/gorseller/WhatsApp%20Image%202026-08-20%20at%2022.24.26%20(1).jpeg', 'ready', '2026-08-19 16:40:00', 'sample-4', '2026-08-19 16:40:00', '2026-08-19 17:00:00']
    ];
    foreach ($examples as $example) $insert->execute($example);
}

function input(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function cleanString(mixed $value): string {
    return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
}

function requestIpHash(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $secret = getenv('PIONEER_COMMENT_IP_SALT') ?: 'pioneer-local-change-this-salt';
    return hash_hmac('sha256', $ip, $secret);
}

function publicComments(PDO $pdo, int $limit): array {
    $statement = $pdo->prepare('SELECT id, full_name, location, rating, body, image_url, admin_reply, replied_at, created_at FROM comments WHERE status = "approved" ORDER BY datetime(created_at) DESC LIMIT :limit');
    $statement->bindValue(':limit', max(1, min($limit, 24)), PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function validateComment(array $data): array {
    $name = cleanString($data['full_name'] ?? '');
    $email = strtolower(cleanString($data['email'] ?? ''));
    $location = cleanString($data['location'] ?? '');
    $body = cleanString($data['body'] ?? '');
    $imageName = basename(cleanString($data['image_name'] ?? ''));
    $rating = filter_var($data['rating'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);

    if (cleanString($data['website'] ?? '') !== '') response(['ok' => false, 'message' => 'Yorumunuz doğrulanamadı.'], 422);
    if (mb_strlen($name) < 2 || mb_strlen($name) > 70 || !preg_match('/\p{L}/u', $name)) response(['ok' => false, 'message' => 'Lütfen adınızı en az iki harfle yazın.'], 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) response(['ok' => false, 'message' => 'Geçerli bir e-posta adresi girin.'], 422);
    if ($rating === false) response(['ok' => false, 'message' => 'Lütfen 1 ile 5 arasında bir değerlendirme seçin.'], 422);
    if (mb_strlen($body) < COMMENT_MIN_LENGTH || mb_strlen($body) > COMMENT_MAX_LENGTH) response(['ok' => false, 'message' => 'Yorumunuz 20 ile 1200 karakter arasında olmalıdır.'], 422);
    if (preg_match('/(.)\1{6,}/u', $body)) response(['ok' => false, 'message' => 'Yorumda tekrarlanan anlamsız karakterler bulunuyor.'], 422);
    preg_match_all('/[\p{L}]{2,}/u', $body, $words);
    if (count($words[0]) < 3) response(['ok' => false, 'message' => 'Lütfen deneyiminizi birkaç anlamlı kelimeyle paylaşın.'], 422);
    if (($data['consent'] ?? false) !== true && ($data['consent'] ?? '') !== 'true') response(['ok' => false, 'message' => 'Yayın ve gizlilik bilgilendirmesini onaylamanız gerekir.'], 422);

    return compact('name', 'email', 'location', 'body', 'imageName', 'rating');
}

try {
    $pdo = database();
    $action = $_GET['action'] ?? 'list';

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        response(['ok' => true, 'comments' => publicComments($pdo, (int) ($_GET['limit'] ?? 12))]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit') {
        $comment = validateComment(input());
        $ipHash = requestIpHash();
        $recent = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE ip_hash = ? AND datetime(created_at) > datetime("now", "-1 hour")');
        $recent->execute([$ipHash]);
        if ((int) $recent->fetchColumn() >= 3) response(['ok' => false, 'message' => 'Bir saat içinde en fazla üç yorum gönderebilirsiniz.'], 429);

        $imageStatus = $comment['imageName'] !== '' ? 'awaiting-r2' : 'none';
        $insert = $pdo->prepare('INSERT INTO comments (status, full_name, email, location, rating, body, image_filename, image_status, consent_at, ip_hash) VALUES ("pending", ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)');
        $insert->execute([$comment['name'], $comment['email'], $comment['location'], $comment['rating'], $comment['body'], $comment['imageName'] ?: null, $imageStatus, $ipHash]);
        response(['ok' => true, 'message' => 'Yorumunuz alındı. Yayınlanmadan önce ekip tarafından incelenecek.', 'id' => (int) $pdo->lastInsertId()]);
    }

    response(['ok' => false, 'message' => 'İstenen işlem bulunamadı.'], 404);
} catch (Throwable $exception) {
    error_log('Pioneer comments API: ' . $exception->getMessage());
    response(['ok' => false, 'message' => 'Yorum sistemi şu anda kullanılamıyor.'], 500);
}
