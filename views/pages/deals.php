<?php
$deals = fetchAll($pdo, 'SELECT d.*, u.name AS creator_name, l.label AS category
    FROM deals d
    JOIN users u ON u.id = d.created_by_user_id
    LEFT JOIN lookup_values l ON l.id = d.category_id
    ORDER BY d.expires_at ASC, d.created_at DESC');
$dealCategories = fetchAll($pdo, "SELECT id, label FROM lookup_values WHERE type = 'deal' ORDER BY label = 'Other', label");
?>
                <section class="card stack">
                    <div class="toolbar">
                        <div class="toolbar-group">
                            <h2 style="margin: 0;">Deals</h2>
                        </div>
                        <?php if ($currentUser): ?>
                            <div class="toolbar-group">
                                <label for="add-deal-toggle" class="primary-btn">+ Add deal</label>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php foreach (array_slice($deals, 0, $showLimit) as $deal): ?>
                        <?php $canManage = userCanManage($currentUser, $deal, 'created_by_user_id'); ?>
                        <article class="panel<?= $canManage ? ' has-actions' : '' ?>">
                            <?php if ($canManage): ?>
                                <div class="card-actions">
                                    <label for="edit-deal-<?= (int) $deal['id'] ?>" class="mini-btn">Edit</label>
                                    <form method="post" class="card-actions-del" onsubmit="return confirm('Delete this deal?');">
                                        <input type="hidden" name="action" value="delete_deal">
                                        <input type="hidden" name="deal_id" value="<?= (int) $deal['id'] ?>">
                                        <button type="submit" class="mini-btn mini-danger">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            <div class="inline">
                                <strong><?= h($deal['title']) ?></strong>
                                <span class="badge"><?= h($deal['category']) ?></span>
                                <span class="badge deal-expiry">Expires <?= h((new DateTimeImmutable($deal['expires_at']))->format('M j, Y')) ?></span>
                            </div>
                            <p><?= h($deal['description']) ?></p>
                            <div class="meta"><?= h($deal['business_name']) ?></div>
                            <?php if (!empty($deal['website'])): ?>
                                <div class="meta"><a href="<?= h($deal['website']) ?>" target="_blank" rel="noreferrer">Website</a></div>
                            <?php endif; ?>
                        </article>
                        <?php if ($canManage): ?>
                            <input type="checkbox" id="edit-deal-<?= (int) $deal['id'] ?>" class="modal-toggle" hidden>
                            <div class="modal-backdrop">
                                <div class="modal">
                                    <div class="modal-head">
                                        <h2>Edit Deal</h2>
                                        <label for="edit-deal-<?= (int) $deal['id'] ?>" class="modal-x" aria-label="Close">×</label>
                                    </div>
                                    <form method="post" class="stack">
                                        <input type="hidden" name="action" value="update_deal">
                                        <input type="hidden" name="deal_id" value="<?= (int) $deal['id'] ?>">
                                        <?php dealFormFields($deal, $dealCategories); ?>
                                        <div class="modal-actions">
                                            <label for="edit-deal-<?= (int) $deal['id'] ?>" class="btn-secondary">Cancel</label>
                                            <button type="submit">Save changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <span id="more-deals" class="scroll-anchor"></span>
                    <?= showMoreLink(count($deals), $showLimit, 'more-deals') ?>
                </section>
<?php if ($currentUser): ?>
    <input type="checkbox" id="add-deal-toggle" class="modal-toggle" hidden>
    <div class="modal-backdrop">
        <div class="modal">
            <div class="modal-head">
                <h2>Add Deal</h2>
                <label for="add-deal-toggle" class="modal-x" aria-label="Close">×</label>
            </div>
            <form method="post" class="stack">
                <input type="hidden" name="action" value="create_deal">
                <?php dealFormFields([], $dealCategories); ?>
                <div class="modal-actions">
                    <label for="add-deal-toggle" class="btn-secondary">Cancel</label>
                    <button type="submit">Add deal</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
