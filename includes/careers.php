<?php
/**
 * Careers: vacancies the admin posts (admin/careers.php) and the public page lists (careers.php).
 * Settings: careers_apply_email, careers_apply_link (optional form/URL), careers_schema (migration flag).
 */

require_once __DIR__ . '/learning.php';

const SDA_JOB_TYPES = ['Full-time', 'Part-time', 'Contract', 'Internship', 'Volunteer'];

function sda_careers_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if (setting('careers_schema') === '1') return;
    if ($pdo->inTransaction()) { $done = false; return; }
    $pdo->exec("CREATE TABLE IF NOT EXISTS job_vacancies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(160) NOT NULL,
        department VARCHAR(80) NULL,
        summary TEXT NOT NULL,
        responsibilities TEXT NULL,
        requirements TEXT NULL,
        job_type VARCHAR(30) NOT NULL DEFAULT 'Full-time',
        location VARCHAR(120) NULL,
        deadline DATE NULL,
        status ENUM('open','closed') NOT NULL DEFAULT 'open',
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_status (status, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // First run: post the five roles from the academy's vacancy announcement.
    if ((int)$pdo->query("SELECT COUNT(*) FROM job_vacancies")->fetchColumn() === 0) {
        $seed = [
            ['Academic & Training Coordinator', 'Academics',
             'Develops training programmes, coordinates instructors, manages curricula, monitors assessments and ensures quality learning delivery.',
             "Develop and coordinate training programmes.\nManage curriculum development and implementation.\nCoordinate instructors and training schedules.\nMonitor learner assessments and academic progress.\nMaintain quality standards in training delivery."],
            ['Technology & Innovation Officer', 'Technology',
             'Manages the LMS, digital infrastructure, technical support, software solutions and technology innovation.',
             "Manage the Academy's Learning Management System (LMS).\nMaintain digital infrastructure and learning platforms.\nProvide technical support to learners and instructors.\nSupport software development and technology integration.\nIdentify and implement innovative digital solutions."],
            ['Student Affairs & Welfare Officer', 'Student Affairs',
             'Handles admissions, student support, learner engagement, grievances, academic progress and alumni coordination.',
             "Coordinate student admissions and registration.\nProvide learner support and guidance.\nManage student engagement and welfare matters.\nMonitor student progress and address grievances.\nCoordinate alumni engagement and graduate support."],
            ['Marketing & Communications Officer', 'Marketing',
             'Manages branding, digital marketing, public relations, content creation, social media and institutional visibility.',
             "Develop and implement marketing strategies.\nManage institutional branding and communications.\nCreate engaging digital and promotional content.\nManage social media and online visibility.\nCoordinate public relations and promotional campaigns."],
            ['Partnerships & Resource Mobilisation Officer', 'Partnerships',
             'Builds relationships with institutions, companies and sponsors; coordinates partnerships, fundraising, grants and scholarship opportunities.',
             "Identify and establish strategic partnerships.\nBuild relationships with organisations, companies and institutions.\nCoordinate fundraising and resource mobilisation initiatives.\nIdentify grant and sponsorship opportunities.\nSupport scholarship programmes and institutional collaborations."],
        ];
        $ins = $pdo->prepare("INSERT INTO job_vacancies (title, department, summary, responsibilities, job_type, status, sort_order) VALUES (?, ?, ?, ?, 'Full-time', 'open', ?)");
        foreach ($seed as $i => $r) $ins->execute([$r[0], $r[1], $r[2], $r[3], ($i + 1) * 10]);
    }
    save_setting($pdo, 'careers_schema', '1');
}

/** Lines of a textarea as a clean list. */
function sda_lines(?string $s): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$s)), 'strlen'));
}

function sda_careers_email(): string
{
    $e = trim(setting('careers_apply_email', 'info@skopedigital.co.ke'));
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : 'info@skopedigital.co.ke';
}

function sda_careers_link(): string
{
    $u = trim(setting('careers_apply_link', ''));
    return preg_match('#^https://#i', $u) && filter_var($u, FILTER_VALIDATE_URL) ? $u : '';
}

/** Open vacancies whose deadline has not passed (a closing date counts through the end of that day). */
function sda_open_vacancies(PDO $pdo): array
{
    return $pdo->query("SELECT * FROM job_vacancies WHERE status = 'open' AND (deadline IS NULL OR deadline >= CURDATE())
                        ORDER BY sort_order, id")->fetchAll();
}
