# Skope Digital Academy

Skope Digital Academy is a comprehensive Learning Management System (LMS) built with PHP. It provides a full-featured platform for online education, supporting both students and tutors with interactive learning, gamification, AI assistance, and seamless payment integration.

## Features

### Student Portal
- **Dashboard & Courses:** Browse and enroll in courses, view progress, and access materials.
- **Assignments & Quizzes:** Submit assignments and take interactive quizzes.
- **Gamification & Leaderboard:** Earn badges, track points, and compete on the leaderboard.
- **Certificates:** Automatically generate and view certificates upon course completion.
- **AI Assistant:** Integrated AI features to assist students with learning.
- **Community & Messaging:** Interact with other students, mentors, and receive announcements.
- **Payments:** Secure course enrollment payments using Paystack.

### Tutor Portal
- **Dashboard & Analytics:** Track student progress, course engagement, and overall revenue.
- **Course & Lesson Management:** Create and manage courses, lessons, quizzes, and assignments.
- **Student Management:** View student profiles, award badges, and export student data.
- **AI Assistant:** Utilize AI to help generate course content and manage tasks.
- **Notifications & Communication:** Send announcements and manage direct messages.

## Tech Stack
- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript
- **Payment Gateway:** Paystack
- **AI Integration:** Custom AI handlers and proxy

## Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Sege-Peter/Skope-Digital-Academy.git
   ```

2. **Server Environment Setup:**
   - Move the cloned repository to your local server document root (e.g., `htdocs` for XAMPP or `www` for WAMP/MAMP).
   - Ensure your server has PHP installed and MySQL running.

3. **Database Configuration:**
   - Create a new MySQL database.
   - Import the database schema (if provided in the repository).
   - Update the database credentials in `includes/db.php` (or wherever your configuration file is located).

4. **Environment Variables / Configuration:**
   - Configure your Paystack API keys in `includes/paystack.php`.
   - Configure AI settings in `includes/ai_config.php`.

5. **Run the Application:**
   - Access the application via your web browser, e.g., `http://localhost/Skope Digital Academy/`.

## Directory Structure
- `/assets`: Contains CSS, JS, and image files.
- `/includes`: Core PHP files for database connection, authentication, layout components (header/footer/sidebar), and shared logic.
- `/student`: All views and logic for the student portal.
- `/tutor`: All views and logic for the tutor portal.
- `/uploads`: Directory for uploaded assignments, avatars, course materials, and certificates.

## License
All rights reserved.
