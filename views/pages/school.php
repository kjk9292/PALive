<?php
$schools = fetchAll($pdo, 'SELECT s.*, u.name AS creator_name FROM schools s JOIN users u ON u.id = s.created_by_user_id ORDER BY s.level, s.school_type, s.name');
$schoolSchedules = fetchAll($pdo, 'SELECT ss.*, s.name AS school_name, s.school_type, s.level
    FROM school_schedules ss
    JOIN schools s ON s.id = ss.school_id
    ORDER BY FIELD(s.level, "elementary", "middle", "high"), s.name, ss.start_time');
$schoolEvents = fetchAll($pdo, 'SELECT se.*, s.name AS school_name, s.school_type, s.level, u.name AS creator_name
    FROM school_events se
    JOIN schools s ON s.id = se.school_id
    JOIN users u ON u.id = se.created_by_user_id
    ORDER BY se.starts_at ASC');
$selectedSchool = null;
$selectedSchoolSchedules = [];
$selectedSchoolEvents = [];
$selectedSchoolNews = [];
if ($schoolId > 0) {
    foreach ($schools as $school) {
        if ((int) $school['id'] === $schoolId) {
            $selectedSchool = $school;
            break;
        }
    }
    if ($selectedSchool) {
        $selectedSchoolSchedules = array_values(array_filter($schoolSchedules, static fn(array $s): bool => (int) $s['school_id'] === $schoolId));
        $selectedSchoolEvents = array_values(array_filter($schoolEvents, static fn(array $e): bool => (int) $e['school_id'] === $schoolId));
        $schoolAliases = array_column(fetchAll($pdo, 'SELECT alias FROM school_aliases WHERE school_id = :sid', ['sid' => $schoolId]), 'alias');
        $selectedSchoolNews = fetchSchoolNews($pdo, $schoolId, (string) $selectedSchool['name'], $schoolAliases);
    }
}
?>
<?php if ($selectedSchool): ?>
                <section class="card stack">
                    <div class="toolbar">
                        <div class="toolbar-group">
                            <h2 style="margin: 0;"><?= h($selectedSchool['name']) ?></h2>
                            <span class="badge"><?= ucfirst(h($selectedSchool['level'])) ?></span>
                            <span class="badge"><?= h($selectedSchool['school_type']) ?></span>
                        </div>
                        <div class="toolbar-group">
                            <a href="?page=schools">Back to Schools</a>
                        </div>
                    </div>
                    <div class="meta"><?= $selectedSchool['neighborhood'] !== '' ? h($selectedSchool['neighborhood']) : 'Palo Alto' ?></div>
                    <?php if ($selectedSchool['website'] !== ''): ?>
                        <div class="meta"><a href="<?= h($selectedSchool['website']) ?>" target="_blank" rel="noreferrer">School website</a></div>
                    <?php endif; ?>
                </section>
                <section class="card">
                    <details class="directory-group">
                        <summary class="disclosure-summary">
                            <span class="disclosure-label">Schedule (<?= count($selectedSchoolSchedules) ?>)</span>
                            <span class="disclosure-arrow">›</span>
                        </summary>
                        <div class="stack">
                            <?php foreach ($selectedSchoolSchedules as $schedule): ?>
                                <article class="panel">
                                    <strong><?= h($schedule['label']) ?></strong>
                                    <p><?= h($schedule['days']) ?>, <?= h(substr($schedule['start_time'], 0, 5)) ?> to <?= h(substr($schedule['end_time'], 0, 5)) ?></p>
                                    <?php if ($schedule['notes'] !== ''): ?>
                                        <div class="meta"><?= h($schedule['notes']) ?></div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                            <?php if ($selectedSchoolSchedules === []): ?>
                                <p class="meta">No schedules added for this school yet.</p>
                            <?php endif; ?>
                        </div>
                    </details>
                </section>
                <section class="card">
                    <details class="directory-group">
                        <summary class="disclosure-summary">
                            <span class="disclosure-label">School Events (<?= count($selectedSchoolEvents) ?>)</span>
                            <span class="disclosure-arrow">›</span>
                        </summary>
                        <div class="stack">
                            <?php foreach ($selectedSchoolEvents as $schoolEvent): ?>
                                <article class="panel">
                                    <strong><?= h($schoolEvent['title']) ?></strong>
                                    <p><?= h($schoolEvent['description']) ?></p>
                                    <div class="meta"><?= h($schoolEvent['location']) ?> • <?= h(formatEventDateRange($schoolEvent['starts_at'], $schoolEvent['ends_at'])) ?></div>
                                </article>
                            <?php endforeach; ?>
                            <?php if ($selectedSchoolEvents === []): ?>
                                <p class="meta">No school events scheduled yet.</p>
                            <?php endif; ?>
                        </div>
                    </details>
                </section>
                <section class="card">
                    <details class="directory-group" open>
                        <summary class="disclosure-summary">
                            <span class="disclosure-label">School News (<?= count($selectedSchoolNews) ?>)</span>
                            <span class="disclosure-arrow">›</span>
                        </summary>
                        <div class="stack">
                            <?php foreach ($selectedSchoolNews as $article): ?>
                                <article class="panel">
                                    <details class="news-details">
                                        <summary class="disclosure-summary">
                                            <div class="news-summary disclosure-label">
                                                <div class="news-title">
                                                    <strong><?= h($article['title']) ?></strong>
                                                </div>
                                                <div class="meta"><?= h(formatNewsCompactLabel($article)) ?></div>
                                            </div>
                                            <span class="disclosure-arrow">›</span>
                                        </summary>
                                        <div class="news-body">
                                            <div><span class="badge"><?= h(formatCategoryLabel((string) $article['category'])) ?></span></div>
                                            <?php if ($article['summary'] !== ''): ?>
                                                <p><?= h(mb_strimwidth($article['summary'], 0, 280, '...')) ?></p>
                                            <?php endif; ?>
                                            <div class="meta"><a href="<?= h($article['link']) ?>" target="_blank" rel="noreferrer">More</a></div>
                                            <div class="meta"><?= h($article['source_name']) ?> • <?= h($article['published_label']) ?></div>
                                        </div>
                                    </details>
                                </article>
                            <?php endforeach; ?>
                            <?php if ($selectedSchoolNews === []): ?>
                                <p class="meta">No related news found yet.</p>
                            <?php endif; ?>
                        </div>
                    </details>
                </section>
<?php else: ?>
                <section class="card">
                    <h2>School Not Found</h2>
                    <p class="meta">Choose a school from the directory to view its schedule and events.</p>
                    <p><a href="?page=schools">Back to Schools</a></p>
                </section>
<?php endif; ?>
