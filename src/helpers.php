<?php

declare(strict_types=1);

function redirect(string $target = '?'): never
{
    header('Location: ' . $target);
    exit;
}

function flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function pullFlash(): ?string
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function currentUser(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function requireUser(PDO $pdo): array
{
    $user = currentUser($pdo);
    if ($user === null) {
        throw new RuntimeException('Please sign in to continue.');
    }

    return $user;
}

function isAdminUser(?array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

function userCanManage(?array $user, array $row, string $ownerCol = 'created_by_user_id'): bool
{
    if ($user === null) {
        return false;
    }
    if (isAdminUser($user)) {
        return true;
    }
    $owner = (int) ($row[$ownerCol] ?? 0);
    return $owner > 0 && $owner === (int) ($user['id'] ?? 0);
}

function createNotification(PDO $pdo, int $userId, string $type, string $message, string $link = ''): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, message, link)
         VALUES (:user_id, :type, :message, :link)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'type' => $type,
        'message' => $message,
        'link' => $link,
    ]);
}

function ensureVisitorToken(): string
{
    $cookieName = 'palive_visitor';
    if (!empty($_COOKIE[$cookieName]) && preg_match('/^[a-f0-9]{64}$/', (string) $_COOKIE[$cookieName])) {
        return (string) $_COOKIE[$cookieName];
    }

    $token = hash('sha256', bin2hex(random_bytes(32)));
    setcookie($cookieName, $token, [
        'expires' => time() + (86400 * 365),
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[$cookieName] = $token;

    return $token;
}

function logVisitor(PDO $pdo, string $visitorToken, string $page, ?array $currentUser): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO visitor_logs (visitor_token, page, user_id, ip_address, user_agent)
         VALUES (:visitor_token, :page, :user_id, :ip_address, :user_agent)'
    );
    $stmt->execute([
        'visitor_token' => $visitorToken,
        'page' => substr($page, 0, 80),
        'user_id' => $currentUser['id'] ?? null,
        'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
}

function normalizeDateTimeLocal(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
    if (!$date) {
        throw new RuntimeException('Invalid event date or time.');
    }

    return $date->format('Y-m-d H:i:s');
}

function normalizeOptionalUrl(mixed $value): ?string
{
    $url = trim((string) $value);
    if ($url === '') {
        return null;
    }

    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new RuntimeException('Invalid website URL.');
    }

    return $url;
}

function normalizeTime(string $value): string
{
    $time = DateTimeImmutable::createFromFormat('H:i', $value);
    if (!$time) {
        throw new RuntimeException('Invalid time value.');
    }

    return $time->format('H:i:s');
}

function calendarDayCell(DateTimeImmutable $day): array
{
    return [
        'key' => $day->format('Y-m-d'),
        'weekday' => $day->format('D'),
        'label' => $day->format('n/j'),
        'day_number' => $day->format('j'),
    ];
}

function eventSortSql(string $sort, string $today): string
{
    // $today is a local 'Y-m-d' literal so today's events are always grouped with
    // upcoming (the DB clock may be in a different timezone than the app).
    return match ($sort) {
        'oldest' => 'e.starts_at ASC',
        'newest' => 'e.starts_at DESC',
        'past' => 'e.starts_at DESC',
        default => "CASE WHEN DATE(e.starts_at) >= '" . $today . "' THEN 0 ELSE 1 END, e.starts_at ASC",
    };
}

function queryWith(array $overrides): string
{
    $query = array_merge($_GET, $overrides);
    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        }
    }

    return '?' . http_build_query($query);
}

function showMoreLink(int $total, int $shown, string $anchor = ''): string
{
    if ($total <= $shown) {
        return '';
    }

    $remaining = $total - $shown;
    $href = queryWith(['show' => $shown + 20]) . ($anchor !== '' ? '#' . $anchor : '');

    return '<div class="toolbar"><a class="show-more" href="'
        . h($href)
        . '">Show more (' . $remaining . ' more)</a></div>';
}

function isNavActive(string $section, string $page): bool
{
    return match ($section) {
        'home' => $page === 'dashboard',
        'events' => $page === 'events',
        'news' => $page === 'news',
        'schools' => in_array($page, ['schools', 'school'], true),
        'resources' => $page === 'resources',
        'deals' => $page === 'deals',
        'community' => in_array($page, ['discussions', 'messages', 'teams', 'marketplace'], true),
        'admin' => $page === 'admin',
        default => false,
    };
}

function formatEventDateRange(string $startsAt, string $endsAt): string
{
    $start = new DateTimeImmutable($startsAt);
    $end = new DateTimeImmutable($endsAt);

    if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
        return sprintf(
            '%s, %s to %s',
            $start->format('M j, Y'),
            $start->format('g:i A'),
            $end->format('g:i A')
        );
    }

    return sprintf(
        '%s to %s',
        $start->format('M j, Y g:i A'),
        $end->format('M j, Y g:i A')
    );
}

function formatEventDay(string $startsAt): string
{
    return (new DateTimeImmutable($startsAt))->format('D');
}

function formatEventDate(string $startsAt): string
{
    return (new DateTimeImmutable($startsAt))->format('M j');
}

function formatEventGroupLabel(string $dayKey): string
{
    if ($dayKey === date('Y-m-d')) {
        return 'Today';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $dayKey);
    return $date ? $date->format('l, n/j') : $dayKey;
}

function eventFormFields(array $event, array $teamOptions, array $categories = []): void
{
    $val = static fn(string $key): string => h((string) ($event[$key] ?? ''));
    $dt = static fn(?string $s): string => $s ? h((new DateTimeImmutable($s))->format('Y-m-d\TH:i')) : '';
    $currentCat = (int) ($event['category_id'] ?? 0);
    ?>
    <input name="title" placeholder="Event title" value="<?= $val('title') ?>" required>
    <select name="category_id" required>
        <option value="">Select type</option>
        <?php foreach ($categories as $categoryOption): ?>
            <option value="<?= (int) $categoryOption['id'] ?>"<?= $currentCat === (int) $categoryOption['id'] ? ' selected' : '' ?>><?= h($categoryOption['label']) ?></option>
        <?php endforeach; ?>
    </select>
    <input name="venue" placeholder="Venue" value="<?= $val('venue') ?>" required>
    <input name="address" placeholder="Address" value="<?= $val('address') ?>" required>
    <label class="field"><span>Starts</span><input type="datetime-local" name="starts_at" value="<?= $dt($event['starts_at'] ?? null) ?>" required></label>
    <label class="field"><span>Ends</span><input type="datetime-local" name="ends_at" value="<?= $dt($event['ends_at'] ?? null) ?>" required></label>
    <input name="website" placeholder="Website (optional)" value="<?= $val('website') ?>">
    <textarea name="description" rows="3" placeholder="Description" required><?= $val('description') ?></textarea>
    <select name="team_id">
        <option value="">No team</option>
        <?php foreach ($teamOptions as $teamOption): ?>
            <option value="<?= (int) $teamOption['id'] ?>"<?= (int) ($event['team_id'] ?? 0) === (int) $teamOption['id'] ? ' selected' : '' ?>><?= h($teamOption['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php
}

function dealFormFields(array $deal, array $categories = []): void
{
    $val = static fn(string $key): string => h((string) ($deal[$key] ?? ''));
    $dt = static fn(?string $s): string => $s ? h((new DateTimeImmutable($s))->format('Y-m-d\TH:i')) : '';
    $currentCat = (int) ($deal['category_id'] ?? 0);
    ?>
    <input name="title" placeholder="Deal title" value="<?= $val('title') ?>" required>
    <input name="business_name" placeholder="Business name" value="<?= $val('business_name') ?>" required>
    <select name="category_id" required>
        <option value="">Select category</option>
        <?php foreach ($categories as $categoryOption): ?>
            <option value="<?= (int) $categoryOption['id'] ?>"<?= $currentCat === (int) $categoryOption['id'] ? ' selected' : '' ?>><?= h($categoryOption['label']) ?></option>
        <?php endforeach; ?>
    </select>
    <input name="website" placeholder="Website (optional)" value="<?= $val('website') ?>">
    <textarea name="description" rows="3" placeholder="Description" required><?= $val('description') ?></textarea>
    <label class="field"><span>Expires</span><input type="datetime-local" name="expires_at" value="<?= $dt($deal['expires_at'] ?? null) ?>" required></label>
    <?php
}

function renderEventPanel(array $event, int $focusEvent, int $currentUserId = 0, array $teamOptions = [], array $categories = [], bool $isAdmin = false): void
{
    $isPast = strtotime((string) $event['starts_at']) <= time();
    $recLabel = recurrenceLabel((string) ($event['recurrence'] ?? 'none'));
    $canManage = $isAdmin || ($currentUserId > 0 && (int) ($event['created_by_user_id'] ?? 0) === $currentUserId);
    ?>
    <article class="panel event-panel<?= $isPast ? ' is-past' : '' ?><?= $canManage ? ' has-actions' : '' ?>" id="event-<?= (int) $event['id'] ?>">
        <?php if ($canManage): ?>
            <div class="card-actions">
                <label for="edit-event-<?= (int) $event['id'] ?>" class="mini-btn">Edit</label>
                <form method="post" class="card-actions-del" onsubmit="return confirm('Delete this event?');">
                    <input type="hidden" name="action" value="delete_event">
                    <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                    <button type="submit" class="mini-btn mini-danger">Delete</button>
                </form>
            </div>
        <?php endif; ?>
        <details class="event-details"<?= $focusEvent === (int) $event['id'] ? ' open' : '' ?>>
            <summary class="event-summary disclosure-summary">
                <div class="disclosure-label">
                    <div class="inline event-title-row">
                        <strong><?= h($event['title']) ?></strong>
                        <span class="badge event-type-badge"><?= h($event['category']) ?></span>
                    </div>
                    <div class="meta"><strong><?= h(formatEventDateRange($event['starts_at'], $event['ends_at'])) ?></strong></div>
                </div>
                <span class="disclosure-arrow">›</span>
            </summary>
            <div class="event-body">
                <div class="inline">
                    <span class="badge"><?= h((new DateTimeImmutable($event['starts_at']))->format('g:i A')) ?></span>
                    <?php if ($recLabel !== ''): ?>
                        <span class="badge badge-recurring">↻ <?= h($recLabel) ?><?= !empty($event['recurrence_until']) ? ' until ' . h((new DateTimeImmutable($event['recurrence_until']))->format('n/j/Y')) : '' ?></span>
                    <?php endif; ?>
                </div>
                <div class="meta"><?= h($event['venue']) ?>, <?= h($event['address']) ?></div>
                <p><?= h($event['description']) ?></p>
                <?php if (!empty($event['website'])): ?>
                    <div class="meta"><a href="<?= h($event['website']) ?>" target="_blank" rel="noreferrer">Website</a></div>
                <?php endif; ?>
            </div>
        </details>
    </article>
    <?php if ($canManage): ?>
        <input type="checkbox" id="edit-event-<?= (int) $event['id'] ?>" class="modal-toggle" hidden>
        <div class="modal-backdrop">
            <div class="modal">
                <div class="modal-head">
                    <h2>Edit Event</h2>
                    <label for="edit-event-<?= (int) $event['id'] ?>" class="modal-x" aria-label="Close">×</label>
                </div>
                <form method="post" class="stack">
                    <input type="hidden" name="action" value="update_event">
                    <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                    <?php eventFormFields($event, $teamOptions, $categories); ?>
                    <div class="modal-actions">
                        <label for="edit-event-<?= (int) $event['id'] ?>" class="btn-secondary">Cancel</label>
                        <button type="submit">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <?php
}

function recurrenceLabel(string $recurrence): string
{
    return match ($recurrence) {
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'biweekly' => 'Every 2 weeks',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        default => '',
    };
}

/**
 * Returns the Y-m-d dates on which an event occurs within [rangeStart, rangeEnd].
 * Honors the full recurrence enum (none/daily/weekly/biweekly/monthly/yearly) and
 * stops at recurrence_until when set. Monthly/yearly anchor on the original
 * day-of-month, clamped to the length of each target month.
 */
function eventOccurrenceDates(array $event, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
{
    $start = (new DateTimeImmutable($event['starts_at']))->setTime(0, 0);
    $recurrence = (string) ($event['recurrence'] ?? 'none');

    $until = !empty($event['recurrence_until'])
        ? (new DateTimeImmutable($event['recurrence_until']))->setTime(0, 0)
        : null;
    $hardEnd = ($until !== null && $until < $rangeEnd) ? $until : $rangeEnd;

    // Fixed day-based intervals.
    $stepDays = match ($recurrence) {
        'daily' => 1,
        'weekly' => 7,
        'biweekly' => 14,
        default => 0,
    };

    if ($stepDays > 0) {
        $cursor = $start;
        if ($cursor < $rangeStart) {
            $skip = intdiv((int) $cursor->diff($rangeStart)->days, $stepDays) * $stepDays;
            $cursor = $cursor->modify('+' . $skip . ' days');
            while ($cursor < $rangeStart) {
                $cursor = $cursor->modify('+' . $stepDays . ' days');
            }
        }

        $dates = [];
        while ($cursor <= $hardEnd) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+' . $stepDays . ' days');
        }

        return $dates;
    }

    // Month-based intervals (clamp day-of-month to each month's length).
    $stepMonths = match ($recurrence) {
        'monthly' => 1,
        'yearly' => 12,
        default => 0,
    };

    if ($stepMonths > 0) {
        $day = (int) $start->format('j');
        $monthStart = $start->modify('first day of this month');

        $dates = [];
        for ($n = 0; $n < 6000; $n++) {
            $month = $monthStart->modify('+' . ($n * $stepMonths) . ' months');
            $occ = $month->setDate(
                (int) $month->format('Y'),
                (int) $month->format('n'),
                min($day, (int) $month->format('t'))
            );
            if ($occ > $hardEnd) {
                break;
            }
            if ($occ >= $rangeStart) {
                $dates[] = $occ->format('Y-m-d');
            }
        }

        return $dates;
    }

    // One-off (none / unknown value).
    return ($start >= $rangeStart && $start <= $hardEnd) ? [$start->format('Y-m-d')] : [];
}

function buildEventCalendar(array $events, string $scale, string $anchor): array
{
    $base = DateTimeImmutable::createFromFormat('Y-m-d', $anchor) ?: new DateTimeImmutable('today');
    $base = $base->setTime(0, 0);

    if ($scale === 'day') {
        $days = [calendarDayCell($base)];
        $meta = [
            'scale' => 'day',
            'label' => $base->format('l, F j, Y'),
            'previous' => $base->modify('-1 day')->format('Y-m-d'),
            'next' => $base->modify('+1 day')->format('Y-m-d'),
        ];
    } elseif ($scale === 'month') {
        $first = $base->modify('first day of this month');
        $last = $base->modify('last day of this month');
        $gridStart = $first->modify('-' . $first->format('w') . ' days');
        $gridEnd = $last->modify('+' . (6 - (int) $last->format('w')) . ' days');

        $days = [];
        for ($cursor = $gridStart; $cursor <= $gridEnd; $cursor = $cursor->modify('+1 day')) {
            $cell = calendarDayCell($cursor);
            $cell['outside'] = $cursor->format('Y-m') !== $first->format('Y-m');
            $days[] = $cell;
        }
        $meta = [
            'scale' => 'month',
            'label' => $first->format('F Y'),
            'previous' => $first->modify('-1 month')->format('Y-m-d'),
            'next' => $first->modify('+1 month')->format('Y-m-d'),
        ];
    } else {
        $start = $base->modify('-' . $base->format('w') . ' days');
        $end = $start->modify('+6 days');

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = calendarDayCell($start->modify('+' . $i . ' days'));
        }
        $meta = [
            'scale' => 'week',
            'label' => $start->format('M j') . ' - ' . $end->format('M j, Y'),
            'previous' => $start->modify('-7 days')->format('Y-m-d'),
            'next' => $start->modify('+7 days')->format('Y-m-d'),
        ];
    }

    $rangeStart = (new DateTimeImmutable($days[0]['key']))->setTime(0, 0);
    $rangeEnd = (new DateTimeImmutable($days[count($days) - 1]['key']))->setTime(0, 0);

    $eventsByDay = [];
    foreach ($events as $event) {
        foreach (eventOccurrenceDates($event, $rangeStart, $rangeEnd) as $dateKey) {
            $eventsByDay[$dateKey][] = $event;
        }
    }
    foreach ($eventsByDay as $dateKey => $dayEvents) {
        usort($dayEvents, static fn(array $a, array $b): int => strcmp((string) $a['starts_at'], (string) $b['starts_at']));
        $eventsByDay[$dateKey] = $dayEvents;
    }

    return $meta + [
        'days' => $days,
        'events_by_day' => $eventsByDay,
    ];
}

function groupSchoolsForDirectory(array $schools): array
{
    $groups = [
        'elementary' => ['label' => 'Elementary Schools', 'items' => []],
        'middle' => ['label' => 'Middle Schools', 'items' => []],
        'high' => ['label' => 'High Schools', 'items' => []],
        'university' => ['label' => 'University', 'items' => []],
    ];

    foreach ($schools as $school) {
        $level = strtolower((string) ($school['level'] ?? ''));
        $groupKey = match ($level) {
            'elementary' => 'elementary',
            'middle' => 'middle',
            'high' => 'high',
            default => 'university',
        };
        $groups[$groupKey]['items'][] = $school;
    }

    return array_filter($groups, static fn(array $group): bool => $group['items'] !== []);
}

function formatCategoryLabel(string $value): string
{
    $normalized = str_replace(['_', '-'], ' ', trim($value));
    return ucwords($normalized);
}

function formatNewsCompactLabel(array $article): string
{
    $timestamp = (int) ($article['published_at_unix'] ?? 0);
    if ($timestamp <= 0) {
        return '';
    }

    if (date('Y-m-d', $timestamp) === date('Y-m-d')) {
        return strtoupper(date('ga', $timestamp));
    }

    return date('n/j', $timestamp);
}

function fetchRemoteText(string $url): ?string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 6,
            'user_agent' => 'PaloAltoLiveNewsBot/1.0',
        ],
        'https' => [
            'timeout' => 6,
            'user_agent' => 'PaloAltoLiveNewsBot/1.0',
        ],
    ]);

    $data = @file_get_contents($url, false, $context);
    if ($data !== false && $data !== '') {
        return $data;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => 'PaloAltoLiveNewsBot/1.0',
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        if (is_string($response) && $response !== '') {
            return $response;
        }
    }

    return null;
}

function fetchFeedXmlCached(string $url): ?string
{
    $cacheDir = sys_get_temp_dir() . '/palive-news-cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }

    $cacheFile = $cacheDir . '/' . sha1($url) . '.xml';
    $ttl = 900;
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $cached = file_get_contents($cacheFile);
        return $cached === false ? null : $cached;
    }

    $xml = fetchRemoteText($url);
    if ($xml === null) {
        if (is_file($cacheFile)) {
            $cached = file_get_contents($cacheFile);
            return $cached === false ? null : $cached;
        }
        return null;
    }

    file_put_contents($cacheFile, $xml);
    return $xml;
}

function parseRssArticles(array $sources): array
{
    $articles = [];

    foreach ($sources as $source) {
        $xml = fetchFeedXmlCached($source['feed_url']);
        if ($xml === null) {
            continue;
        }

        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml);
        if (!$feed || !isset($feed->channel->item)) {
            continue;
        }

        foreach ($feed->channel->item as $item) {
            $link = trim((string) $item->link);
            if ($link === '') {
                continue;
            }

            $publishedAt = strtotime((string) $item->pubDate) ?: time();
            $articles[$link] = [
                'title' => trim((string) $item->title),
                'link' => $link,
                'source_name' => $source['name'],
                'category' => $source['category'],
                'published_at' => $publishedAt,
                'published_label' => date('M j, Y g:i A', $publishedAt),
                'summary' => trim(strip_tags((string) ($item->description ?? ''))),
            ];
        }
    }

    usort($articles, static fn(array $a, array $b): int => $b['published_at'] <=> $a['published_at']);
    return array_slice($articles, 0, 24);
}

function fetchNewsArticles(PDO $pdo): array
{
    $articles = fetchAll($pdo, 'SELECT n.*, s.name AS source_name, s.category AS source_category, l.label AS category
        FROM news n
        JOIN news_sources s ON s.id = n.news_source_id
        LEFT JOIN lookup_values l ON l.id = n.category_id
        ORDER BY COALESCE(n.published_at, n.created_at) DESC, n.id DESC
        LIMIT 24');

    return array_map(static function (array $article): array {
        $timestamp = strtotime((string) ($article['published_at'] ?: $article['created_at'])) ?: time();
        $article['published_at_unix'] = $timestamp;
        $article['published_label'] = date('M j, Y g:i A', $timestamp);
        $article['summary'] = trim((string) ($article['summary'] ?? ''));
        $article['category'] = trim((string) ($article['category'] ?? '')) !== ''
            ? (string) $article['category']
            : (string) ($article['source_category'] ?? 'local');
        return $article;
    }, $articles);
}

/**
 * News relevant to a single school. Combines feed-based attribution
 * (news_sources.school_id) with conservative whole-word name/alias matching
 * against recent articles. Query-time, so it always reflects current news.
 */
function fetchSchoolNews(PDO $pdo, int $schoolId, string $schoolName, array $aliases): array
{
    $matched = fetchAll($pdo, 'SELECT n.*, s.name AS source_name, s.category AS source_category, l.label AS category
        FROM news n
        JOIN news_sources s ON s.id = n.news_source_id
        LEFT JOIN lookup_values l ON l.id = n.category_id
        WHERE s.school_id = :sid
        ORDER BY COALESCE(n.published_at, n.created_at) DESC, n.id DESC
        LIMIT 30', ['sid' => $schoolId]);

    $seen = [];
    foreach ($matched as $article) {
        $seen[(int) $article['id']] = true;
    }

    $terms = array_values(array_filter(array_map('trim', array_merge([$schoolName], $aliases))));
    if ($terms !== []) {
        $recent = fetchAll($pdo, 'SELECT n.*, s.name AS source_name, s.category AS source_category, l.label AS category
            FROM news n
            JOIN news_sources s ON s.id = n.news_source_id
            LEFT JOIN lookup_values l ON l.id = n.category_id
            ORDER BY COALESCE(n.published_at, n.created_at) DESC, n.id DESC
            LIMIT 200');

        $pattern = '/\b(' . implode('|', array_map(
            static fn(string $term): string => preg_quote($term, '/'),
            $terms
        )) . ')\b/i';

        foreach ($recent as $article) {
            if (isset($seen[(int) $article['id']])) {
                continue;
            }
            $haystack = (string) $article['title'] . ' ' . (string) ($article['summary'] ?? '');
            if (preg_match($pattern, $haystack) === 1) {
                $seen[(int) $article['id']] = true;
                $matched[] = $article;
            }
        }
    }

    usort($matched, static fn(array $x, array $y): int =>
        (strtotime((string) ($y['published_at'] ?: $y['created_at'])) ?: 0)
        <=> (strtotime((string) ($x['published_at'] ?: $x['created_at'])) ?: 0));

    return array_map(static function (array $article): array {
        $timestamp = strtotime((string) ($article['published_at'] ?: $article['created_at'])) ?: time();
        $article['published_at_unix'] = $timestamp;
        $article['published_label'] = date('M j, Y g:i A', $timestamp);
        $article['summary'] = trim((string) ($article['summary'] ?? ''));
        $article['category'] = trim((string) ($article['category'] ?? '')) !== ''
            ? (string) $article['category']
            : (string) ($article['source_category'] ?? 'local');
        return $article;
    }, array_slice($matched, 0, 8));
}

function loadStats(PDO $pdo, ?array $currentUser): array
{
    $counts = [
        'members' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'teams' => (int) $pdo->query('SELECT COUNT(*) FROM teams')->fetchColumn(),
        'events' => (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn(),
        'resources' => (int) $pdo->query('SELECT COUNT(*) FROM resources')->fetchColumn(),
        'discussions' => (int) $pdo->query('SELECT COUNT(*) FROM discussions')->fetchColumn(),
        'marketplace' => (int) $pdo->query('SELECT (SELECT COUNT(*) FROM deals) + (SELECT COUNT(*) FROM classifieds)')->fetchColumn(),
        'schools' => (int) $pdo->query('SELECT COUNT(*) FROM schools')->fetchColumn(),
        'visits' => (int) $pdo->query('SELECT COUNT(*) FROM visitor_logs')->fetchColumn(),
        'unique_visitors' => (int) $pdo->query('SELECT COUNT(DISTINCT visitor_token) FROM visitor_logs')->fetchColumn(),
    ];

    $counts['notifications'] = 0;
    if ($currentUser) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0');
        $stmt->execute(['user_id' => $currentUser['id']]);
        $counts['notifications'] = (int) $stmt->fetchColumn();
    }

    return $counts;
}

function search(PDO $pdo, $term): array
{
	$eventRows = fetchAll($pdo,
	'SELECT e.*, l.label AS category
	FROM events e
	LEFT JOIN lookup_values l on l.id = e.category_id AND l.type = ?
	WHERE (e.title LIKE ? OR e.description LIKE ? OR e.venue LIKE ? OR l.label LIKE ? OR SOUNDEX(title) = SOUNDEX(?))
	AND starts_at BETWEEN NOW() - INTERVAL 1 MONTH AND NOW() + INTERVAL 1 MONTH
	ORDER BY starts_at',
	['event',
	'%'.$term.'%',
	'%'.$term.'%',
	'%'.$term.'%',
	'%'.$term.'%',
	$term]);

	$newsRows = fetchAll($pdo,
	'SELECT n.*, l.label AS category, s.name as article_publisher
        FROM news n
        LEFT JOIN lookup_values l on l.id = n.category_id AND l.type = ?
        LEFT JOIN news_sources s on s.id =  n.news_source_id
	WHERE (n.summary LIKE ? OR n.title LIKE ? OR n.link LIKE ? OR l.label LIKE ? OR SOUNDEX(title) = SOUNDEX(?))
        AND published_at BETWEEN NOW() - INTERVAL 1 MONTH AND NOW() 
        ORDER BY COALESCE(n.published_at, n.created_at)', 
        ['news',
	'%'.$term.'%',
        '%'.$term.'%',
        '%'.$term.'%',
        '%'.$term.'%',
        $term]);

	return['events' => $eventRows, 'news' => $newsRows];
}



function fetchAll(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

