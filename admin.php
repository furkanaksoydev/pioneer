<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'database-config.php';

const PIONEER_ADMIN_USER = 'pioneer.admin';
const PIONEER_ADMIN_PASSWORD_HASH = '$2y$10$tXxAXB2e/XfvthwFz93X6exdVqWMlMzTRu3Legtwi1/rPeWWoXoia';
const PIONEER_ADMIN_SESSION = 'pioneer_private_admin';

function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function money(int $cents): string { return number_format($cents / 100, 2, ',', '.') . ' ₺'; }
function now(): string { return (new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul')))->format('Y-m-d H:i:s'); }
function monthLabel(string $month): string {
    $date = DateTimeImmutable::createFromFormat('!Y-m', $month);
    if (!$date) return $month;
    $names = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];
    return $names[(int) $date->format('n')] . ' ' . $date->format('Y');
}
function db(): PDO {
    $pdo = pioneerMysql();
    $pdo->exec('CREATE TABLE IF NOT EXISTS comments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        status ENUM("pending", "approved", "rejected") NOT NULL DEFAULT "pending",
        full_name VARCHAR(70) NOT NULL, email VARCHAR(190) NOT NULL, location VARCHAR(70) NULL,
        rating TINYINT UNSIGNED NOT NULL, body TEXT NOT NULL, image_filename VARCHAR(255) NULL, image_url TEXT NULL,
        image_status ENUM("none", "awaiting-r2", "ready", "rejected") NOT NULL DEFAULT "none",
        consent_at DATETIME NOT NULL, ip_hash CHAR(64) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL, reviewer_note TEXT NULL, admin_reply TEXT NULL, replied_at DATETIME NULL,
        INDEX idx_comments_public (status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->exec('CREATE TABLE IF NOT EXISTS finance_entries (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        flow ENUM("income", "expense") NOT NULL,
        payment_type ENUM("instant", "pending") NOT NULL,
        payment_method ENUM("cash", "card") NULL,
        amount_cents BIGINT UNSIGNED NOT NULL,
        remaining_cents BIGINT UNSIGNED NOT NULL,
        due_date DATE NULL,
        note VARCHAR(500) NOT NULL,
        status ENUM("completed", "pending", "partial", "cancelled") NOT NULL,
        created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
        INDEX idx_finance_entries_status (status, due_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->exec('CREATE TABLE IF NOT EXISTS finance_payments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        entry_id BIGINT UNSIGNED NOT NULL, payment_method ENUM("cash", "card") NOT NULL,
        amount_cents BIGINT UNSIGNED NOT NULL, note VARCHAR(500) NULL,
        paid_at DATETIME NOT NULL, created_at DATETIME NOT NULL,
        INDEX idx_finance_payments_entry (entry_id, paid_at),
        CONSTRAINT fk_finance_payments_entry FOREIGN KEY (entry_id) REFERENCES finance_entries(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->exec('CREATE TABLE IF NOT EXISTS finance_audit (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        entry_id BIGINT UNSIGNED NULL, action VARCHAR(60) NOT NULL, payload JSON NULL, created_at DATETIME NOT NULL,
        INDEX idx_finance_audit_entry (entry_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    return $pdo;
}
function cents(mixed $value): int {
    $value = trim(str_replace(['₺', ' '], '', (string) $value));
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);
    if (!is_numeric($value)) throw new InvalidArgumentException('Geçerli bir tutar girin.');
    $amount = (int) round((float) $value * 100);
    if ($amount <= 0 || $amount > 99999999999) throw new InvalidArgumentException('Tutar sıfırdan büyük olmalıdır.');
    return $amount;
}
function flash(string $type, string $text): void { $_SESSION['flash'] = ['type' => $type, 'text' => $text]; }
function redirect(string $tab = 'comments', string $section = ''): never { header('Location: admin.php?tab=' . rawurlencode($tab) . $section); exit; }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function requireCsrf(): void { if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) throw new RuntimeException('İstek doğrulanamadı. Sayfayı yenileyip tekrar deneyin.'); }
function audit(PDO $pdo, ?int $entryId, string $action, array $payload = []): void { $statement = $pdo->prepare('INSERT INTO finance_audit (entry_id, action, payload, created_at) VALUES (?, ?, ?, ?)'); $statement->execute([$entryId, $action, json_encode($payload, JSON_UNESCAPED_UNICODE), now()]); }
function dueCounts(PDO $pdo): array {
    $date = (new DateTimeImmutable('today', new DateTimeZone('Europe/Istanbul')))->format('Y-m-d');
    $until = (new DateTimeImmutable('+7 days', new DateTimeZone('Europe/Istanbul')))->format('Y-m-d');
    $statement = $pdo->prepare('SELECT flow, COUNT(*) AS total FROM finance_entries WHERE payment_type = "pending" AND status IN ("pending", "partial") AND remaining_cents > 0 AND due_date IS NOT NULL AND date(due_date) BETWEEN date(?) AND date(?) GROUP BY flow');
    $statement->execute([$date, $until]);
    $counts = ['income' => 0, 'expense' => 0];
    foreach ($statement->fetchAll() as $row) $counts[$row['flow']] = (int) $row['total'];
    $counts['all'] = $counts['income'] + $counts['expense'];
    return $counts;
}
function summary(PDO $pdo): array {
    $actual = $pdo->query('SELECT flow, payment_method, COALESCE(SUM(amount_cents), 0) total FROM (SELECT flow, payment_method, amount_cents FROM finance_entries WHERE payment_type = "instant" AND status = "completed" UNION ALL SELECT e.flow, p.payment_method, p.amount_cents FROM finance_payments p JOIN finance_entries e ON e.id = p.entry_id) AS realized GROUP BY flow, payment_method')->fetchAll();
    $cash = ['income' => 0, 'expense' => 0]; $cashByMethod = ['income' => ['cash' => 0, 'card' => 0], 'expense' => ['cash' => 0, 'card' => 0]];
    foreach ($actual as $row) { $cash[$row['flow']] += (int) $row['total']; $cashByMethod[$row['flow']][$row['payment_method']] = (int) $row['total']; }
    $month = (new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul')))->format('Y-m');
    $monthly = $pdo->prepare('SELECT flow, payment_method, COALESCE(SUM(amount_cents), 0) total FROM (SELECT flow, payment_method, amount_cents, created_at FROM finance_entries WHERE payment_type = "instant" AND status = "completed" UNION ALL SELECT e.flow, p.payment_method, p.amount_cents, p.paid_at AS created_at FROM finance_payments p JOIN finance_entries e ON e.id = p.entry_id) AS realized WHERE DATE_FORMAT(created_at, "%Y-%m") = ? GROUP BY flow, payment_method');
    $monthly->execute([$month]); $monthCash = ['income' => 0, 'expense' => 0]; $monthByMethod = ['income' => ['cash' => 0, 'card' => 0], 'expense' => ['cash' => 0, 'card' => 0]];
    foreach ($monthly->fetchAll() as $row) { $monthCash[$row['flow']] += (int) $row['total']; $monthByMethod[$row['flow']][$row['payment_method']] = (int) $row['total']; }
    $pending = $pdo->query('SELECT flow, COALESCE(SUM(remaining_cents), 0) total FROM finance_entries WHERE payment_type = "pending" AND status IN ("pending", "partial") GROUP BY flow')->fetchAll();
    $awaiting = ['income' => 0, 'expense' => 0]; foreach ($pending as $row) $awaiting[$row['flow']] = (int) $row['total'];
    return ['balance' => $cash['income'] - $cash['expense'], 'income' => $cash['income'], 'expense' => $cash['expense'], 'month_income' => $monthCash['income'], 'month_expense' => $monthCash['expense'], 'awaiting_income' => $awaiting['income'], 'awaiting_expense' => $awaiting['expense'], 'cash_by_method' => $cashByMethod, 'month_by_method' => $monthByMethod];
}
function ledger(PDO $pdo): array {
    $sql = 'SELECT created_at, flow, payment_method, amount_cents, note, "instant" AS entry_kind, id AS source_id FROM finance_entries WHERE payment_type = "instant" AND status = "completed" UNION ALL SELECT p.paid_at AS created_at, e.flow, p.payment_method, p.amount_cents, COALESCE(NULLIF(p.note, ""), e.note) AS note, "settlement" AS entry_kind, e.id AS source_id FROM finance_payments p JOIN finance_entries e ON e.id = p.entry_id ORDER BY created_at DESC';
    $rows = $pdo->query($sql)->fetchAll(); $months = [];
    foreach ($rows as $row) { $key = substr($row['created_at'], 0, 7); $months[$key][] = $row; }
    return $months;
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name(PIONEER_ADMIN_SESSION);
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => $https]);
session_start();
header('X-Frame-Options: DENY'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: no-referrer'); header('Cache-Control: no-store, max-age=0');

if (isset($_POST['login'])) {
    $attempts = (int) ($_SESSION['login_attempts'] ?? 0);
    $cooldown = (int) ($_SESSION['login_cooldown'] ?? 0);
    if ($cooldown > time()) flash('error', 'Çok fazla deneme yapıldı. Lütfen biraz sonra tekrar deneyin.');
    elseif (hash_equals(PIONEER_ADMIN_USER, trim((string) ($_POST['username'] ?? ''))) && password_verify((string) ($_POST['password'] ?? ''), PIONEER_ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true); $_SESSION['authenticated'] = true; $_SESSION['login_attempts'] = 0; $_SESSION['login_cooldown'] = 0; csrf(); redirect();
    } else { $_SESSION['login_attempts'] = $attempts + 1; if ($attempts >= 4) $_SESSION['login_cooldown'] = time() + 300; flash('error', 'Kullanıcı adı veya parola hatalı.'); }
    redirect('login');
}
if (isset($_POST['logout'])) { $_SESSION = []; session_destroy(); header('Location: admin.php'); exit; }
$authenticated = !empty($_SESSION['authenticated']);
$tab = $_GET['tab'] ?? 'comments';
if (!in_array($tab, ['comments', 'finance', 'payables', 'receivables'], true)) $tab = 'comments';

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireCsrf(); $pdo = db(); $action = $_POST['action'] ?? '';
        if ($action === 'comment_review') {
            $id = filter_var($_POST['comment_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); $status = $_POST['status'] ?? '';
            if (!$id || !in_array($status, ['approved', 'rejected'], true)) throw new InvalidArgumentException('Yorum işlemi geçersiz.');
            $update = $pdo->prepare('UPDATE comments SET status = ?, reviewed_at = ? WHERE id = ? AND status = "pending"');
            $update->execute([$status, now(), $id]); flash('success', $status === 'approved' ? 'Yorum onaylandı ve yayın akışına alındı.' : 'Yorum reddedildi.'); redirect('comments');
        }
        if ($action === 'comment_reply') {
            $id = filter_var($_POST['comment_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); $reply = trim((string) ($_POST['admin_reply'] ?? ''));
            if (!$id || $reply === '' || mb_strlen($reply) > 1200) throw new InvalidArgumentException('İşletme yanıtı 1 ile 1200 karakter arasında olmalıdır.');
            $update = $pdo->prepare('UPDATE comments SET admin_reply = ?, replied_at = ? WHERE id = ? AND status = "approved"');
            $update->execute([$reply, now(), $id]);
            if (!$update->rowCount()) throw new InvalidArgumentException('Yanıt yalnızca yayındaki yorumlara verilebilir.');
            flash('success', 'İşletme yanıtı güncellendi.'); redirect('comments');
        }
        if ($action === 'comment_delete') {
            $id = filter_var($_POST['comment_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); if (!$id) throw new InvalidArgumentException('Yorum bulunamadı.');
            $pdo->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]); flash('success', 'Yorum kalıcı olarak silindi.'); redirect('comments');
        }
        if ($action === 'finance_create') {
            $flow = $_POST['flow'] ?? ''; $paymentType = $_POST['payment_type'] ?? ''; $paymentMethod = $_POST['payment_method'] ?? ''; $amount = cents($_POST['amount'] ?? ''); $note = trim((string) ($_POST['note'] ?? '')); $due = trim((string) ($_POST['due_date'] ?? ''));
            if (!in_array($flow, ['income', 'expense'], true) || !in_array($paymentType, ['instant', 'pending'], true)) throw new InvalidArgumentException('İşlem türünü seçin.');
            if ($paymentType === 'instant' && !in_array($paymentMethod, ['cash', 'card'], true)) throw new InvalidArgumentException('Anlık işlem için nakit veya kart seçin.');
            if ($note === '' || mb_strlen($note) > 500) throw new InvalidArgumentException('Finans notu zorunludur ve en fazla 500 karakter olabilir.');
            if ($paymentType === 'pending' && $due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) throw new InvalidArgumentException('Vade tarihi geçerli bir tarih olmalıdır.');
            $status = $paymentType === 'instant' ? 'completed' : 'pending'; $time = now();
            $insert = $pdo->prepare('INSERT INTO finance_entries (flow, payment_type, payment_method, amount_cents, remaining_cents, due_date, note, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([$flow, $paymentType, $paymentType === 'instant' ? $paymentMethod : null, $amount, $paymentType === 'pending' ? $amount : 0, $paymentType === 'pending' && $due !== '' ? $due : null, $note, $status, $time, $time]);
            $id = (int) $pdo->lastInsertId(); audit($pdo, $id, 'created', compact('flow', 'paymentType', 'paymentMethod', 'amount', 'due', 'note'));
            flash('success', $paymentType === 'instant' ? 'Anlık finans hareketi kaydedildi.' : 'Bekleyen ödeme oluşturuldu.'); redirect('finance');
        }
        if ($action === 'finance_edit') {
            $id = filter_var($_POST['entry_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); $amount = cents($_POST['amount'] ?? ''); $note = trim((string) ($_POST['note'] ?? '')); $due = trim((string) ($_POST['due_date'] ?? ''));
            if (!$id || $note === '' || mb_strlen($note) > 500) throw new InvalidArgumentException('İşlem bilgilerini kontrol edin.');
            $get = $pdo->prepare('SELECT * FROM finance_entries WHERE id = ? AND payment_type = "pending"'); $get->execute([$id]); $entry = $get->fetch(); if (!$entry) throw new InvalidArgumentException('Bekleyen ödeme bulunamadı.');
            $paid = (int) $entry['amount_cents'] - (int) $entry['remaining_cents']; if ($amount < $paid) throw new InvalidArgumentException('Toplam tutar, daha önce işlenen ödemeden düşük olamaz.');
            if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) throw new InvalidArgumentException('Vade tarihi geçerli bir tarih olmalıdır.');
            $remaining = $amount - $paid; $status = $remaining === 0 ? 'completed' : ($paid > 0 ? 'partial' : 'pending');
            $pdo->prepare('UPDATE finance_entries SET amount_cents = ?, remaining_cents = ?, note = ?, due_date = ?, status = ?, updated_at = ? WHERE id = ?')->execute([$amount, $remaining, $note, $due ?: null, $status, now(), $id]); audit($pdo, $id, 'edited', compact('amount', 'remaining', 'note', 'due'));
            flash('success', 'Bekleyen ödeme güncellendi.'); redirect($entry['flow'] === 'income' ? 'receivables' : 'payables');
        }
        if ($action === 'finance_settle') {
            $id = filter_var($_POST['entry_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); $paymentMethod = $_POST['payment_method'] ?? ''; $amount = cents($_POST['amount'] ?? ''); $note = trim((string) ($_POST['note'] ?? ''));
            if (!$id) throw new InvalidArgumentException('Bekleyen ödeme bulunamadı.');
            if (!in_array($paymentMethod, ['cash', 'card'], true)) throw new InvalidArgumentException('Ödeme yöntemi olarak nakit veya kart seçin.');
            $pdo->beginTransaction(); $get = $pdo->prepare('SELECT * FROM finance_entries WHERE id = ? AND payment_type = "pending" AND status IN ("pending", "partial")'); $get->execute([$id]); $entry = $get->fetch();
            if (!$entry) { $pdo->rollBack(); throw new InvalidArgumentException('Bu ödeme zaten kapatılmış veya bulunamadı.'); }
            if ($amount > (int) $entry['remaining_cents']) { $pdo->rollBack(); throw new InvalidArgumentException('İşlenecek tutar kalan bakiyeden yüksek olamaz.'); }
            $remaining = (int) $entry['remaining_cents'] - $amount; $status = $remaining === 0 ? 'completed' : 'partial'; $time = now();
            $pdo->prepare('INSERT INTO finance_payments (entry_id, payment_method, amount_cents, note, paid_at, created_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$id, $paymentMethod, $amount, $note ?: null, $time, $time]);
            $pdo->prepare('UPDATE finance_entries SET remaining_cents = ?, status = ?, updated_at = ? WHERE id = ?')->execute([$remaining, $status, $time, $id]); audit($pdo, $id, 'settled', compact('amount', 'remaining', 'note')); $pdo->commit();
            flash('success', $entry['flow'] === 'income' ? 'Tahsilat işlendi; özet kartları güncellendi.' : 'Ödeme işlendi; özet kartları güncellendi.'); redirect($entry['flow'] === 'income' ? 'receivables' : 'payables');
        }
        throw new InvalidArgumentException('İşlem bulunamadı.');
    } catch (Throwable $exception) { if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack(); flash('error', $exception instanceof InvalidArgumentException || $exception instanceof RuntimeException ? $exception->getMessage() : 'İşlem kaydedilemedi.'); redirect($tab); }
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$pdo = null; $counts = ['income' => 0, 'expense' => 0, 'all' => 0]; $cards = []; $comments = []; $pendingEntries = []; $ledger = [];
if ($authenticated) { try { $pdo = db(); $counts = dueCounts($pdo); $cards = summary($pdo); if ($tab === 'comments') $comments = $pdo->query('SELECT * FROM comments ORDER BY CASE status WHEN "pending" THEN 0 WHEN "approved" THEN 1 ELSE 2 END, datetime(created_at) DESC')->fetchAll(); if (in_array($tab, ['payables', 'receivables'], true)) { $flow = $tab === 'payables' ? 'expense' : 'income'; $statement = $pdo->prepare('SELECT * FROM finance_entries WHERE payment_type = "pending" AND status IN ("pending", "partial") AND flow = ? ORDER BY CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, date(due_date), datetime(created_at) DESC'); $statement->execute([$flow]); $pendingEntries = $statement->fetchAll(); } if ($tab === 'finance') $ledger = ledger($pdo); } catch (Throwable $exception) { $flash = ['type' => 'error', 'text' => 'Veritabanı başlatılamadı.']; } }
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Pioneer Yönetim Paneli</title><link rel="stylesheet" href="admin-v3.css"></head><body class="admin-body <?= $authenticated ? 'is-authenticated' : 'is-guest' ?>">
<?php if (!$authenticated): ?><main class="login-screen"><section class="login-panel"><a class="admin-mark" href="index.html" aria-label="Pioneer ana sayfa"><img src="https://cdn.lavira360.com/pioneer/logo.png" alt="Pioneer"></a><p class="admin-eyebrow">Özel yönetim alanı</p><h1>İşletmenizin<br><em>kontrol merkezi.</em></h1><p>Yorumları inceleyin, finansal hareketleri yönetin ve yaklaşan ödemeleri tek noktadan takip edin.</p><?php if ($flash): ?><p class="form-alert form-alert--<?= e($flash['type']) ?>"><?= e($flash['text']) ?></p><?php endif; ?><form method="post" class="login-form"><label>Kullanıcı adı<input name="username" autocomplete="username" required></label><label>Parola<input name="password" type="password" autocomplete="current-password" required></label><button class="admin-button" name="login" value="1">Güvenli giriş <span>↗</span></button></form><a href="index.html" class="back-link">← Web sitesine dön</a></section><aside class="login-aside"><div><small>Pioneer yönetim sistemi</small><p>Yorumlar, nakit akışı ve bekleyen ödemeler her güncel işlemden sonra yeniden hesaplanır.</p></div></aside></main><?php else: ?>
<div class="admin-app"><aside class="admin-sidebar"><a class="admin-mark" href="index.html" aria-label="Pioneer ana sayfa"><img src="https://cdn.lavira360.com/pioneer/logo.png" alt="Pioneer"></a><nav aria-label="Yönetim menüsü"><a class="<?= $tab === 'comments' ? 'is-active' : '' ?>" href="admin.php?tab=comments"><i>01</i>Yorumlar</a><a class="<?= $tab === 'finance' ? 'is-active' : '' ?>" href="admin.php?tab=finance"><i>02</i>Finans</a><div class="nav-group"><p>Bekleyen ödemeler <b class="nav-badge <?= $counts['all'] ? '' : 'is-empty' ?>"><?= $counts['all'] ?></b></p><a class="<?= $tab === 'payables' ? 'is-active' : '' ?>" href="admin.php?tab=payables">Ödeme yapılacaklar <b class="nav-badge <?= $counts['expense'] ? '' : 'is-empty' ?>"><?= $counts['expense'] ?></b></a><a class="<?= $tab === 'receivables' ? 'is-active' : '' ?>" href="admin.php?tab=receivables">Ödeme alınacaklar <b class="nav-badge <?= $counts['income'] ? '' : 'is-empty' ?>"><?= $counts['income'] ?></b></a></div></nav><form method="post" class="sidebar-footer"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><button name="logout" value="1">Oturumu kapat <span>→</span></button></form></aside><main class="admin-main"><header class="admin-topbar"><div><p class="admin-eyebrow">Pioneer · işletme yönetimi</p><h1><?php if ($tab === 'comments'): ?>Yorum <em>merkezi.</em><?php elseif ($tab === 'finance'): ?>Finans <em>özeti.</em><?php elseif ($tab === 'payables'): ?>Ödeme <em>yapılacaklar.</em><?php else: ?>Ödeme <em>alınacaklar.</em><?php endif; ?></h1></div><div class="topbar-user"><span>pioneer.admin</span><b><?= date('d.m.Y') ?></b></div></header><?php if ($flash): ?><p class="form-alert form-alert--<?= e($flash['type']) ?>"><?= e($flash['text']) ?></p><?php endif; ?>
<?php if ($tab === 'comments'): ?><section class="comment-admin-list"><div class="section-intro"><p>Yeni yorumlar önce burada görünür. İnceleme tamamlandıktan sonra yayındaki yorumlara işletme yanıtı ekleyebilirsiniz.</p><span><?= count(array_filter($comments, fn($comment) => $comment['status'] === 'pending')) ?> yeni inceleme</span></div><?php if (!$comments): ?><div class="empty-state">Henüz inceleme kuyruğunda yorum bulunmuyor.</div><?php endif; ?><?php foreach ($comments as $comment): ?><article class="comment-admin-card"><div class="comment-admin-card__head"><div><span class="status status--<?= e($comment['status']) ?>"><?= $comment['status'] === 'pending' ? 'İnceleme bekliyor' : ($comment['status'] === 'approved' ? 'Yayında' : 'Reddedildi') ?></span><h2><?= e($comment['full_name']) ?></h2><p><?= e($comment['location'] ?: 'Bölge belirtilmedi') ?> · <?= (int) $comment['rating'] ?>/5 · <?= e($comment['created_at']) ?></p></div><a href="mailto:<?= e($comment['email']) ?>">E-posta ↗</a></div><p class="comment-body"><?= nl2br(e($comment['body'])) ?></p><?php if ($comment['image_filename']): ?><p class="image-info">Görsel durumu: <?= e($comment['image_status']) ?> · <?= e($comment['image_filename']) ?></p><?php endif; ?><?php if ($comment['status'] === 'pending'): ?><form method="post" class="comment-action-form"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="comment_review"><input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>"><div class="form-actions"><button class="admin-button" name="status" value="approved">Onayla ve yayınla</button><button class="plain-button" name="status" value="rejected">Reddet</button></div></form><?php elseif ($comment['status'] === 'approved'): ?><form method="post" class="comment-action-form"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="comment_reply"><input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>"><label>İşletme yanıtı <textarea name="admin_reply" maxlength="1200" required placeholder="Müşteriye herkese açık olarak yanıt verin."><?= e($comment['admin_reply'] ?? '') ?></textarea></label><div class="form-actions"><button class="admin-button">Yanıtı yayınla</button></div></form><?php endif; ?><form method="post" class="delete-form" data-confirm="Bu yorumu kalıcı olarak silmek istediğinizden emin misiniz?"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="comment_delete"><input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>"><button>Yorumu sil</button></form></article><?php endforeach; ?></section>
<?php elseif ($tab === 'finance'): ?><section class="finance-cards"><article><small>Net kasa</small><strong><?= money($cards['balance']) ?></strong><span>Nakit net: <?= money($cards['cash_by_method']['income']['cash'] - $cards['cash_by_method']['expense']['cash']) ?> · Kart net: <?= money($cards['cash_by_method']['income']['card'] - $cards['cash_by_method']['expense']['card']) ?></span></article><article class="income"><small>Bu ay gelir</small><strong><?= money($cards['month_income']) ?></strong><span>Nakit: <?= money($cards['month_by_method']['income']['cash']) ?> · Kart: <?= money($cards['month_by_method']['income']['card']) ?></span></article><article class="expense"><small>Bu ay gider</small><strong><?= money($cards['month_expense']) ?></strong><span>Nakit: <?= money($cards['month_by_method']['expense']['cash']) ?> · Kart: <?= money($cards['month_by_method']['expense']['card']) ?></span></article><article class="pending"><small>Bekleyen net hareket</small><strong><?= money($cards['awaiting_income'] - $cards['awaiting_expense']) ?></strong><span><?= money($cards['awaiting_income']) ?> alınacak · <?= money($cards['awaiting_expense']) ?> ödenecek</span></article></section><section class="finance-layout"><form method="post" class="finance-form"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="finance_create"><p class="admin-eyebrow">Yeni kayıt</p><h2>Para hareketi ekle</h2><div class="choice-grid" data-choice-group><label><input type="radio" name="payment_type" value="instant" checked><span>Anlık ödeme</span><small>Kasaya hemen girer/çıkar.</small></label><label><input type="radio" name="payment_type" value="pending"><span>Bekleyen ödeme</span><small>Parçalı tahsilat/ödeme için.</small></label></div><div class="choice-grid choice-grid--flow"><label><input type="radio" name="flow" value="income" checked><span>Gelir</span></label><label><input type="radio" name="flow" value="expense"><span>Gider</span></label></div><label class="payment-method-field">Ödeme yöntemi<select name="payment_method"><option value="cash">Nakit</option><option value="card">Kart</option></select></label><label>Miktar<input name="amount" inputmode="decimal" placeholder="Örn. 25.000,00" required></label><label>Finans notu<textarea name="note" maxlength="500" required placeholder="İşlemin ne için olduğunu açıkça yazın."></textarea></label><label class="due-date-field" hidden>Beklenen tarih <small>Boş bırakılırsa tarih belirtilmedi olarak kaydedilir.</small><input name="due_date" type="date"></label><button class="admin-button" type="submit">Hareketi kaydet <span>↗</span></button></form><section class="ledger"><div class="ledger-head"><div><p class="admin-eyebrow">Hareket defteri</p><h2>Aylık kayıtlar</h2></div><p>Yeni aydan eski aya sıralanır. Bekleyen kayıtlar, gerçekleşene kadar bu deftere yazılmaz.</p></div><?php if (!$ledger): ?><div class="empty-state">Henüz gerçekleşen finans hareketi bulunmuyor.</div><?php endif; ?><?php foreach ($ledger as $month => $rows): ?><details class="ledger-month" open><summary><span><?= e(monthLabel($month)) ?></span><b><?= count($rows) ?> işlem <i>+</i></b></summary><div class="ledger-table"><div class="ledger-row ledger-row--header"><span>Tarih</span><span>İşlem / not</span><span>Tür</span><span>Tutar</span></div><?php foreach ($rows as $row): ?><div class="ledger-row"><span><?= e(date('d.m.Y', strtotime($row['created_at']))) ?></span><span><strong><?= e($row['note']) ?></strong><small><?= $row['entry_kind'] === 'settlement' ? 'Bekleyen işlem tahsilatı / ödemesi' : 'Anlık finans hareketi' ?> · <?= $row['payment_method'] === 'card' ? 'Kart' : 'Nakit' ?></small></span><span class="flow flow--<?= e($row['flow']) ?>"><?= $row['flow'] === 'income' ? 'Gelir' : 'Gider' ?></span><span class="amount amount--<?= e($row['flow']) ?>"><?= ($row['flow'] === 'income' ? '+' : '−') . money((int) $row['amount_cents']) ?></span></div><?php endforeach; ?></div></details><?php endforeach; ?></section></section>
<?php else: $isIncome = $tab === 'receivables'; ?><section class="pending-page"><div class="pending-page__intro"><p class="admin-eyebrow">Bekleyen ödeme yönetimi</p><h2><?= $isIncome ? 'Tahsil edilecek' : 'Ödenecek' ?> <em>kayıtlar.</em></h2><p>Her ödeme işleminde kalan tutar otomatik hesaplanır. Vadesine 7 gün veya daha az kalan kayıtlar uyarı işaretiyle öne çıkar.</p></div><?php if (!$pendingEntries): ?><div class="empty-state">Bu alanda açık bekleyen ödeme bulunmuyor.</div><?php endif; ?><div class="pending-list"><?php foreach ($pendingEntries as $entry): $today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Istanbul')))->format('Y-m-d'); $withinWeek = (new DateTimeImmutable('+7 days', new DateTimeZone('Europe/Istanbul')))->format('Y-m-d'); $near = $entry['due_date'] && $entry['due_date'] >= $today && $entry['due_date'] <= $withinWeek; ?><article class="pending-card <?= $near ? 'is-near' : '' ?>"><div class="pending-card__summary"><span class="status status--<?= $isIncome ? 'approved' : 'rejected' ?>"><?= $isIncome ? 'Alınacak' : 'Yapılacak' ?></span><h3><?= e($entry['note']) ?></h3><p>Toplam <?= money((int) $entry['amount_cents']) ?> · Kalan <strong><?= money((int) $entry['remaining_cents']) ?></strong></p><div class="progress"><span style="width: <?= max(0, min(100, (((int) $entry['amount_cents'] - (int) $entry['remaining_cents']) / (int) $entry['amount_cents']) * 100)) ?>%"></span></div><small><?= $entry['due_date'] ? 'Vade: ' . e(date('d.m.Y', strtotime($entry['due_date']))) : 'Tarih belirtilmedi' ?><?= $near ? ' · Yaklaşan vade' : '' ?></small></div><div class="pending-card__actions"><details><summary>Düzenle</summary><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="finance_edit"><input type="hidden" name="entry_id" value="<?= (int) $entry['id'] ?>"><label>Toplam tutar<input name="amount" value="<?= e(number_format((int) $entry['amount_cents'] / 100, 2, ',', '.')) ?>" required></label><label>Finans notu<textarea name="note" required><?= e($entry['note']) ?></textarea></label><label>Beklenen tarih <small>Boş bırakılabilir.</small><input name="due_date" type="date" value="<?= e($entry['due_date']) ?>"></label><button class="plain-button">Kaydı güncelle</button></form></details><details><summary><?= $isIncome ? 'Ödeme al' : 'Ödeme yap' ?></summary><form method="post" class="inline-form settle-form" data-remaining="<?= (int) $entry['remaining_cents'] ?>"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="finance_settle"><input type="hidden" name="entry_id" value="<?= (int) $entry['id'] ?>"><p>Kalan tutar: <strong><?= money((int) $entry['remaining_cents']) ?></strong></p><label>Ödeme yöntemi<select name="payment_method" required><option value="cash">Nakit</option><option value="card">Kart</option></select></label><label>İşlenecek miktar<input name="amount" inputmode="decimal" required placeholder="0,00"></label><label>Güncel işlem notu <textarea name="note" maxlength="500" placeholder="İsteğe bağlı ödeme notu"></textarea></label><button class="admin-button"><?= $isIncome ? 'Tahsilatı kaydet' : 'Ödemeyi kaydet' ?></button></form></details></div></article><?php endforeach; ?></div></section><?php endif; ?></main></div><?php endif; ?><script src="admin-v2.js"></script></body></html>
