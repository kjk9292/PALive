<?php

declare(strict_types=1);

// Community news.
function handleNewsActions(PDO $pdo, array $user, string $action): void
{
    if ($action === 'create_news') {
        $stmt = $pdo->prepare(
            'INSERT INTO news (news_source_id, category_id, title, link, summary, published_at, created_by_user_id)
             VALUES (:news_source_id, :category_id, :title, :link, :summary, :published_at, :created_by_user_id)'
        );
        $publishedAt = trim((string) ($_POST['published_at'] ?? ''));
        $stmt->execute([
            'news_source_id' => (int) $_POST['news_source_id'],
            'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
            'title' => trim((string) $_POST['title']),
            'link' => trim((string) $_POST['link']),
            'summary' => trim((string) ($_POST['summary'] ?? '')),
            'published_at' => $publishedAt === '' ? null : normalizeDateTimeLocal($publishedAt),
            'created_by_user_id' => $user['id'],
        ]);
        flash('News article created.');
        redirect('?page=news');
    }

    if ($action === 'update_news') {
        $publishedAt = trim((string) ($_POST['published_at'] ?? ''));
        $params = [
            'news_source_id' => (int) $_POST['news_source_id'],
            'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
            'title' => trim((string) $_POST['title']),
            'link' => trim((string) $_POST['link']),
            'summary' => trim((string) ($_POST['summary'] ?? '')),
            'published_at' => $publishedAt === '' ? null : normalizeDateTimeLocal($publishedAt),
            'id' => (int) $_POST['news_id'],
        ];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND created_by_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare(
            'UPDATE news SET news_source_id = :news_source_id, category_id = :category_id, title = :title,
                link = :link, summary = :summary, published_at = :published_at
             WHERE ' . $where
        );
        $stmt->execute($params);
        flash('News article updated.');
        redirect('?page=news');
    }

    if ($action === 'delete_news') {
        $params = ['id' => (int) $_POST['news_id']];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND created_by_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare('DELETE FROM news WHERE ' . $where);
        $stmt->execute($params);
        flash('News article deleted.');
        redirect('?page=news');
    }
}
