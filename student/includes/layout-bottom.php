<?php
/**
 * layout-bottom.php - closes the student page shell (design v2).
 * On the dashboard (index.php) it also renders the right-hand panel: streak/XP/level, what's due, calendar.
 */
?>
            </div> <!-- /dashboard-main -->
        </main>

        <?php if (basename($_SERVER['PHP_SELF']) == 'index.php'): ?>
        <aside class="right-panel" aria-label="Your week">
            <?php $ps = $panel_stats ?? ['streak' => (int)($student['streak_days'] ?? 0), 'xp' => (int)($student['xp'] ?? 0), 'level' => 1]; ?>
            <div class="sp-grid" style="grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px;">
                <div class="sp-card" style="padding: 12px; text-align: center;">
                    <div class="sp-ico orange" style="margin: 0 auto 6px; width: 34px; height: 34px;"><i class="fas fa-fire"></i></div>
                    <strong style="display: block; font-size: 1.1rem;"><?= (int)$ps['streak'] ?></strong>
                    <span class="sp-muted" style="font-size: .7rem; font-weight: 600;">Day streak</span>
                </div>
                <div class="sp-card" style="padding: 12px; text-align: center;">
                    <div class="sp-ico yellow" style="margin: 0 auto 6px; width: 34px; height: 34px;"><i class="fas fa-bolt"></i></div>
                    <strong style="display: block; font-size: 1.1rem;"><?= number_format((int)$ps['xp']) ?></strong>
                    <span class="sp-muted" style="font-size: .7rem; font-weight: 600;">XP</span>
                </div>
                <a href="achievements" class="sp-card" style="padding: 12px; text-align: center; text-decoration: none;">
                    <div class="sp-ico violet" style="margin: 0 auto 6px; width: 34px; height: 34px;"><i class="fas fa-medal"></i></div>
                    <strong style="display: block; font-size: 1.1rem;"><?= (int)$ps['level'] ?></strong>
                    <span class="sp-muted" style="font-size: .7rem; font-weight: 600;">Level</span>
                </a>
            </div>

            <!-- Coming up -->
            <div class="sp-card" style="margin-bottom: 16px; padding: 16px;">
                <div class="sp-card-head" style="margin-bottom: 6px;"><h4>Coming up</h4><a href="assignments" class="sp-link" style="font-size: .78rem;">All</a></div>
                <?php
                $up = [];
                foreach (($portal_assignments ?? []) as $a) $up[] = ['assignments', 'fa-file-pen', 'orange', $a['title'], $a['course_name'], $a['due_date'] ? 'Due ' . date('j M', strtotime($a['due_date'])) : ''];
                foreach (($portal_cats ?? []) as $q) $up[] = ['take-quiz?id=' . (int)$q['id'], 'fa-circle-question', '', $q['title'], $q['course_name'], 'Quiz'];
                foreach (array_filter($cal_events ?? [], fn($e) => substr((string)$e['event_date'], 0, 10) >= date('Y-m-d')) as $e) $up[] = ['#', 'fa-calendar-day', 'violet', $e['title'], 'Reminder', date('j M', strtotime($e['event_date']))];
                ?>
                <?php if (!$up): ?>
                    <p class="sp-muted sp-small" style="margin: 6px 0 2px;">Nothing due. Click a day on the calendar to add a reminder.</p>
                <?php else: ?>
                    <div class="sp-list">
                        <?php foreach (array_slice($up, 0, 5) as [$href, $ic, $col, $t, $sub, $when]): ?>
                        <a href="<?= htmlspecialchars($href) ?>" class="sp-list-item" style="text-decoration: none; padding: 10px 0;">
                            <span class="sp-ico <?= $col ?>" style="width: 34px; height: 34px;"><i class="fas <?= $ic ?>"></i></span>
                            <span class="sp-grow"><h5 class="sp-truncate"><?= htmlspecialchars($t) ?></h5><p class="sp-truncate"><?= htmlspecialchars($sub) ?></p></span>
                            <?php if ($when): ?><span class="sp-badge gray"><?= htmlspecialchars($when) ?></span><?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Calendar -->
            <div class="calendar-widget">
                <?php
                $currentDate = new DateTime();
                $cal_m = isset($_GET['cal_m']) ? max(1, min(12, (int)$_GET['cal_m'])) : (int)$currentDate->format('m');
                $cal_y = isset($_GET['cal_y']) ? max(2000, min(2100, (int)$_GET['cal_y'])) : (int)$currentDate->format('Y');
                $reqDate = new DateTime("$cal_y-$cal_m-01");
                $numDays = (int)$reqDate->format('t');
                $startDayOffset = (int)$reqDate->format('N') - 1;
                $prevDate = (clone $reqDate)->modify('-1 month');
                $nextDate = (clone $reqDate)->modify('+1 month');
                $isCurrentMonth = ($cal_m == (int)$currentDate->format('m') && $cal_y == (int)$currentDate->format('Y'));
                $todayNum = $isCurrentMonth ? (int)$currentDate->format('d') : null;
                ?>
                <div class="calendar-header">
                    <h4><?= $reqDate->format('F Y') ?></h4>
                    <div style="display: flex; gap: 6px;">
                        <a href="?cal_m=<?= $prevDate->format('n') ?>&cal_y=<?= $prevDate->format('Y') ?>" aria-label="Previous month"><i class="fas fa-chevron-left"></i></a>
                        <a href="?cal_m=<?= $nextDate->format('n') ?>&cal_y=<?= $nextDate->format('Y') ?>" aria-label="Next month"><i class="fas fa-chevron-right"></i></a>
                    </div>
                </div>
                <div class="calendar-grid">
                    <?php foreach (['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'] as $day): ?><div class="day-label"><?= $day ?></div><?php endforeach; ?>
                    <?php for ($i = 0; $i < $startDayOffset; $i++): ?><div></div><?php endfor; ?>
                    <?php for ($d = 1; $d <= $numDays; $d++):
                        $dayIso = sprintf('%04d-%02d-%02d', $cal_y, $cal_m, $d);
                        $dayEvents = array_filter($cal_events ?? [], fn($ce) => substr((string)$ce['event_date'], 0, 10) === $dayIso);
                        $dayTip = $dayEvents ? implode(', ', array_map(fn($ce) => $ce['title'], $dayEvents)) : 'Add a reminder';
                    ?>
                        <div class="day-num<?= $d == $todayNum ? ' active' : '' ?><?= $dayEvents ? ' has-event' : '' ?>" title="<?= htmlspecialchars($dayTip) ?>" onclick="if (typeof openEventModal === 'function') openEventModal('<?= $dayIso ?>')"><?= $d ?></div>
                    <?php endfor; ?>
                </div>
                <p class="sp-muted" style="font-size: .72rem; margin: 10px 0 0;">Tip: click a day to add a reminder.</p>
            </div>
        </aside>
        <?php endif; ?>
    </div> <!-- /dashboard-wrapper -->
</div> <!-- /main-content -->

<script src="../assets/js/main.js"></script>
</body>
</html>
