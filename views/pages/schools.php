<?php
$schools = fetchAll($pdo, 'SELECT s.*, u.name AS creator_name FROM schools s JOIN users u ON u.id = s.created_by_user_id ORDER BY s.level, s.school_type, s.name');
$schoolDirectoryGroups = groupSchoolsForDirectory($schools);
?>
                <section class="card stack">
                    <h2>Local School Directory</h2>
                    <?php foreach ($schoolDirectoryGroups as $group): ?>
                        <details class="directory-group">
                            <summary class="disclosure-summary">
                                <span class="disclosure-label"><?= h($group['label']) ?> (<?= count($group['items']) ?>)</span>
                                <span class="disclosure-arrow">›</span>
                            </summary>
                            <div class="stack">
                                <?php foreach ($group['items'] as $school): ?>
                                    <a class="card-link" href="?page=school&school_id=<?= (int) $school['id'] ?>">
                                        <article class="panel">
                                            <div class="inline">
                                                <strong><?= h($school['name']) ?></strong>
                                                <span class="badge"><?= h($school['school_type']) ?></span>
                                            </div>
                                            <div class="meta"><?= ucfirst(h($school['level'])) ?><?= $school['neighborhood'] !== '' ? ' • ' . h($school['neighborhood']) : '' ?></div>
                                            <div class="action-row">
                                                <div class="meta">Schedules and school events</div>
                                                <span class="action-link">Open<span class="action-arrow">+</span></span>
                                            </div>
                                        </article>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </section>
