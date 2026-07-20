<?php
$resources = fetchAll($pdo, 'SELECT r.*, u.name AS creator_name, l.label AS category
    FROM resources r
    JOIN users u ON u.id = r.created_by_user_id
    LEFT JOIN lookup_values l ON l.id = r.category_id
    ORDER BY r.title ASC');
$resourceCategoryOptions = fetchAll($pdo, "SELECT id, label FROM lookup_values WHERE type = 'resource' ORDER BY label = 'Other', label");
?>
                <section class="card stack">
                    <div class="toolbar">
                        <div class="toolbar-group">
                            <h2>City Resources</h2>
                        </div>
                        <?php if ($currentUser): ?>
                            <label for="add-resource" class="primary-btn">+ Add Resource</label>
                        <?php endif; ?>
                    </div>

                    <?php if ($currentUser): ?>
                        <input type="checkbox" id="add-resource" class="modal-toggle" hidden>
                        <div class="modal-backdrop">
                            <div class="modal">
                                <div class="modal-head">
                                    <h2>Add Resource</h2>
                                    <label for="add-resource" class="modal-x" aria-label="Close">×</label>
                                </div>
                                <form method="post" class="stack">
                                    <input type="hidden" name="action" value="create_resource">
                                    <label>Title
                                        <input type="text" name="title" required>
                                    </label>
                                    <label>Category
                                        <select name="category_id" required>
                                            <option value="">Select category</option>
                                            <?php foreach ($resourceCategoryOptions as $rc): ?>
                                                <option value="<?= (int) $rc['id'] ?>"><?= h((string) $rc['label']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Contact name
                                        <input type="text" name="contact_name">
                                    </label>
                                    <label>Phone
                                        <input type="text" name="phone">
                                    </label>
                                    <label>Website
                                        <input type="url" name="website">
                                    </label>
                                    <label>Details
                                        <textarea name="details" rows="4"></textarea>
                                    </label>
                                    <div class="modal-actions">
                                        <label for="add-resource" class="btn-secondary">Cancel</label>
                                        <button type="submit">Create</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($resources as $resource): ?>
                        <?php $canManage = userCanManage($currentUser, $resource, 'created_by_user_id'); ?>
                        <article class="panel<?= $canManage ? ' has-actions' : '' ?>">
                            <?php if ($canManage): ?>
                                <div class="card-actions">
                                    <label for="edit-resource-<?= (int) $resource['id'] ?>" class="mini-btn">Edit</label>
                                    <form method="post" class="card-actions-del" onsubmit="return confirm('Delete this resource?');">
                                        <input type="hidden" name="action" value="delete_resource">
                                        <input type="hidden" name="resource_id" value="<?= (int) $resource['id'] ?>">
                                        <button type="submit" class="mini-btn mini-danger">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            <details class="resource-details">
                                <summary class="resource-summary disclosure-summary">
                                    <div class="disclosure-label">
                                        <div class="inline">
                                            <strong><?= h($resource['title']) ?></strong>
                                            <span class="badge"><?= h($resource['category']) ?></span>
                                        </div>
                                        <div class="meta"><?= h($resource['contact_name']) ?> • <?= h($resource['phone']) ?></div>
                                    </div>
                                    <span class="disclosure-arrow">›</span>
                                </summary>
                                <div class="resource-body">
                                    <p><?= h($resource['details']) ?></p>
                                    <div class="meta">
                                        <?= h($resource['contact_name']) ?> • <?= h($resource['phone']) ?>
                                        <?php if ($resource['website'] !== ''): ?>
                                            • <a href="<?= h($resource['website']) ?>" target="_blank" rel="noreferrer">Website</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </details>
                        </article>
                        <?php if ($canManage): ?>
                            <input type="checkbox" id="edit-resource-<?= (int) $resource['id'] ?>" class="modal-toggle" hidden>
                            <div class="modal-backdrop">
                                <div class="modal">
                                    <div class="modal-head">
                                        <h2>Edit Resource</h2>
                                        <label for="edit-resource-<?= (int) $resource['id'] ?>" class="modal-x" aria-label="Close">×</label>
                                    </div>
                                    <form method="post" class="stack">
                                        <input type="hidden" name="action" value="update_resource">
                                        <input type="hidden" name="resource_id" value="<?= (int) $resource['id'] ?>">
                                        <label>Title
                                            <input type="text" name="title" value="<?= h($resource['title']) ?>" required>
                                        </label>
                                        <label>Category
                                            <select name="category_id" required>
                                                <?php foreach ($resourceCategoryOptions as $rc): ?>
                                                    <option value="<?= (int) $rc['id'] ?>"<?= (int) ($resource['category_id'] ?? 0) === (int) $rc['id'] ? ' selected' : '' ?>><?= h((string) $rc['label']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>Contact name
                                            <input type="text" name="contact_name" value="<?= h($resource['contact_name']) ?>">
                                        </label>
                                        <label>Phone
                                            <input type="text" name="phone" value="<?= h($resource['phone']) ?>">
                                        </label>
                                        <label>Website
                                            <input type="url" name="website" value="<?= h($resource['website']) ?>">
                                        </label>
                                        <label>Details
                                            <textarea name="details" rows="4"><?= h($resource['details']) ?></textarea>
                                        </label>
                                        <div class="modal-actions">
                                            <label for="edit-resource-<?= (int) $resource['id'] ?>" class="btn-secondary">Cancel</label>
                                            <button type="submit">Save changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </section>
