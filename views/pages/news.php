<?php
$newsSources = fetchAll($pdo, 'SELECT * FROM news_sources WHERE is_active = 1 ORDER BY category, name');
$newsArticles = fetchNewsArticles($pdo);
if ($newsArticles === []) {
    $newsArticles = parseRssArticles($newsSources);
}
$newsCategory = trim((string) ($_GET['news_cat'] ?? ''));
$newsCategories = array_values(array_unique(array_map(
    static fn(array $a): string => formatCategoryLabel((string) ($a['category'] ?? '')),
    $newsArticles
)));
sort($newsCategories);
$newsList = $newsCategory !== ''
    ? array_values(array_filter(
        $newsArticles,
        static fn(array $a): bool => formatCategoryLabel((string) ($a['category'] ?? '')) === $newsCategory
    ))
    : $newsArticles;
$newsCategoryOptions = fetchAll($pdo, "SELECT id, label FROM lookup_values WHERE type = 'news' ORDER BY label = 'Other', label");
?>
                <section class="card stack">
                    <div class="toolbar">
                        <div class="toolbar-group">
                            <h2 style="margin: 0;">News</h2>
                            <span class="meta">Recent web articles for Palo Alto, schools, and civic coverage.</span>
                        </div>
                        <?php if ($currentUser): ?>
                            <div class="toolbar-group">
                                <label for="add-news" class="primary-btn">+ Add news</label>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="toolbar">
                        <div class="toolbar-group filter-group">
                            <span class="filter-label">Category:</span>
                            <a href="<?= h(queryWith(['news_cat' => null, 'show' => null])) ?>" class="<?= $newsCategory === '' ? 'is-active' : '' ?>">All</a>
                            <?php foreach ($newsCategories as $cat): ?>
                                <a href="<?= h(queryWith(['news_cat' => $cat, 'show' => null])) ?>" class="<?= $newsCategory === $cat ? 'is-active' : '' ?>"><?= h($cat) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php foreach (array_slice($newsList, 0, $showLimit) as $article): ?>
                        <?php $canManage = isset($article['id']) && userCanManage($currentUser, $article); ?>
                        <article class="panel<?= $canManage ? ' has-actions' : '' ?>">
                            <?php if ($canManage): ?>
                                <div class="card-actions">
                                    <label for="edit-news-<?= (int) $article['id'] ?>" class="mini-btn">Edit</label>
                                    <form method="post" class="card-actions-del" onsubmit="return confirm('Delete this article?');">
                                        <input type="hidden" name="action" value="delete_news">
                                        <input type="hidden" name="news_id" value="<?= (int) $article['id'] ?>">
                                        <button type="submit" class="mini-btn mini-danger">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            <details class="news-details">
                                <summary class="disclosure-summary">
                                    <div class="news-summary disclosure-label">
                                        <div class="news-title">
                                            <strong><?= h($article['title']) ?> <span class="news-source">(<?= h($article['source_name']) ?>)</span></strong>
                                            <span class="badge news-type-badge"><?= h(formatCategoryLabel((string) $article['category'])) ?></span>
                                        </div>
                                    </div>
                                    <span class="disclosure-arrow">›</span>
                                </summary>
                                <div class="news-body">
                                    <?php if ($article['summary'] !== ''): ?>
                                        <p><?= h(mb_strimwidth($article['summary'], 0, 280, '...')) ?></p>
                                    <?php endif; ?>
                                    <div class="meta"><a href="<?= h($article['link']) ?>" target="_blank" rel="noreferrer">More</a></div>
                                </div>
                            </details>
                        </article>
                        <?php if ($canManage): ?>
                            <input type="checkbox" id="edit-news-<?= (int) $article['id'] ?>" class="modal-toggle" hidden>
                            <div class="modal-backdrop">
                                <div class="modal">
                                    <div class="modal-head">
                                        <h2>Edit News</h2>
                                        <label for="edit-news-<?= (int) $article['id'] ?>" class="modal-x" aria-label="Close">×</label>
                                    </div>
                                    <form method="post" class="stack">
                                        <input type="hidden" name="action" value="update_news">
                                        <input type="hidden" name="news_id" value="<?= (int) $article['id'] ?>">
                                        <label>Title
                                            <input type="text" name="title" value="<?= h((string) $article['title']) ?>" required>
                                        </label>
                                        <label>Link
                                            <input type="url" name="link" value="<?= h((string) $article['link']) ?>">
                                        </label>
                                        <label>Category
                                            <select name="category_id" required>
                                                <?php foreach ($newsCategoryOptions as $nc): ?>
                                                    <option value="<?= (int) $nc['id'] ?>"<?= (int) ($article['category_id'] ?? 0) === (int) $nc['id'] ? ' selected' : '' ?>><?= h(formatCategoryLabel((string) $nc['label'])) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>Source
                                            <select name="news_source_id">
                                                <?php foreach ($newsSources as $source): ?>
                                                    <option value="<?= (int) $source['id'] ?>"<?= (int) ($article['news_source_id'] ?? 0) === (int) $source['id'] ? ' selected' : '' ?>><?= h((string) $source['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>Published at
                                            <input type="datetime-local" name="published_at" value="<?= h($article['published_at'] ? date('Y-m-d\TH:i', strtotime((string) $article['published_at'])) : '') ?>">
                                        </label>
                                        <label>Summary
                                            <textarea name="summary" rows="4"><?= h((string) $article['summary']) ?></textarea>
                                        </label>
                                        <div class="modal-actions">
                                            <label for="edit-news-<?= (int) $article['id'] ?>" class="btn-secondary">Cancel</label>
                                            <button type="submit">Save changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <span id="more-news" class="scroll-anchor"></span>
                    <?= showMoreLink(count($newsList), $showLimit, 'more-news') ?>
                    <?php if ($newsList === [] && $newsCategory !== ''): ?>
                        <p class="meta">No news in this category.</p>
                    <?php elseif ($newsArticles === []): ?>
                        <p class="meta">News feeds are temporarily unavailable. The source configuration is in the database and the page will repopulate when feed requests succeed.</p>
                    <?php endif; ?>
                </section>
<?php if ($currentUser): ?>
    <input type="checkbox" id="add-news" class="modal-toggle" hidden>
    <div class="modal-backdrop">
        <div class="modal">
            <div class="modal-head">
                <h2>Add News</h2>
                <label for="add-news" class="modal-x" aria-label="Close">×</label>
            </div>
            <form method="post" class="stack">
                <input type="hidden" name="action" value="create_news">
                <label>Title
                    <input type="text" name="title" required>
                </label>
                <label>Link
                    <input type="url" name="link">
                </label>
                <label>Category
                    <select name="category_id" required>
                        <?php foreach ($newsCategoryOptions as $nc): ?>
                            <option value="<?= (int) $nc['id'] ?>"><?= h(formatCategoryLabel((string) $nc['label'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Source
                    <select name="news_source_id">
                        <?php foreach ($newsSources as $source): ?>
                            <option value="<?= (int) $source['id'] ?>"><?= h((string) $source['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Published at
                    <input type="datetime-local" name="published_at">
                </label>
                <label>Summary
                    <textarea name="summary" rows="4"></textarea>
                </label>
                <div class="modal-actions">
                    <label for="add-news" class="btn-secondary">Cancel</label>
                    <button type="submit">Add news</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
