<?php
$events = fetchAll($pdo, 'SELECT e.*, u.name AS creator_name, t.name AS team_name, l.label AS category,
    (SELECT COUNT(*) FROM event_rsvps r WHERE r.event_id = e.id AND r.status = "going") AS going_count
    FROM events e
    JOIN users u ON u.id = e.created_by_user_id
    LEFT JOIN teams t ON t.id = e.team_id
    LEFT JOIN lookup_values l ON l.id = e.category_id
    ORDER BY ' . eventSortSql($eventSort, date('Y-m-d')));
$eventCategoryOptions = fetchAll($pdo, "SELECT id, label FROM lookup_values WHERE type = 'event' ORDER BY label = 'Other', label");
$eventCategories = array_map(static fn(array $o): string => (string) $o['label'], $eventCategoryOptions);
$eventList = $eventCategory !== ''
    ? array_values(array_filter($events, static fn(array $e): bool => (string) $e['category'] === $eventCategory))
    : $events;
$eventCalendar = buildEventCalendar($eventList, $calendarScale, $calendarAnchor);
$eventListView = $eventSort === 'past'
    ? array_values(array_filter($eventList, static fn(array $e): bool => strtotime((string) $e['ends_at']) < time()))
    : $eventList;
?>
                <section class="card stack">
                    <div class="toolbar">
                        <div class="toolbar-group">
                            <h2 style="margin: 0;">Events</h2>
                            <a href="<?= h(queryWith(['events_view' => 'list'])) ?>" class="<?= $eventView === 'list' ? 'is-active' : '' ?>">List</a>
                            <a href="<?= h(queryWith(['events_view' => 'calendar'])) ?>" class="<?= $eventView === 'calendar' ? 'is-active' : '' ?>">Calendar</a>
                            <?php if ($eventView === 'calendar'): ?>
                                <span class="toolbar-divider"></span>
                                <?php foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $scaleKey => $scaleLabel): ?>
                                    <a href="<?= h(queryWith(['events_cal' => $scaleKey])) ?>" class="<?= $calendarScale === $scaleKey ? 'is-active' : '' ?>"><?= $scaleLabel ?></a>
                                <?php endforeach; ?>
                                <span class="toolbar-divider"></span>
                                <a href="<?= h(queryWith(['events_week' => $eventCalendar['previous']])) ?>">‹ Prev</a>
                                <a href="<?= h(queryWith(['events_week' => date('Y-m-d')])) ?>">Today</a>
                                <a href="<?= h(queryWith(['events_week' => $eventCalendar['next']])) ?>">Next ›</a>
                            <?php endif; ?>
                        </div>
                        <?php if ($currentUser): ?>
                            <div class="toolbar-group">
                                <label for="add-event-toggle" class="primary-btn">+ Add event</label>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="toolbar">
                        <div class="toolbar-group filter-group">
                            <span class="filter-label">Type:</span>
                            <a href="<?= h(queryWith(['events_cat' => null, 'show' => null])) ?>" class="<?= $eventCategory === '' ? 'is-active' : '' ?>">All</a>
                            <?php foreach ($eventCategories as $cat): ?>
                                <a href="<?= h(queryWith(['events_cat' => $cat, 'show' => null])) ?>" class="<?= $eventCategory === $cat ? 'is-active' : '' ?>"><?= h($cat) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if ($eventView === 'calendar'): ?>
                        <div class="calendar-label"><strong><?= h($eventCalendar['label']) ?></strong></div>
                        <?php if ($eventCalendar['scale'] === 'day'): ?>
                            <?php $dayKey = $eventCalendar['days'][0]['key']; ?>
                            <?php $dayEvents = array_values(array_filter(
                                $eventCalendar['events_by_day'][$dayKey] ?? [],
                                static fn(array $e): bool => strtotime($dayKey . substr((string) $e['starts_at'], 10)) > time()
                            )); ?>
                            <div class="calendar-day-view">
                                <?php if ($dayEvents === []): ?>
                                    <p class="meta"><?= $dayKey === date('Y-m-d') ? 'No events today.' : 'No events scheduled for this day.' ?></p>
                                <?php else: ?>
                                    <?php foreach ($dayEvents as $event): ?>
                                        <a class="calendar-event" href="<?= h(queryWith(['events_view' => 'list', 'events_week' => null, 'events_cal' => null, 'focus' => (int) $event['id']]) . '#event-' . (int) $event['id']) ?>">
                                            <strong><?= h($event['title']) ?><?= recurrenceLabel((string) ($event['recurrence'] ?? 'none')) !== '' ? ' ↻' : '' ?></strong>
                                            <?= h((new DateTimeImmutable($event['starts_at']))->format('g:i A')) ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="calendar <?= $eventCalendar['scale'] === 'month' ? 'is-month' : '' ?>">
                                <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekdayName): ?>
                                    <div class="calendar-head"><?= h($weekdayName) ?></div>
                                <?php endforeach; ?>
                                <?php foreach ($eventCalendar['days'] as $day): ?>
                                    <?php $dayEvents = $eventCalendar['events_by_day'][$day['key']] ?? []; ?>
                                    <div class="calendar-day<?= $dayEvents === [] ? ' is-empty' : '' ?><?= !empty($day['outside']) ? ' is-outside' : '' ?>">
                                        <div class="calendar-date">
                                            <?= h($eventCalendar['scale'] === 'month' ? $day['day_number'] : $day['label']) ?>
                                            <?php if ($eventCalendar['scale'] !== 'month'): ?>
                                                <span class="meta"><?= h($day['weekday']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php foreach ($dayEvents as $event): ?>
                                            <a class="calendar-event<?= strtotime($day['key'] . substr((string) $event['starts_at'], 10)) <= time() ? ' is-past' : '' ?>" href="<?= h(queryWith(['events_view' => 'list', 'events_week' => null, 'events_cal' => null, 'focus' => (int) $event['id']]) . '#event-' . (int) $event['id']) ?>">
                                                <strong><?= h($event['title']) ?><?= recurrenceLabel((string) ($event['recurrence'] ?? 'none')) !== '' ? ' ↻' : '' ?></strong>
                                                <?= h((new DateTimeImmutable($event['starts_at']))->format('g:i A')) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php
                        $nowTs = time();
                        $todayStr = date('Y-m-d');
                        $todayUpcoming = $todayPast = $futureEvents = $oldEvents = [];
                        foreach ($eventListView as $e) {
                            $dayStr = substr((string) $e['starts_at'], 0, 10);
                            if ($dayStr === $todayStr) {
                                if (strtotime((string) $e['starts_at']) <= $nowTs) {
                                    $todayPast[] = $e;
                                } else {
                                    $todayUpcoming[] = $e;
                                }
                            } elseif ($dayStr > $todayStr) {
                                $futureEvents[] = $e;
                            } else {
                                $oldEvents[] = $e;
                            }
                        }
                        $futureLimit = $focusEvent ? count($futureEvents) : $showLimit;
                        $focusInOld = false;
                        foreach ($oldEvents as $oldEvent) {
                            if ((int) $oldEvent['id'] === $focusEvent) {
                                $focusInOld = true;
                                break;
                            }
                        }
                        $focusInTodayPast = false;
                        foreach ($todayPast as $todayPastEvent) {
                            if ((int) $todayPastEvent['id'] === $focusEvent) {
                                $focusInTodayPast = true;
                                break;
                            }
                        }
                        ?>
                        <?php if ($eventListView === []): ?>
                            <p class="meta">No events match this filter.</p>
                        <?php endif; ?>

                        <?php if ($todayUpcoming !== [] || $todayPast !== []): ?>
                            <?php if ($todayPast !== []): ?>
                                <input type="checkbox" id="today-toggle" class="today-toggle" hidden<?= $focusInTodayPast ? ' checked' : '' ?>>
                                <label for="today-toggle" class="day-heading today-heading">Today+ (<?= count($todayPast) ?> earlier)</label>
                            <?php else: ?>
                                <div class="day-heading">Today</div>
                            <?php endif; ?>
                            <?php foreach ($todayUpcoming as $event): ?>
                                <?php renderEventPanel($event, $focusEvent, (int) ($currentUser["id"] ?? 0), $teamOptions, $eventCategoryOptions, isAdminUser($currentUser)); ?>
                            <?php endforeach; ?>
                            <?php if ($todayPast !== []): ?>
                                <div class="today-past stack">
                                    <?php foreach ($todayPast as $event): ?>
                                        <?php renderEventPanel($event, $focusEvent, (int) ($currentUser["id"] ?? 0), $teamOptions, $eventCategoryOptions, isAdminUser($currentUser)); ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php $previousEventDay = null; ?>
                        <?php foreach (array_slice($futureEvents, 0, $futureLimit) as $event): ?>
                            <?php $eventDayKey = (new DateTimeImmutable($event['starts_at']))->format('Y-m-d'); ?>
                            <?php if ($previousEventDay !== $eventDayKey): ?>
                                <div class="day-heading"><?= h(formatEventGroupLabel($eventDayKey)) ?></div>
                            <?php endif; ?>
                            <?php renderEventPanel($event, $focusEvent, (int) ($currentUser["id"] ?? 0), $teamOptions, $eventCategoryOptions, isAdminUser($currentUser)); ?>
                            <?php $previousEventDay = $eventDayKey; ?>
                        <?php endforeach; ?>
                        <span id="more-events" class="scroll-anchor"></span>
                        <?= showMoreLink(count($futureEvents), $futureLimit, 'more-events') ?>

                        <?php if ($oldEvents !== []): ?>
                            <details class="old-events"<?= $focusInOld ? ' open' : '' ?>>
                                <summary class="disclosure-summary">
                                    <span class="disclosure-label">Old (<?= count($oldEvents) ?>)</span>
                                    <span class="disclosure-arrow">›</span>
                                </summary>
                                <div class="stack">
                                    <?php $previousOldDay = null; ?>
                                    <?php foreach ($oldEvents as $event): ?>
                                        <?php $eventDayKey = (new DateTimeImmutable($event['starts_at']))->format('Y-m-d'); ?>
                                        <?php if ($previousOldDay !== $eventDayKey): ?>
                                            <div class="day-heading"><?= h(formatEventGroupLabel($eventDayKey)) ?></div>
                                        <?php endif; ?>
                                        <?php renderEventPanel($event, $focusEvent, (int) ($currentUser["id"] ?? 0), $teamOptions, $eventCategoryOptions, isAdminUser($currentUser)); ?>
                                        <?php $previousOldDay = $eventDayKey; ?>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
<?php if ($currentUser): ?>
    <input type="checkbox" id="add-event-toggle" class="modal-toggle" hidden>
    <div class="modal-backdrop">
        <div class="modal">
            <div class="modal-head">
                <h2>Add Event</h2>
                <label for="add-event-toggle" class="modal-x" aria-label="Close">×</label>
            </div>
            <form method="post" class="stack">
                <input type="hidden" name="action" value="create_event">
                <?php eventFormFields([], $teamOptions, $eventCategoryOptions); ?>
                <div class="modal-actions">
                    <label for="add-event-toggle" class="btn-secondary">Cancel</label>
                    <button type="submit">Add event</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
