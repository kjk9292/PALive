<?php

declare(strict_types=1);

// Request input parsing: normalize the query-string parameters that drive page
// selection, the events list/calendar views, and pagination into typed values.

$page = $_GET['page'] ?? 'dashboard';
$schoolId = isset($_GET['school_id']) ? (int) $_GET['school_id'] : 0;
$eventView = ($_GET['events_view'] ?? 'list') === 'calendar' ? 'calendar' : 'list';
$eventSort = $_GET['events_sort'] ?? 'upcoming';
$calendarScale = $_GET['events_cal'] ?? 'day';
if (!in_array($calendarScale, ['day', 'week', 'month'], true)) {
    $calendarScale = 'day';
}
$calendarAnchor = trim((string) ($_GET['events_week'] ?? ''));
$eventCategory = trim((string) ($_GET['events_cat'] ?? ''));
$showLimit = max(20, (int) ($_GET['show'] ?? 20));
$focusEvent = (int) ($_GET['focus'] ?? 0);
