<?php
/**
 * layout-bottom.php
 * Unified right sidebar and layout closing for the student portal.
 * Use: require_once 'includes/layout-bottom.php'; at the bottom of your page content.
 */
?>
            </div> <!-- End dashboard-main -->
        </main>

        <?php if (basename($_SERVER['PHP_SELF']) == 'index.php'): ?>
        <!-- Right Interaction Panel -->
        <aside class="right-panel">
            <div class="desktop-top-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; gap: 24px;">
                <form action="search.php" method="GET" class="search-box" style="flex: 1; max-width: 540px; position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: var(--text-dim); font-size: 0.9rem;"></i>
                    <input type="text" name="q" placeholder="Search for anything .." value="<?= htmlspecialchars($q ?? '') ?>" style="width: 100%; padding: 16px 20px 16px 54px; border: 1px solid var(--border); border-radius: 18px; outline: none; transition: 0.3s; font-size: 0.92rem; font-family: inherit; background: #f8fafc;">
                </form>
                <div style="display: flex; align-items: center; gap: 14px; background: rgba(241, 245, 249, 0.5); padding: 6px 12px; border-radius: 20px; border: 1px solid rgba(241, 245, 249, 0.8);">
                    <!-- Calendar (Desktop) -->
                    <div style="position: relative;">
                        <div style="width: 46px; height: 46px; border-radius: 14px; background: white; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; transition: 0.2s; box-shadow: 0 2px 6px rgba(0,0,0,0.02);" onclick="const d = document.getElementById('calDropdownD'); d.style.display = d.style.display === 'none' ? 'flex' : 'none'; event.stopPropagation();">
                            <i class="far fa-calendar-alt" style="font-size: 1.1rem;"></i>
                        </div>
                        <div id="calDropdownD" style="display: none; flex-direction: column; position: absolute; top: 60px; right: -40px; background: white; border: 1px solid var(--border); border-radius: 16px; padding: 16px; width: 280px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); z-index: 100;">
                            <h6 style="margin: 0 0 12px 0; font-size: 0.85rem; font-weight: 800; color: var(--text-main);">Scheduled Activities</h6>
                            <?php if(empty($cal_events)): ?>
                                <div style="font-size: 0.8rem; color: var(--text-dim); text-align: center; padding: 20px 0;">No calendar events yet.</div>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: 8px; max-height: 200px; overflow-y: auto;">
                                    <?php foreach($cal_events as $ce): ?>
                                        <div style="padding: 10px; background: #f8fafc; border-left: 3px solid var(--primary); border-radius: 6px;">
                                            <strong style="display: block; font-size: 0.8rem;"><?= htmlspecialchars($ce['title']) ?></strong>
                                            <span style="font-size: 0.7rem; color: var(--text-dim);"><i class="far fa-clock"></i> <?= $ce['event_date'] ?> • <?= ucfirst($ce['category']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Notifications (Desktop) -->
                    <div style="position: relative;">
                        <div style="width: 46px; height: 46px; border-radius: 14px; background: white; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; position: relative; cursor: pointer; color: #64748b; transition: 0.2s; box-shadow: 0 2px 6px rgba(0,0,0,0.02);" onclick="const d = document.getElementById('notifDropdownD'); d.style.display = d.style.display === 'none' ? 'flex' : 'none'; event.stopPropagation();">
                            <i class="far fa-bell" style="font-size: 1.1rem;"></i>
                            <?php if($unread_count > 0): ?>
                                <span style="position: absolute; top: -4px; right: -4px; min-width: 18px; height: 18px; background: #ef4444; border-radius: 50%; border: 2px solid white; font-size: 0.6rem; color: white; font-weight: 900; display: flex; align-items: center; justify-content: center;"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                            <?php endif; ?>
                        </div>
                        <div id="notifDropdownD" style="display: none; flex-direction: column; position: absolute; top: 60px; right: -10px; background: white; border: 1px solid var(--border); border-radius: 16px; padding: 16px; width: 320px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); z-index: 100;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                                <h6 style="margin: 0; font-size: 0.85rem; font-weight: 800; color: var(--text-main);">Notifications</h6>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 8px; max-height: 300px; overflow-y: auto;">
                            <?php if(empty($live_notifications)): ?>
                                <div style="text-align: center; padding: 24px 0; font-size: 0.82rem; color: var(--text-dim);">No notifications yet.</div>
                            <?php else: foreach(array_slice($live_notifications, 0, 6) as $n): ?>
                                <div style="display: flex; gap: 12px; padding: 12px; background: <?= empty($n['read_status']) ? '#F0F9FF' : '#f8fafc' ?>; border-radius: 12px; border-left: 3px solid <?= empty($n['read_status']) ? 'var(--primary)' : 'transparent' ?>;">
                                    <div style="flex: 1; min-width: 0;">
                                        <strong style="display: block; font-size: 0.78rem; font-weight: 800;"><?= htmlspecialchars($n['title'] ?? 'Notice') ?></strong>
                                        <span style="font-size: 0.7rem; color: var(--text-dim); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars($n['message']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                            </div>
                            <a href="notifications.php" style="display: block; text-align: center; margin-top: 12px; font-size: 0.78rem; font-weight: 800; color: var(--primary); text-decoration: none; padding: 10px; background: #E0F7FF; border-radius: 10px;">View All</a>
                        </div>
                    </div>

                    <!-- Profile (Desktop) -->
                    <div style="position: relative; margin-left: 10px;">
                        <div style="display: flex; align-items: center; gap: 12px; cursor: pointer; transition: 0.2s;" onclick="const d = document.getElementById('profileDropdown'); d.style.display = d.style.display === 'none' ? 'flex' : 'none'; event.stopPropagation();">
                            <div style="text-align: right; line-height: 1.1;">
                                <h6 style="margin: 0; font-size: 1rem; font-weight: 950; letter-spacing: -0.5px;"><?= htmlspecialchars(explode(' ', $student['name'])[0]) ?></h6>
                                <span style="font-size: 0.65rem; color: var(--text-dim); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Scholar</span>
                            </div>
                            <div style="width: 48px; height: 48px; border-radius: 15px; background: #e2e8f0; border: 2px solid #fff; display: flex; align-items: center; justify-content: center; color: var(--text-dim); font-size: 1.2rem; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                                <?php if(!empty($student['avatar'])): ?>
                                    <img src="../uploads/avatars/<?= htmlspecialchars($student['avatar']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-user-astronaut"></i>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div id="profileDropdown" style="display: none; flex-direction: column; position: absolute; top: 60px; right: 0; background: white; border: 1px solid var(--border); border-radius: 16px; padding: 8px; width: 220px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); z-index: 100;">
                            <a href="profile.php" style="display: flex; align-items: center; gap: 12px; padding: 12px; color: var(--text-main); text-decoration: none; font-size: 0.88rem; border-radius: 10px; font-weight: 750; transition: 0.2s;"><i class="fas fa-user-circle" style="color: var(--primary); width: 18px;"></i> Profile Base</a>
                            <div style="height: 1px; background: #f1f5f9; margin: 8px 0;"></div>
                            <a href="../logout.php" style="display: flex; align-items: center; gap: 12px; padding: 12px; color: #ef4444; text-decoration: none; font-size: 0.88rem; border-radius: 10px; font-weight: 750; transition: 0.2s;"><i class="fas fa-power-off" style="width: 18px;"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
>

            <!-- Academic Metrics -->
            <div style="display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap;">
                <div style="width: 85px; height: 85px; padding: 8px; text-align: center; background: #FFF7ED; border-radius: 16px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    <i class="fas fa-fire" style="color: #FF5A1F; margin-bottom: 4px; font-size: 1.1rem;"></i>
                    <span style="display: block; font-size: 0.55rem; font-weight: 900; color: #FF5A1F; margin-bottom: 2px;">STREAK</span>
                    <strong style="font-size: 0.95rem; font-weight: 800;"><?= $student['streak_days'] ?></strong>
                </div>
                <div style="width: 85px; height: 85px; padding: 8px; text-align: center; background: #FEF3C7; border-radius: 16px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    <i class="fas fa-crown" style="color: #D97706; margin-bottom: 4px; font-size: 1.1rem;"></i>
                    <span style="display: block; font-size: 0.55rem; font-weight: 900; color: #D97706; margin-bottom: 2px;">POINTS</span>
                    <strong style="font-size: 0.95rem; font-weight: 800;"><?= number_format($student['merit_points']) ?></strong>
                </div>
                <div style="width: 85px; height: 85px; padding: 8px; text-align: center; background: #ECFDF5; border-radius: 16px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    <i class="fas fa-coins" style="color: #059669; margin-bottom: 4px; font-size: 1.1rem;"></i>
                    <span style="display: block; font-size: 0.55rem; font-weight: 900; color: #059669; margin-bottom: 2px;">COINS</span>
                    <strong style="font-size: 0.95rem; font-weight: 800;"><?= number_format($student['merit_coins']) ?></strong>
                </div>
            </div>

            <!-- Calendar Widget -->
            <div class="calendar-widget">
                <?php 
                $currentDate = new DateTime(); 
                $cal_m = isset($_GET['cal_m']) ? (int)$_GET['cal_m'] : (int)$currentDate->format('m');
                $cal_y = isset($_GET['cal_y']) ? (int)$_GET['cal_y'] : (int)$currentDate->format('Y');
                $reqDate = new DateTime("$cal_y-$cal_m-01");
                $monthName = $reqDate->format('F Y');
                $numDays = (int)$reqDate->format('t');
                $startDayOffset = (int)$reqDate->format('N') - 1;
                $prevDate = (clone $reqDate)->modify('-1 month');
                $nextDate = (clone $reqDate)->modify('+1 month');
                $prevUrl = "?cal_m=" . $prevDate->format('m') . "&cal_y=" . $prevDate->format('Y');
                $nextUrl = "?cal_m=" . $nextDate->format('m') . "&cal_y=" . $nextDate->format('Y');
                $isCurrentMonth = ($cal_m == (int)$currentDate->format('m') && $cal_y == (int)$currentDate->format('Y'));
                $todayNum = $isCurrentMonth ? (int)$currentDate->format('d') : null;
                ?>
                <div class="calendar-header">
                    <h4><?= $monthName ?></h4>
                    <div style="display: flex; gap: 8px;">
                        <a href="<?= $prevUrl ?>"><i class="fas fa-chevron-left"></i></a>
                        <a href="<?= $nextUrl ?>"><i class="fas fa-chevron-right"></i></a>
                    </div>
                </div>
                <div class="calendar-grid">
                    <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
                        <div class="day-label"><?= $day ?></div>
                    <?php endforeach; ?>
                    <?php for($i=0; $i<$startDayOffset; $i++): ?><div></div><?php endfor; ?>
                    <?php for($d=1; $d<=$numDays; $d++): 
                        $hasEvent = false;
                        foreach($cal_events as $ce) {
                            if(date('j', strtotime($ce['event_date'])) == $d && date('n', strtotime($ce['event_date'])) == $cal_m) {
                                $hasEvent = true; break;
                            }
                        }
                    ?>
                        <div class="day-num <?= $d == $todayNum ? 'active' : '' ?>" style="<?= $hasEvent ? 'border: 1px solid var(--primary);' : '' ?>">
                            <?= $d ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </aside>
        <?php endif; ?>
    </div> <!-- End dashboard-wrapper -->
</div> <!-- End main-content -->

<script src="../assets/js/main.js"></script>
<script>
// Close dropdowns when clicking outside
document.addEventListener('click', function() {
    const drops = ['calDropdownM', 'notifDropdownM', 'mobileDropdown', 'calDropdownD', 'notifDropdownD', 'profileDropdown'];
    drops.forEach(id => {
        const d = document.getElementById(id);
        if(d) d.style.display = 'none';
    });
});
</script>
</body>
</html>
