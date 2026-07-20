<?php

declare(strict_types=1);

// The app and its data are Pacific time. Pin PHP to Pacific so all date/time
// math (NOW comparisons, "today", greying) is consistent with the database,
// which is pinned to the same zone per-connection in pdo().
const APP_TIMEZONE = 'America/Los_Angeles';
date_default_timezone_set(APP_TIMEZONE);

function env(string $key, ?string $default = null): ?string
{
    static $vars = null;

    if ($vars === null) {
        $vars = [];
        $envPath = dirname(__DIR__) . '/.env';
        if (is_file($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$name, $value] = explode('=', $line, 2);
                $vars[trim($name)] = trim($value);
            }
        }
    }

    return $vars[$key] ?? $default;
}

function pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST', '127.0.0.1'),
        env('DB_PORT', '3306'),
        env('DB_DATABASE', '')
    );

    $pdo = new PDO($dsn, env('DB_USERNAME', ''), env('DB_PASSWORD', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Pin the DB session to Pacific so NOW()/CURDATE() match the PHP clock.
    // Prefer the named zone; fall back to the current numeric offset if the
    // MySQL timezone tables aren't loaded.
    try {
        $pdo->exec("SET time_zone = '" . APP_TIMEZONE . "'");
    } catch (PDOException $exception) {
        $pdo->exec("SET time_zone = '" . (new DateTimeImmutable('now'))->format('P') . "'");
    }

    return $pdo;
}

function bootstrapSchema(PDO $pdo): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    $sql = file_get_contents(dirname(__DIR__) . '/schema.sql');
    if ($sql !== false) {
        $pdo->exec($sql);
    }

    migrateSchema($pdo);
    seedDatabase($pdo);
    seedSchoolAliases($pdo);
    seedUniversities($pdo);
    seedDiscussions($pdo);
    $bootstrapped = true;
}

/**
 * Idempotent column additions for tables that pre-date a schema change.
 * CREATE TABLE IF NOT EXISTS never alters an existing table, so new columns
 * are added here only when missing.
 */
function migrateSchema(PDO $pdo): void
{
    $eventColumns = $pdo
        ->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events'")
        ->fetchAll(PDO::FETCH_COLUMN);

    $recurrenceEnum = "ENUM('none', 'daily', 'weekly', 'biweekly', 'monthly', 'yearly') NOT NULL DEFAULT 'none'";

    if (!in_array('recurrence', $eventColumns, true)) {
        $pdo->exec("ALTER TABLE events ADD COLUMN recurrence $recurrenceEnum AFTER ends_at");
    } else {
        // Widen a pre-existing narrow enum (e.g. the original ('none','weekly')).
        $columnType = (string) $pdo
            ->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'recurrence'")
            ->fetchColumn();
        if (stripos($columnType, 'monthly') === false) {
            $pdo->exec("ALTER TABLE events MODIFY COLUMN recurrence $recurrenceEnum");
        }
    }

    if (!in_array('recurrence_until', $eventColumns, true)) {
        $pdo->exec("ALTER TABLE events ADD COLUMN recurrence_until DATE DEFAULT NULL AFTER recurrence");
    }

    // Per-school news feed attribution (Option A) — link a source directly to a school.
    $sourceColumns = $pdo
        ->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'news_sources'")
        ->fetchAll(PDO::FETCH_COLUMN);
    if ($sourceColumns !== [] && !in_array('school_id', $sourceColumns, true)) {
        $pdo->exec("ALTER TABLE news_sources ADD COLUMN school_id INT DEFAULT NULL AFTER is_active");
    }

    // Allow 'university' as a school level (for Stanford et al).
    $levelType = (string) $pdo
        ->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'level'")
        ->fetchColumn();
    if ($levelType !== '' && stripos($levelType, 'university') === false) {
        $pdo->exec("ALTER TABLE schools MODIFY COLUMN level ENUM('elementary', 'middle', 'high', 'college', 'university') NOT NULL");
    }

    // Normalize legacy 'college' rows to 'university'.
    $pdo->exec("UPDATE schools SET level = 'university' WHERE level = 'college'");

    // ---- Category lookup table (event types + news categories) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS lookup_values (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(20) NOT NULL,
        label VARCHAR(80) NOT NULL,
        UNIQUE KEY uq_lookup_type_label (type, label)
    )");

    $eventCols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('category_id', $eventCols, true)) {
        $pdo->exec("ALTER TABLE events ADD COLUMN category_id INT NULL AFTER category");
    }
    if (in_array('category', $eventCols, true)) {
        // Legacy merge, then backfill the lookup table + ids from the old column.
        $pdo->exec("UPDATE events SET category = 'Health' WHERE category = 'Health & Wellness'");
        $pdo->exec("INSERT IGNORE INTO lookup_values (type, label)
            SELECT DISTINCT 'event', category FROM events WHERE category IS NOT NULL AND category <> ''");
        $pdo->exec("UPDATE events e JOIN lookup_values l ON l.type = 'event' AND l.label = e.category
            SET e.category_id = l.id WHERE e.category_id IS NULL");
        $pdo->exec("ALTER TABLE events DROP COLUMN category");
        try {
            $pdo->exec("ALTER TABLE events ADD CONSTRAINT fk_events_category FOREIGN KEY (category_id) REFERENCES lookup_values(id) ON DELETE SET NULL");
        } catch (PDOException $e) {
            // FK already present
        }
    }

    $newsCols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'news'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('category_id', $newsCols, true)) {
        $pdo->exec("ALTER TABLE news ADD COLUMN category_id INT NULL AFTER category");
    }
    if (in_array('category', $newsCols, true)) {
        $pdo->exec("INSERT IGNORE INTO lookup_values (type, label)
            SELECT DISTINCT 'news', category FROM news WHERE category IS NOT NULL AND category <> ''");
        $pdo->exec("UPDATE news n JOIN lookup_values l ON l.type = 'news' AND l.label = n.category
            SET n.category_id = l.id WHERE n.category_id IS NULL");
        $pdo->exec("ALTER TABLE news DROP COLUMN category");
        try {
            $pdo->exec("ALTER TABLE news ADD CONSTRAINT fk_news_category FOREIGN KEY (category_id) REFERENCES lookup_values(id) ON DELETE SET NULL");
        } catch (PDOException $e) {
            // FK already present
        }
    }

    // Move the remaining content-category varchars onto lookup_values too.
    foreach ([['deals', 'deal'], ['resources', 'resource'], ['discussions', 'discussion']] as [$tbl, $ltype]) {
        $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tbl'")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('category_id', $cols, true)) {
            $pdo->exec("ALTER TABLE $tbl ADD COLUMN category_id INT NULL AFTER category");
        }
        if (in_array('category', $cols, true)) {
            $pdo->exec("INSERT IGNORE INTO lookup_values (type, label)
                SELECT DISTINCT '$ltype', category FROM $tbl WHERE category IS NOT NULL AND category <> ''");
            $pdo->exec("UPDATE $tbl t JOIN lookup_values l ON l.type = '$ltype' AND l.label = t.category
                SET t.category_id = l.id WHERE t.category_id IS NULL");
            $pdo->exec("ALTER TABLE $tbl DROP COLUMN category");
            try {
                $pdo->exec("ALTER TABLE $tbl ADD CONSTRAINT fk_{$tbl}_category FOREIGN KEY (category_id) REFERENCES lookup_values(id) ON DELETE SET NULL");
            } catch (PDOException $e) {
                // FK already present
            }
        }
    }

    // Always provide an "Other" option at the end of each list.
    $pdo->exec("INSERT IGNORE INTO lookup_values (type, label) VALUES
        ('event', 'Other'), ('news', 'Other'), ('deal', 'Other'), ('resource', 'Other'), ('discussion', 'Other')");
}

/**
 * Common nicknames so news matching also catches "Paly", "Gunn", "JLS", etc.
 * Idempotent: only seeds when the alias table is empty.
 */
function seedSchoolAliases(PDO $pdo): void
{
    $hasAliases = (int) $pdo->query('SELECT COUNT(*) FROM school_aliases')->fetchColumn();
    if ($hasAliases > 0) {
        return;
    }

    $aliasMap = [
        'Palo Alto High School' => ['Paly'],
        'Henry M. Gunn High School' => ['Gunn', 'Gunn High'],
        'Jane Lathrop Stanford (JLS) Middle School' => ['JLS'],
        'Ellen Fletcher Middle School' => ['Fletcher Middle School'],
        'Frank S. Greene Jr. Middle School' => ['Greene Middle School'],
        'International School of the Peninsula (ISTP)' => ['ISTP'],
        'Lucille M. Nixon Elementary' => ['Nixon Elementary'],
        'Herbert Hoover Elementary' => ['Hoover Elementary'],
        'Juana Briones Elementary' => ['Briones Elementary'],
    ];

    $lookup = $pdo->prepare('SELECT id FROM schools WHERE name = :name LIMIT 1');
    $insert = $pdo->prepare('INSERT IGNORE INTO school_aliases (school_id, alias) VALUES (:school_id, :alias)');

    foreach ($aliasMap as $name => $aliases) {
        $lookup->execute(['name' => $name]);
        $schoolId = $lookup->fetchColumn();
        if ($schoolId === false) {
            continue;
        }
        foreach ($aliases as $alias) {
            $insert->execute(['school_id' => (int) $schoolId, 'alias' => $alias]);
        }
    }
}

/**
 * Ensure Stanford University exists under the University group. Idempotent.
 */
/**
 * Seed 10 Palo Alto "hot topic" discussion threads. Idempotent: keyed on the
 * first topic's title, so it only seeds once.
 */
function seedDiscussions(PDO $pdo): void
{
    $topics = [
        ['14-story tower downtown: too tall for Palo Alto?', 'Development', 'The council narrowly signaled support for a 14-story tower. Does this fit our skyline and housing goals, or set a precedent we will regret?'],
        ['Caltrain grade separation: which design for our crossings?', 'Transportation', 'Churchill, Meadow, and Charleston all need a fix. Trench, viaduct, or underpass — what is the right call for cost, noise, and neighborhoods?'],
        ['Cubberley redevelopment heading to the November ballot', 'City Government', 'A measure to fund the Cubberley Community Center rebuild is advancing. What should the priorities be — fields, arts space, or a new community hub?'],
        ['PAUSD budget and teacher pay raises', 'Schools', 'The district tentatively approved pay raises for educators. How do we keep great teachers while staying within budget?'],
        ['Castilleja expansion: traffic and neighborhood impact', 'Development', 'The enrollment-and-rebuild plan keeps drawing debate. Are the traffic and garage conditions enough to protect the neighborhood?'],
        ['Safe Routes to School: do we need more protected bike lanes?', 'Transportation', 'With school traffic back, parents are asking for protected lanes near Paly, Gunn, and the middle schools. Where are the worst gaps?'],
        ['Foothills Park crowding since it opened to everyone', 'Parks & Rec', 'Now that the preserve is open to non-residents, parking and trail crowding are up. Reservation system, or leave it as is?'],
        ['Cal Ave promenade: keep the street car-free?', 'Local Business', 'The closed-street dining on California Ave is popular but merchants are split on parking. Should the promenade be permanent?'],
        ['Wildfire prep: should we underground the utility lines?', 'Safety', 'Undergrounding is expensive but reduces fire risk and outages. Is it worth the rate impact, and which evacuation routes worry you most?'],
        ['Jobs-housing balance: more offices or more homes?', 'Housing', 'With the office cap and the housing element, how aggressively should Palo Alto add homes versus protect neighborhood character?'],
    ];

    $check = $pdo->prepare('SELECT COUNT(*) FROM discussions WHERE title = :title');
    $check->execute(['title' => $topics[0][0]]);
    if ((int) $check->fetchColumn() > 0) {
        return;
    }

    $ownerId = $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
    if ($ownerId === false) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO discussions (title, category, body, created_by_user_id, is_pinned, created_at)
         VALUES (:title, :category, :body, :owner, :pinned, NOW() - INTERVAL :mins MINUTE)'
    );

    foreach ($topics as $index => [$title, $category, $body]) {
        $insert->execute([
            'title' => $title,
            'category' => $category,
            'body' => $body,
            'owner' => (int) $ownerId,
            'pinned' => $index === 0 ? 1 : 0,
            'mins' => $index * 90,
        ]);
    }
}

function seedUniversities(PDO $pdo): void
{
    $exists = (int) $pdo->query("SELECT COUNT(*) FROM schools WHERE name = 'Stanford University'")->fetchColumn();
    if ($exists > 0) {
        return;
    }

    $ownerId = $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
    if ($ownerId === false) {
        return;
    }

    $pdo->prepare(
        'INSERT INTO schools (name, school_type, level, neighborhood, website, created_by_user_id)
         VALUES (:name, :school_type, :level, :neighborhood, :website, :owner)'
    )->execute([
        'name' => 'Stanford University',
        'school_type' => 'private',
        'level' => 'university',
        'neighborhood' => 'Stanford',
        'website' => 'https://www.stanford.edu',
        'owner' => (int) $ownerId,
    ]);

    $schoolId = (int) $pdo->lastInsertId();
    $aliasStmt = $pdo->prepare('INSERT IGNORE INTO school_aliases (school_id, alias) VALUES (:sid, :alias)');
    foreach (['Stanford', 'Stanford University'] as $alias) {
        $aliasStmt->execute(['sid' => $schoolId, 'alias' => $alias]);
    }
}

function seedDatabase(PDO $pdo): void
{
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount === 0) {
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, neighborhood, bio, role)
             VALUES (:name, :email, :password_hash, :neighborhood, :bio, :role)'
        );
        $stmt->execute([
            'name' => 'Portal Admin',
            'email' => 'admin@cityportal.local',
            'password_hash' => $passwordHash,
            'neighborhood' => 'Central District',
            'bio' => 'Bootstrap administrator account for the city portal.',
            'role' => 'admin',
        ]);

        $adminId = (int) $pdo->lastInsertId();

        $teamStmt = $pdo->prepare(
            'INSERT INTO teams (name, description, visibility, owner_user_id)
             VALUES (:name, :description, :visibility, :owner_user_id)'
        );
        $teamStmt->execute([
            'name' => 'Downtown Volunteers',
            'description' => 'Neighborhood cleanup and civic support team.',
            'visibility' => 'public',
            'owner_user_id' => $adminId,
        ]);
        $teamId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO team_members (team_id, user_id) VALUES (:team_id, :user_id)')
            ->execute(['team_id' => $teamId, 'user_id' => $adminId]);

        $pdo->prepare(
            'INSERT INTO events (title, description, category, venue, address, website, starts_at, ends_at, created_by_user_id, team_id)
             VALUES (:title, :description, :category, :venue, :address, :website, :starts_at, :ends_at, :created_by_user_id, :team_id)'
        )->execute([
            'title' => 'City Hall Listening Session',
            'description' => 'Open forum for resident feedback on transit and safety priorities.',
            'category' => 'Community',
            'venue' => 'City Hall',
            'address' => '100 Main St',
            'website' => 'https://example.org/city-hall-listening-session',
            'starts_at' => date('Y-m-d 18:00:00', strtotime('+2 days')),
            'ends_at' => date('Y-m-d 20:00:00', strtotime('+2 days')),
            'created_by_user_id' => $adminId,
            'team_id' => $teamId,
        ]);

        $pdo->prepare(
            'INSERT INTO resources (title, category, contact_name, phone, website, details, created_by_user_id)
             VALUES (:title, :category, :contact_name, :phone, :website, :details, :created_by_user_id)'
        )->execute([
            'title' => 'Public Works Service Desk',
            'category' => 'Utilities',
            'contact_name' => 'Public Works',
            'phone' => '(555) 010-2000',
            'website' => 'https://example.org/public-works',
            'details' => 'Report road hazards, streetlight issues, and service interruptions.',
            'created_by_user_id' => $adminId,
        ]);

    $pdo->prepare(
        'INSERT INTO deals (title, business_name, category, website, description, expires_at, created_by_user_id)
         VALUES (:title, :business_name, :category, :website, :description, :expires_at, :created_by_user_id)'
    )->execute([
        'title' => '15% off weekend market boxes',
        'business_name' => 'Riverfront Grocer',
        'category' => 'Food',
        'website' => 'https://example.org/riverfront-grocer',
        'description' => 'Resident discount on curated local produce boxes for Saturday pickup.',
        'expires_at' => date('Y-m-d 23:59:59', strtotime('+14 days')),
        'created_by_user_id' => $adminId,
    ]);

        $pdo->prepare(
            'INSERT INTO classifieds (title, listing_type, price, neighborhood, description, expires_at, created_by_user_id)
             VALUES (:title, :listing_type, :price, :neighborhood, :description, :expires_at, :created_by_user_id)'
        )->execute([
            'title' => 'Community garden tools for sale',
            'listing_type' => 'item',
            'price' => 45.00,
            'neighborhood' => 'Central District',
            'description' => 'Gently used shovels, gloves, and hand tools from a neighborhood cleanup program.',
            'expires_at' => date('Y-m-d 23:59:59', strtotime('+21 days')),
            'created_by_user_id' => $adminId,
        ]);

        $pdo->prepare(
            'INSERT INTO discussions (title, category, body, created_by_user_id, team_id, is_pinned)
             VALUES (:title, :category, :body, :created_by_user_id, :team_id, :is_pinned)'
        )->execute([
            'title' => 'What should the summer events calendar include?',
            'category' => 'Ideas',
            'body' => 'Share programming ideas for concerts, family activities, and neighborhood meetups.',
            'created_by_user_id' => $adminId,
            'team_id' => $teamId,
            'is_pinned' => 1,
        ]);
    }

    $adminId = (int) $pdo->query("SELECT id FROM users ORDER BY CASE WHEN role = 'admin' THEN 0 ELSE 1 END, id ASC LIMIT 1")->fetchColumn();
    if ($adminId === 0) {
        return;
    }

    $schoolCount = (int) $pdo->query('SELECT COUNT(*) FROM schools')->fetchColumn();
    if ($schoolCount > 0) {
        return;
    }

    $schoolStmt = $pdo->prepare(
        'INSERT INTO schools (name, school_type, level, neighborhood, website, created_by_user_id)
         VALUES (:name, :school_type, :level, :neighborhood, :website, :created_by_user_id)'
    );
    $schoolStmt->execute([
        'name' => 'Central City Elementary',
        'school_type' => 'public',
        'level' => 'elementary',
        'neighborhood' => 'Central District',
        'website' => 'https://example.org/central-city-elementary',
        'created_by_user_id' => $adminId,
    ]);
    $elementaryId = (int) $pdo->lastInsertId();
    $schoolStmt->execute([
        'name' => 'North Valley Middle School',
        'school_type' => 'public',
        'level' => 'middle',
        'neighborhood' => 'North Hill',
        'website' => 'https://example.org/north-valley-middle',
        'created_by_user_id' => $adminId,
    ]);
    $middleId = (int) $pdo->lastInsertId();
    $schoolStmt->execute([
        'name' => 'Starlight Preparatory Academy',
        'school_type' => 'private',
        'level' => 'high',
        'neighborhood' => 'West Ridge',
        'website' => 'https://example.org/starlight-prep',
        'created_by_user_id' => $adminId,
    ]);
    $highId = (int) $pdo->lastInsertId();

    $scheduleStmt = $pdo->prepare(
        'INSERT INTO school_schedules (school_id, label, days, start_time, end_time, notes)
         VALUES (:school_id, :label, :days, :start_time, :end_time, :notes)'
    );
    $scheduleStmt->execute([
        'school_id' => $elementaryId,
        'label' => 'Elementary Regular Day',
        'days' => 'Mon-Fri',
        'start_time' => '08:00:00',
        'end_time' => '14:30:00',
        'notes' => 'Early release every Wednesday at 1:30 PM.',
    ]);
    $scheduleStmt->execute([
        'school_id' => $middleId,
        'label' => 'Middle School Block Schedule',
        'days' => 'Mon-Fri',
        'start_time' => '08:20:00',
        'end_time' => '15:10:00',
        'notes' => 'Advisory starts at 8:20 AM, clubs on Thursdays.',
    ]);
    $scheduleStmt->execute([
        'school_id' => $highId,
        'label' => 'High School Academic Day',
        'days' => 'Mon-Fri',
        'start_time' => '08:15:00',
        'end_time' => '15:25:00',
        'notes' => 'Zero period available at 7:20 AM.',
    ]);

    $schoolEventStmt = $pdo->prepare(
        'INSERT INTO school_events (school_id, title, description, location, starts_at, ends_at, created_by_user_id)
         VALUES (:school_id, :title, :description, :location, :starts_at, :ends_at, :created_by_user_id)'
    );
    $schoolEventStmt->execute([
        'school_id' => $elementaryId,
        'title' => 'Elementary Family Reading Night',
        'description' => 'Classroom reading stations and literacy games for families.',
        'location' => 'Central City Elementary Library',
        'starts_at' => date('Y-m-d 18:00:00', strtotime('+9 days')),
        'ends_at' => date('Y-m-d 19:30:00', strtotime('+9 days')),
        'created_by_user_id' => $adminId,
    ]);
    $schoolEventStmt->execute([
        'school_id' => $middleId,
        'title' => 'Middle School Orientation',
        'description' => 'Student schedule pickup, campus tours, and athletics information.',
        'location' => 'North Valley Middle School Commons',
        'starts_at' => date('Y-m-d 17:30:00', strtotime('+12 days')),
        'ends_at' => date('Y-m-d 19:00:00', strtotime('+12 days')),
        'created_by_user_id' => $adminId,
    ]);
    $schoolEventStmt->execute([
        'school_id' => $highId,
        'title' => 'High School College Planning Night',
        'description' => 'Counselors review applications, financial aid, and testing timelines.',
        'location' => 'Starlight Preparatory Academy Auditorium',
        'starts_at' => date('Y-m-d 18:30:00', strtotime('+15 days')),
        'ends_at' => date('Y-m-d 20:00:00', strtotime('+15 days')),
        'created_by_user_id' => $adminId,
    ]);

    $newsSourceCount = (int) $pdo->query('SELECT COUNT(*) FROM news_sources')->fetchColumn();
    if ($newsSourceCount === 0) {
        $newsStmt = $pdo->prepare(
            'INSERT INTO news_sources (name, feed_url, category, is_active)
             VALUES (:name, :feed_url, :category, :is_active)'
        );
        $newsStmt->execute([
            'name' => 'Palo Alto Local News',
            'feed_url' => 'https://news.google.com/rss/search?q=Palo+Alto+when%3A7d&hl=en-US&gl=US&ceid=US%3Aen',
            'category' => 'local',
            'is_active' => 1,
        ]);
        $newsStmt->execute([
            'name' => 'Palo Alto Schools News',
            'feed_url' => 'https://news.google.com/rss/search?q=%28Palo+Alto+Unified+OR+PAUSD%29+when%3A14d&hl=en-US&gl=US&ceid=US%3Aen',
            'category' => 'schools',
            'is_active' => 1,
        ]);
        $newsStmt->execute([
            'name' => 'Palo Alto Civic News',
            'feed_url' => 'https://news.google.com/rss/search?q=%28City+of+Palo+Alto+OR+Palo+Alto+City+Council%29+when%3A14d&hl=en-US&gl=US&ceid=US%3Aen',
            'category' => 'civic',
            'is_active' => 1,
        ]);
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionPath = sys_get_temp_dir() . '/palive-sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }
    session_save_path($sessionPath);
    session_start();
}
