<?php
$events = fetchAll($pdo, 'SELECT e.*, u.name AS creator_name, t.name AS team_name, l.label AS category,
    (SELECT COUNT(*) FROM event_rsvps r WHERE r.event_id = e.id AND r.status = "going") AS going_count
    FROM events e
    JOIN users u ON u.id = e.created_by_user_id
    LEFT JOIN teams t ON t.id = e.team_id
    LEFT JOIN lookup_values l ON l.id = e.category_id
    ORDER BY e.starts_at ASC');
$newsSources = fetchAll($pdo, 'SELECT * FROM news_sources WHERE is_active = 1 ORDER BY category, name');
$newsArticles = fetchNewsArticles($pdo);
$searchTerm = trim($_GET['q'] ?? '');
$results = ($searchTerm !== '') ? search($pdo, $searchTerm) : null;
if ($newsArticles === []) {
    $newsArticles = parseRssArticles($newsSources);
}
?>

<form method="get" action="">
   <div class="search-input-wrap">
      <input type="text" name="q" value="<?= h($searchTerm) ?>" placeholder="Search events and news...">
      <a href="?">x</a>
   </div> 
   <button type="submit" class="search_btn">Search</button>
</form>

<?php if ($searchTerm === ''): ?> 

                <section class="dashboard-columns">
                    <div class="card stack">
                        <div class="toolbar">
                            <div class="toolbar-group">
                                <h2 style="margin: 0;">Latest News</h2>
                            </div>
                            <div class="toolbar-group">
                                <a href="?page=news">View All News</a>
                            </div>
                        </div>
                        <?php foreach (array_slice($newsArticles, 0, 5) as $article): ?>
                            <article class="panel">
                                <details class="news-details">
                                    <summary class="disclosure-summary">
                                        <div class="news-summary disclosure-label">
                                            <div class="news-title">
                                                <strong><?= h($article['title']) ?></strong>
                                            </div>
                                        </div>
                                        <span class="disclosure-arrow">›</span>
                                    </summary>
                                    <div class="news-body">
                                        <div><span class="badge"><?= h(formatCategoryLabel((string) $article['category'])) ?></span></div>
                                        <?php if ($article['summary'] !== ''): ?>
                                            <p><?= h(mb_strimwidth($article['summary'], 0, 280, '...')) ?></p>
                                        <?php endif; ?>
                                        <div class="meta"><a href="<?= h($article['link']) ?>" target="_blank" rel="noreferrer">More</a></div>
                                        <div class="meta"><?= h($article['source_name']) ?></div>
                                    </div>
                                </details>
                            </article>
                        <?php endforeach; ?>
                        <?php if ($newsArticles === []): ?>
                            <p class="meta">Recent news is temporarily unavailable.</p>
                        <?php endif; ?>
                    </div>
                    <div class="card stack">
                        <div class="toolbar">
                            <div class="toolbar-group">
                                <h2 style="margin: 0;">Upcoming Events</h2>
                            </div>
                            <div class="toolbar-group">
                                <a href="?page=events&events_view=list&events_sort=upcoming">View All Events</a>
                            </div>
                        </div>
                        <?php $dashboardEvents = array_values(array_filter($events, static fn(array $e): bool => strtotime((string) $e['starts_at']) > time())); ?>
                        <?php foreach (array_slice($dashboardEvents, 0, 5) as $event): ?>
                            <article class="panel">
                                <details class="event-details">
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
                                            <?php $recLabel = recurrenceLabel((string) ($event['recurrence'] ?? 'none')); ?>
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
                        <?php endforeach; ?>
                        <?php if ($events === []): ?>
                            <p class="meta">No events have been posted yet.</p>
                        <?php endif; ?>
                    </div>
                </section>
<?php else: ?>
<section class="search-result-dashboard-columns">
    <div class="card stack">

        <!-- ===== EVENTS SUB-SECTION ===== -->
        <div class="toolbar">
            <div class="toolbar-group">
                <h2 style="margin: 0;">Recent and Upcoming Events</h2>
            </div>
        </div>
        <?php foreach ($results['events'] as $event): ?>
            <article class="panel search-result">
                <details class="event-details">
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
                        <?php $recLabel = recurrenceLabel((string) ($event['recurrence'] ?? 'none')); ?>
                        <?php if ($recLabel !== ''): ?>
                            <span class="badge badge-recurring">↻ <?= h($recLabel) ?><?= !empty($event['recurrence_until']) ? ' until ' . h((new DateTimeImmutable($event['recurrence_until']))->format('n/j/Y')) : '' ?></span>
                        <?php endif; ?>
                        <div class="meta"><?= h($event['venue']) ?>, <?= h($event['address']) ?></div>
                        <?php if ($event['description'] !== ''): ?>
                            <p><?= h(mb_strimwidth($event['description'], 0, 280, '...')) ?></p>
                        <?php endif; ?>
                        <div class="meta"><a href="<?= h($event['website']) ?>" target="_blank" rel="noreferrer">Website</a></div>
                    </div>
                </details>
            </article>
        <?php endforeach; ?>
        <?php if ($results['events'] === []): ?>
            <p class="meta">No events have been posted yet.</p>
        <?php endif; ?>

        <!-- ===== NEWS SUB-SECTION ===== -->
        <div class="toolbar">
            <div class="toolbar-group">
                <h2 style="margin: 0;">Related News</h2>
            </div>
        </div>
        <?php foreach ($results['news'] as $news): ?>
            <article class="panel search-result">
                <details class="news-details">
                    <summary class="disclosure-summary">
                        <div class="news-summary disclosure-label">
                            <div class="news-title">
                                <strong><?= h($news['title']) ?></strong>
                            </div>
                        </div>
			<span class="disclosure-arrow">›</span>                        
                    </summary>
                    <div class="news-body">
                        <div><span class="badge"><?= h(formatCategoryLabel((string) $news['category'])) ?></span></div>
                        <?php if ($news['summary'] !== ''): ?>
                            <p><?= h(mb_strimwidth($news['summary'], 0, 280, '...')) ?></p>
                        <?php endif; ?>
                        <div class="meta"><a href="<?= h($news['link']) ?>" target="_blank" rel="noreferrer">More</a></div>
                        <div class="meta"><?= h($news['article_publisher']) ?></div>
                    </div>
	                </details>
            </article>
        <?php endforeach; ?>
        <?php if ($results['news'] === []): ?>
            <p class="meta">Recent news is unavailable.</p>
        <?php endif; ?>

    </div>
</section>

<script>
const cards = document.querySelectorAll('.search-result');
let currentIndex = -1;

document.addEventListener('keydown', function(event) {

    if (event.key === 'ArrowDown') {
        event.preventDefault();
	if (currentIndex === cards.length - 1) {
            return;
        } else if (currentIndex === -1) {
		currentIndex += 1;
		cards[currentIndex].classList.add('highlighted');
		cards[currentIndex].scrollIntoView({ block: 'nearest' });

	} else {
            cards[currentIndex].classList.remove('highlighted');
            currentIndex += 1;
            cards[currentIndex].classList.add('highlighted');
	    cards[currentIndex].scrollIntoView({ block: 'nearest' });
	}
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
	if (currentIndex === 0) {
            return;
        } else {
            cards[currentIndex].classList.remove('highlighted');
            currentIndex -= 1;
            cards[currentIndex].classList.add('highlighted');
	    cards[currentIndex].scrollIntoView({ block: 'nearest' });
        }
    } else if (event.key === 'Enter') {
        if (currentIndex >= 0 && currentIndex <= cards.length - 1) {
            const someURL = cards[currentIndex].querySelector('a').href;
            window.open(someURL, '_blank');
        }
    }
});
</script>

<?php endif; ?>
