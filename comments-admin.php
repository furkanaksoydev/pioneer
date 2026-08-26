<?php
declare(strict_types=1);

const ADMIN_COOKIE = 'pioneer_comments_admin';

function h(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function adminDatabase(): PDO {
    $path = __DIR__ . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) throw new RuntimeException('Veritabanı dizini oluşturulamadı.');
    $pdo = new PDO('sqlite:' . $path . DIRECTORY_SEPARATOR . 'comments.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('CREATE TABLE IF NOT EXISTS comments (id INTEGER PRIMARY KEY AUTOINCREMENT, status TEXT NOT NULL DEFAULT "pending" CHECK(status IN ("pending", "approved", "rejected")), full_name TEXT NOT NULL, email TEXT NOT NULL, location TEXT, rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5), body TEXT NOT NULL, image_filename TEXT, image_url TEXT, image_status TEXT NOT NULL DEFAULT "none" CHECK(image_status IN ("none", "awaiting-r2", "ready", "rejected")), consent_at TEXT NOT NULL, ip_hash TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, reviewed_at TEXT, reviewer_note TEXT)');
    return $pdo;
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name(ADMIN_COOKIE);
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => $https]);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$password = getenv('PIONEER_COMMENTS_ADMIN_PASSWORD') ?: '';
$error = '';
$notice = '';
if ($password === '') $error = 'Yönetici ekranı kapalı. Sunucuda PIONEER_COMMENTS_ADMIN_PASSWORD ortam değişkenini tanımlayın.';

if (isset($_POST['logout'])) { $_SESSION = []; session_destroy(); header('Location: comments-admin.php'); exit; }
if ($password !== '' && isset($_POST['login_password'])) {
    if (hash_equals($password, (string) $_POST['login_password'])) { $_SESSION['authenticated'] = true; $_SESSION['csrf'] = bin2hex(random_bytes(32)); header('Location: comments-admin.php'); exit; }
    $error = 'Parola doğrulanamadı.';
}
$authenticated = !empty($_SESSION['authenticated']) && !empty($_SESSION['csrf']);
if ($authenticated && isset($_POST['review_id'])) {
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { $error = 'İstek doğrulanamadı. Sayfayı yenileyin.'; }
    else {
        $status = $_POST['status'] ?? '';
        $id = filter_var($_POST['review_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!in_array($status, ['approved', 'rejected'], true) || $id === false) $error = 'Geçersiz inceleme işlemi.';
        else {
            $pdo = adminDatabase();
            $update = $pdo->prepare('UPDATE comments SET status = ?, reviewer_note = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = "pending"');
            $update->execute([$status, trim((string) ($_POST['reviewer_note'] ?? '')), $id]);
            $notice = $update->rowCount() ? 'Yorum ' . ($status === 'approved' ? 'onaylandı ve yayın akışına alındı.' : 'reddedildi.') : 'Yorum daha önce işleme alınmış olabilir.';
        }
    }
}
$pending = [];
if ($authenticated) { try { $pending = adminDatabase()->query('SELECT id, full_name, email, location, rating, body, image_filename, image_status, created_at FROM comments WHERE status = "pending" ORDER BY datetime(created_at) ASC')->fetchAll(); } catch (Throwable $exception) { $error = 'Veritabanına erişilemedi.'; } }
?>
<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex,nofollow"><title>Yorum İnceleme | Pioneer</title><link rel="stylesheet" href="comments-admin-v2.css"></head><body><header class="admin-shell admin-header"><div><p class="admin-kicker">Pioneer · private moderation</p><h1>Yorum inceleme kuyruğu</h1></div><?php if ($authenticated): ?><form method="post"><button class="admin-logout" name="logout" value="1">Oturumu kapat</button></form><?php else: ?><a class="admin-link" href="index.html">← Siteye dön</a><?php endif; ?></header><main class="admin-shell"><?php if (!$authenticated): ?><section class="admin-login"><h2>Yetkili erişim</h2><p>Bu ekran herkese açık yorumların yayınlanmadan önce manuel olarak değerlendirilmesi içindir.</p><?php if ($error): ?><p class="admin-message"><?= h($error) ?></p><?php endif; ?><?php if ($password !== ''): ?><form method="post"><label>Yönetici parolası<input type="password" name="login_password" autocomplete="current-password" required></label><button class="admin-button" type="submit">Kuyruğu aç</button></form><?php endif; ?></section><?php else: ?><?php if ($notice): ?><p class="admin-message" style="color:#dfc98d"><?= h($notice) ?></p><?php endif; ?><?php if ($error): ?><p class="admin-message"><?= h($error) ?></p><?php endif; ?><section class="admin-grid"><?php if (!$pending): ?><p class="admin-empty">İncelenecek yeni yorum bulunmuyor.</p><?php endif; ?><?php foreach ($pending as $comment): ?><article class="admin-card"><div class="admin-meta"><span>#<?= (int) $comment['id'] ?></span><span><?= h($comment['created_at']) ?></span><span><?= (int) $comment['rating'] ?>/5</span><span><?= h($comment['location'] ?: 'Bölge belirtilmedi') ?></span></div><h2><?= h($comment['full_name']) ?></h2><div class="admin-meta"><span><?= h($comment['email']) ?></span><?php if ($comment['image_filename']): ?><span class="admin-image-state">Görsel: <?= h($comment['image_filename']) ?> · <?= h($comment['image_status']) ?></span><?php endif; ?></div><p><?= nl2br(h($comment['body'])) ?></p><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="review_id" value="<?= (int) $comment['id'] ?>"><input class="admin-note" name="reviewer_note" maxlength="500" placeholder="İnceleme notu (yalnızca yönetici için)"><button class="admin-button" name="status" value="approved" type="submit">Onayla ve yayınla</button><button class="admin-button admin-button--reject" name="status" value="rejected" type="submit">Reddet</button></form></article><?php endforeach; ?></section><?php endif; ?></main></body></html>
