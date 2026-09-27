<?php
/**
 * Gamified Logic: Referrals, Merit Coins, and Automated Badges
 */

/**
 * Award 4% merit coins to the referrer when a student completes a payment
 */
function rewardReferrer($student_id, $course_price, $pdo) {
    try {
        // Find if this student was referred
        $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $referrer_id = $stmt->fetchColumn();

        if ($referrer_id) {
            $coin_reward = $course_price * 0.04;
            
            if ($coin_reward > 0) {
                // Update referrer coins
                $upd = $pdo->prepare("UPDATE users SET merit_coins = merit_coins + ? WHERE id = ?");
                $upd->execute([$coin_reward, $referrer_id]);

                // Log the merit gain (reusing audit log or new ledger if needed)
                $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, description) 
                                       VALUES (?, 'referral_earned', ?)");
                $stmt->execute([$referrer_id, "Earned " . number_format($coin_reward, 2) . " merit coins from referral enrollment."]);
                
                // Automate badge check for referrers
                awardBadgeIfEligible($referrer_id, 'referrals_count', $pdo);
                awardBadgeIfEligible($referrer_id, 'coins_earned', $pdo);
            }
        }
    } catch (Exception $e) { error_log("Gamify Error: " . $e->getMessage()); }
}

/**
 * Check and award badges based on criteria
 */
function awardBadgeIfEligible($student_id, $criteria_type, $pdo) {
    try {
        // 1. Get current value for this criteria
        $current_value = 0;
        
        switch($criteria_type) {
            case 'courses_completed':
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'completed'");
                $stmt->execute([$student_id]);
                $current_value = $stmt->fetchColumn();
                break;
            case 'lessons_completed':
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id = ? AND status = 'completed'");
                $stmt->execute([$student_id]);
                $current_value = $stmt->fetchColumn();
                break;
            case 'quizzes_passed':
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE student_id = ? AND passed = 1");
                $stmt->execute([$student_id]);
                $current_value = $stmt->fetchColumn();
                break;
            case 'points_earned':
                $stmt = $pdo->prepare("SELECT merit_points FROM users WHERE id = ?");
                $stmt->execute([$student_id]);
                $current_value = $stmt->fetchColumn();
                break;
            case 'coins_earned':
                $stmt = $pdo->prepare("SELECT merit_coins FROM users WHERE id = ?");
                $stmt->execute([$student_id]);
                $current_value = $stmt->fetchColumn();
                break;
            case 'referrals_count':
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
                $stmt->execute([$student_id]);
                $current_value = $stmt->fetchColumn();
                break;
        }

        // 2. Find badges for this criteria that the student DOES NOT have yet
        $stmt = $pdo->prepare("SELECT id FROM badges 
                               WHERE criteria_type = ? AND criteria_value <= ? 
                               AND id NOT IN (SELECT badge_id FROM student_badges WHERE student_id = ?)");
        $stmt->execute([$criteria_type, $current_value, $student_id]);
        $eligible_badges = $stmt->fetchAll();

        // 3. Award them
        if ($eligible_badges) {
            $awardStmt = $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_id) VALUES (?, ?)");
            foreach($eligible_badges as $b) {
                $awardStmt->execute([$student_id, $b['id']]);
                
                // Notify user (optionally log to notifications table)
                $pdo->prepare("INSERT INTO notifications (title, message, target_user_id) 
                               VALUES ('New Badge Awarded!', 'Congratulations! You earned a new badge for your academic achievements.', ?)")
                    ->execute([$student_id]);
            }
        }

    } catch (Exception $e) { error_log("Badge Error: " . $e->getMessage()); }
}

/**
 * Award merit points for academic achievements
 */
function rewardStudentPoints($student_id, $points, $pdo) {
    try {
        if ($points <= 0) return;

        // Update merit points
        $upd = $pdo->prepare("UPDATE users SET merit_points = merit_points + ? WHERE id = ?");
        $upd->execute([round($points), $student_id]);

        // Log to point_ledger for transcript history
        $stmt = $pdo->prepare("INSERT INTO point_ledger (student_id, merit_points, reason) VALUES (?, ?, 'Quiz / Assessment completion')");
        $stmt->execute([$student_id, round($points)]);

        // Automate badge check for achievements
        awardBadgeIfEligible($student_id, 'points_earned', $pdo);
    } catch (Exception $e) { error_log("Merit Reward Error: " . $e->getMessage()); }
}
