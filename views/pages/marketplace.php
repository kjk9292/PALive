<?php
$classifieds = fetchAll($pdo, 'SELECT c.*, u.name AS creator_name FROM classifieds c JOIN users u ON u.id = c.created_by_user_id ORDER BY c.expires_at ASC, c.created_at DESC');

$listingTypes = ['item', 'service', 'job', 'housing'];

$classifiedFields = static function (array $row) use ($listingTypes): void {
    $expires = (string) ($row['expires_at'] ?? '');
    $expiresLocal = $expires !== '' ? str_replace(' ', 'T', substr($expires, 0, 16)) : '';
    ?>
    <label class="field"><span>Title</span>
        <input type="text" name="title" value="<?= h((string) ($row['title'] ?? '')) ?>" required>
    </label>
    <label class="field"><span>Listing type</span>
        <select name="listing_type">
            <?php foreach ($listingTypes as $type): ?>
                <option value="<?= h($type) ?>"<?= ($row['listing_type'] ?? '') === $type ? ' selected' : '' ?>><?= h(ucfirst($type)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="field"><span>Price</span>
        <input type="number" step="0.01" name="price" value="<?= isset($row['price']) && $row['price'] !== null ? h((string) $row['price']) : '' ?>">
    </label>
    <label class="field"><span>Neighborhood</span>
        <input type="text" name="neighborhood" value="<?= h((string) ($row['neighborhood'] ?? '')) ?>">
    </label>
    <label class="field"><span>Description</span>
        <textarea name="description" required><?= h((string) ($row['description'] ?? '')) ?></textarea>
    </label>
    <label class="field"><span>Expires</span>
        <input type="datetime-local" name="expires_at" value="<?= h($expiresLocal) ?>">
    </label>
    <?php
};
?>
                <section class="card stack">
                    <div class="toolbar">
                        <div class="toolbar-group"></div>
                        <?php if ($currentUser): ?>
                            <label for="add-classified" class="primary-btn">+ Add listing</label>
                        <?php endif; ?>
                    </div>
                    <h2>Classifieds</h2>

                    <?php if ($currentUser): ?>
                        <input type="checkbox" id="add-classified" class="modal-toggle" hidden>
                        <div class="modal-backdrop">
                            <div class="modal">
                                <div class="modal-head">
                                    <h2>Add Listing</h2>
                                    <label for="add-classified" class="modal-x" aria-label="Close">&times;</label>
                                </div>
                                <form method="post" class="stack">
                                    <input type="hidden" name="action" value="create_classified">
                                    <?php $classifiedFields([]); ?>
                                    <div class="modal-actions">
                                        <label for="add-classified" class="btn-secondary">Cancel</label>
                                        <button type="submit">Create</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($classifieds as $classified): ?>
                        <?php $canManage = userCanManage($currentUser, $classified, 'created_by_user_id'); ?>
                        <article class="panel<?= $canManage ? ' has-actions' : '' ?>">
                            <?php if ($canManage): ?>
                                <div class="card-actions">
                                    <label for="edit-classified-<?= (int) $classified['id'] ?>" class="mini-btn">Edit</label>
                                    <form method="post" class="card-actions-del" onsubmit="return confirm('Delete this classified?');">
                                        <input type="hidden" name="action" value="delete_classified">
                                        <input type="hidden" name="classified_id" value="<?= (int) $classified['id'] ?>">
                                        <button type="submit" class="mini-btn mini-danger">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            <div class="inline">
                                <strong><?= h($classified['title']) ?></strong>
                                <span class="badge"><?= h($classified['listing_type']) ?></span>
                            </div>
                            <p><?= h($classified['description']) ?></p>
                            <div class="meta">
                                <?= $classified['price'] !== null ? '$' . h(number_format((float) $classified['price'], 2)) . ' • ' : '' ?>
                                <?= h((string) $classified['neighborhood']) ?>
                                <?= $classified['neighborhood'] !== '' ? ' • ' : '' ?>
                                Posted by <?= h($classified['creator_name']) ?> • Expires <?= h($classified['expires_at']) ?>
                            </div>
                        </article>
                        <?php if ($canManage): ?>
                            <input type="checkbox" id="edit-classified-<?= (int) $classified['id'] ?>" class="modal-toggle" hidden>
                            <div class="modal-backdrop">
                                <div class="modal">
                                    <div class="modal-head">
                                        <h2>Edit Listing</h2>
                                        <label for="edit-classified-<?= (int) $classified['id'] ?>" class="modal-x" aria-label="Close">&times;</label>
                                    </div>
                                    <form method="post" class="stack">
                                        <input type="hidden" name="action" value="update_classified">
                                        <input type="hidden" name="classified_id" value="<?= (int) $classified['id'] ?>">
                                        <?php $classifiedFields($classified); ?>
                                        <div class="modal-actions">
                                            <label for="edit-classified-<?= (int) $classified['id'] ?>" class="btn-secondary">Cancel</label>
                                            <button type="submit">Save changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </section>
