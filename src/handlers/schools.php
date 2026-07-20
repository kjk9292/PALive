<?php

declare(strict_types=1);

// Schools, schedules, and school events (admin-only).
function handleSchoolActions(PDO $pdo, array $user, string $action): void
{
    if ($action === 'create_school') {
        $stmt = $pdo->prepare(
            'INSERT INTO schools (name, school_type, level, neighborhood, website, created_by_user_id)
             VALUES (:name, :school_type, :level, :neighborhood, :website, :created_by_user_id)'
        );
        $stmt->execute([
            'name' => trim((string) $_POST['name']),
            'school_type' => ($_POST['school_type'] ?? '') === 'private' ? 'private' : 'public',
            'level' => in_array($_POST['level'] ?? '', ['elementary', 'middle', 'high'], true) ? $_POST['level'] : 'elementary',
            'neighborhood' => trim((string) ($_POST['neighborhood'] ?? '')),
            'website' => trim((string) ($_POST['website'] ?? '')),
            'created_by_user_id' => $user['id'],
        ]);
        flash('School added.');
        redirect('?page=schools');
    }

    if ($action === 'create_school_schedule') {
        $stmt = $pdo->prepare(
            'INSERT INTO school_schedules (school_id, label, days, start_time, end_time, notes)
             VALUES (:school_id, :label, :days, :start_time, :end_time, :notes)'
        );
        $stmt->execute([
            'school_id' => (int) $_POST['school_id'],
            'label' => trim((string) $_POST['label']),
            'days' => trim((string) $_POST['days']),
            'start_time' => normalizeTime((string) $_POST['start_time']),
            'end_time' => normalizeTime((string) $_POST['end_time']),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ]);
        createNotification($pdo, (int) $user['id'], 'school_schedule', 'School schedule saved.', '?page=schools');
        flash('School schedule added.');
        redirect('?page=schools');
    }

    if ($action === 'create_school_event') {
        $stmt = $pdo->prepare(
            'INSERT INTO school_events (school_id, title, description, location, starts_at, ends_at, created_by_user_id)
             VALUES (:school_id, :title, :description, :location, :starts_at, :ends_at, :created_by_user_id)'
        );
        $stmt->execute([
            'school_id' => (int) $_POST['school_id'],
            'title' => trim((string) $_POST['title']),
            'description' => trim((string) $_POST['description']),
            'location' => trim((string) $_POST['location']),
            'starts_at' => normalizeDateTimeLocal((string) $_POST['starts_at']),
            'ends_at' => normalizeDateTimeLocal((string) $_POST['ends_at']),
            'created_by_user_id' => $user['id'],
        ]);
        createNotification($pdo, (int) $user['id'], 'school_event', 'School event published.', '?page=schools');
        flash('School event added.');
        redirect('?page=schools');
    }

    if ($action === 'update_school') {
        if (!isAdminUser($user)) {
            throw new RuntimeException('Not authorized.');
        }
        $stmt = $pdo->prepare(
            'UPDATE schools SET name = :name, school_type = :school_type, level = :level,
                neighborhood = :neighborhood, website = :website
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => trim((string) $_POST['name']),
            'school_type' => ($_POST['school_type'] ?? '') === 'private' ? 'private' : 'public',
            'level' => in_array($_POST['level'] ?? '', ['elementary', 'middle', 'high'], true) ? $_POST['level'] : 'elementary',
            'neighborhood' => trim((string) ($_POST['neighborhood'] ?? '')),
            'website' => trim((string) ($_POST['website'] ?? '')),
            'id' => (int) $_POST['school_id'],
        ]);
        flash('School updated.');
        redirect('?page=schools');
    }

    if ($action === 'delete_school') {
        if (!isAdminUser($user)) {
            throw new RuntimeException('Not authorized.');
        }
        $stmt = $pdo->prepare('DELETE FROM schools WHERE id = :id');
        $stmt->execute(['id' => (int) $_POST['school_id']]);
        flash('School deleted.');
        redirect('?page=schools');
    }

    if ($action === 'update_school_schedule') {
        if (!isAdminUser($user)) {
            throw new RuntimeException('Not authorized.');
        }
        $stmt = $pdo->prepare(
            'UPDATE school_schedules SET label = :label, days = :days,
                start_time = :start_time, end_time = :end_time, notes = :notes
             WHERE id = :id'
        );
        $stmt->execute([
            'label' => trim((string) $_POST['label']),
            'days' => trim((string) $_POST['days']),
            'start_time' => normalizeTime((string) $_POST['start_time']),
            'end_time' => normalizeTime((string) $_POST['end_time']),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'id' => (int) $_POST['schedule_id'],
        ]);
        flash('School schedule updated.');
        redirect('?page=schools');
    }

    if ($action === 'delete_school_schedule') {
        if (!isAdminUser($user)) {
            throw new RuntimeException('Not authorized.');
        }
        $stmt = $pdo->prepare('DELETE FROM school_schedules WHERE id = :id');
        $stmt->execute(['id' => (int) $_POST['schedule_id']]);
        flash('School schedule deleted.');
        redirect('?page=schools');
    }

    if ($action === 'update_school_event') {
        if (!isAdminUser($user)) {
            throw new RuntimeException('Not authorized.');
        }
        $stmt = $pdo->prepare(
            'UPDATE school_events SET title = :title, description = :description, location = :location,
                starts_at = :starts_at, ends_at = :ends_at
             WHERE id = :id'
        );
        $stmt->execute([
            'title' => trim((string) $_POST['title']),
            'description' => trim((string) $_POST['description']),
            'location' => trim((string) $_POST['location']),
            'starts_at' => normalizeDateTimeLocal((string) $_POST['starts_at']),
            'ends_at' => normalizeDateTimeLocal((string) $_POST['ends_at']),
            'id' => (int) $_POST['school_event_id'],
        ]);
        flash('School event updated.');
        redirect('?page=schools');
    }

    if ($action === 'delete_school_event') {
        if (!isAdminUser($user)) {
            throw new RuntimeException('Not authorized.');
        }
        $stmt = $pdo->prepare('DELETE FROM school_events WHERE id = :id');
        $stmt->execute(['id' => (int) $_POST['school_event_id']]);
        flash('School event deleted.');
        redirect('?page=schools');
    }
}
