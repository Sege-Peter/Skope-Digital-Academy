<?php
/**
 * Default recruitment assessments for the five advertised roles (seeded once; admins edit them in
 * Admin → Careers → Assessment). Each role: 5 knowledge MCQs (4 marks), 5 situational MCQs (4 marks),
 * one practical written task (40) and one professional-competency written task (20) = 100 marks.
 * Format per MCQ: [prompt, [options], correct index]. Written: [prompt, marks, marking guide].
 */
return [
'Academic & Training Coordinator' => [
    'knowledge' => [
        ['A learning outcome is best written as…', ['A list of topics the course will cover', 'A measurable statement of what the learner will be able to do', 'The number of hours of teaching', 'A summary of the instructor\'s experience'], 1],
        ['Which assessment is mainly used to give feedback while learning is still happening?', ['Summative assessment', 'Formative assessment', 'Entry exam', 'Final certification exam'], 1],
        ['In the ADDIE instructional design model, the "E" stands for…', ['Execution', 'Engagement', 'Evaluation', 'Enrolment'], 2],
        ['A rubric is most useful for…', ['Scoring open-ended work consistently against clear criteria', 'Timetabling instructors', 'Recording attendance', 'Setting course fees'], 0],
        ['Which is the strongest sign that a training programme is effective?', ['Learners enjoyed the sessions', 'Learners can apply the new skills in real tasks', 'The course had many slides', 'The instructor finished on time'], 1],
    ],
    'situational' => [
        ['Two instructors are scheduled for the same lab at the same time next week. What do you do first?', ['Cancel both sessions', 'Check the timetable, agree a fix with both instructors and notify affected learners', 'Let the instructors sort it out themselves', 'Wait until the day to see who arrives first'], 1],
        ['Half of a cohort failed the same quiz question. What is the best first step?', ['Remove the question from future quizzes immediately', 'Review the question and the related lesson for clarity, then reteach or fix as needed', 'Tell learners to study harder', 'Give everyone full marks without review'], 1],
        ['An instructor regularly uploads lesson materials late. How do you handle it?', ['Report them to management without talking to them', 'Discuss the cause privately, agree deadlines and support, and follow up', 'Upload your own materials instead without telling them', 'Ignore it if learners don\'t complain'], 1],
        ['A learner asks to skip a prerequisite course because they "already know it". You…', ['Refuse outright', 'Offer a placement test or evidence review based on clear criteria', 'Allow it without checking', 'Ask other learners what they think'], 1],
        ['Learner feedback says a course is too theoretical. What is the best response?', ['Ignore it; theory is important', 'Review the course with instructors and add practical tasks and examples', 'Shorten the course by half', 'Replace the instructor immediately'], 1],
    ],
    'practical' => ['Design a 4-week, beginner-level "Digital Skills for Small Business" training programme for learners in Kisumu. Include: 3–4 measurable learning outcomes, a week-by-week outline, how learners will be assessed (formative and final), and how you would check quality during delivery.', 40, "Outcomes measurable and realistic (8) · Logical weekly progression with practical activities (10) · Assessment plan with formative + summative and clear criteria (10) · Quality monitoring (feedback, observation, data) (7) · Clarity, local relevance (5)"],
    'professional' => ['Describe a time you coordinated people or a programme under a tight deadline. What was the situation, what did you do, and what was the result? What would you do differently?', 20, "Clear situation and own role (5) · Concrete actions showing planning and communication (7) · Measurable result (4) · Reflection and learning (4)"],
],
'Technology & Innovation Officer' => [
    'knowledge' => [
        ['What does LMS stand for in education technology?', ['Local Media Server', 'Learning Management System', 'Lesson Monitoring Software', 'Linked Module Storage'], 1],
        ['Which practice best protects user passwords stored in a database?', ['Store them in plain text with restricted access', 'Encrypt them with a key kept in the same database', 'Hash them with a slow algorithm such as bcrypt or Argon2', 'Encode them in Base64'], 2],
        ['An HTTP 500 error usually means…', ['The page was not found', 'The user is not logged in', 'Something failed on the server', 'The request was redirected'], 2],
        ['What is the main purpose of regular, tested backups?', ['Speed up the website', 'Recover data after failure, error or attack', 'Reduce hosting costs', 'Improve search ranking'], 1],
        ['In SQL, which helps prevent SQL injection?', ['Building queries by joining strings', 'Prepared statements with bound parameters', 'Hiding error messages only', 'Using uppercase keywords'], 1],
    ],
    'situational' => [
        ['The LMS goes down 30 minutes before a scheduled exam. What do you do first?', ['Start rebuilding the server from scratch', 'Check status/logs to find the cause, inform academics and learners, and restore service or agree a new time', 'Wait to see if it comes back by itself', 'Tell learners to email their answers'], 1],
        ['A learner says they cannot log in. What is the best first step?', ['Reset their password and send it to them by SMS in plain text', 'Verify their identity, check the account status and guide them through the reset flow', 'Create a new account for them', 'Tell them to try again tomorrow'], 1],
        ['A staff member asks for the database password to "check something quickly". You…', ['Share it by WhatsApp', 'Find out what they need and give access through a proper, limited account or do the task for them', 'Post it in the staff group', 'Refuse and ignore the request'], 1],
        ['You find an update that fixes a security flaw in the LMS plugin. You…', ['Apply it directly on the live site during class hours', 'Back up, test it on a staging copy, then apply at a quiet time and verify', 'Ignore it until something breaks', 'Remove the plugin permanently'], 1],
        ['Instructors want a new tool that needs every learner\'s personal data sent to a third party. You…', ['Install it immediately', 'Assess privacy, security and the Data Protection Act requirements before approving', 'Let each instructor decide', 'Install it on a trial with real data'], 1],
    ],
    'practical' => ['Our LMS has slow page loads in the evening when most learners are online, and some learners on mobile data cannot finish video lessons. Explain how you would investigate the problem, the likely causes you would check, and a step-by-step plan to fix it. Include how you would measure that it worked.', 40, "Structured investigation (logs, monitoring, peak load) (10) · Plausible causes: server resources, DB queries, caching, media size/CDN (10) · Concrete, prioritised fixes incl. low-bandwidth options (12) · Measurement and follow-up (8)"],
    'professional' => ['Tell us about a technical problem you solved that others found hard. How did you approach it, and how did you explain the solution to non-technical people?', 20, "Real, specific problem (5) · Logical approach (6) · Clear explanation to non-technical audience (5) · Outcome and learning (4)"],
],
'Student Affairs & Welfare Officer' => [
    'knowledge' => [
        ['The first goal of handling a learner grievance is to…', ['Close it as quickly as possible', 'Listen, understand the issue and record it accurately', 'Decide who is at fault', 'Forward it to the director'], 1],
        ['Which information should be kept confidential?', ['Course timetables', 'A learner\'s health or personal circumstances', 'Public event dates', 'Course fees'], 1],
        ['Active listening includes…', ['Interrupting to save time', 'Paraphrasing and asking clarifying questions', 'Giving advice immediately', 'Checking your phone while they speak'], 1],
        ['A good learner-retention practice is…', ['Contacting learners only when fees are due', 'Early follow-up with learners who fall behind', 'Removing inactive learners without notice', 'Waiting for learners to ask for help'], 1],
        ['Alumni engagement mainly helps the academy to…', ['Reduce staff workload', 'Build a network for mentorship, referrals and feedback', 'Avoid new admissions', 'Replace instructors'], 1],
    ],
    'situational' => [
        ['A learner tells you they are being harassed by another learner. You…', ['Tell them to ignore it', 'Listen, ensure their safety, document it and follow the academy\'s procedure', 'Post about it in the class group', 'Confront the other learner publicly'], 1],
        ['An angry parent calls about their child\'s grades. You…', ['Argue the grades are correct', 'Stay calm, listen, check consent and records, and arrange a follow-up with the right person', 'Change the grades to end the call', 'Hang up'], 1],
        ['A learner has missed three weeks of classes. What do you do?', ['Remove them from the course', 'Reach out kindly to understand why and offer support options', 'Wait for them to come back', 'Report them to the police'], 1],
        ['A learner shares that they cannot afford to continue. You…', ['Tell them to drop out', 'Explain available options such as scholarships or payment plans and refer them correctly', 'Promise them free fees yourself', 'Ignore it; fees are not your job'], 1],
        ['Two learners in a group project keep arguing. You…', ['Split the group without talking to them', 'Meet them, clarify roles and expectations, and agree on a way forward', 'Fail both learners', 'Let the instructor deal with it'], 1],
    ],
    'practical' => ['Design a simple learner support plan for a new intake of 100 online learners. Include: how you will welcome and onboard them, how you will spot learners who are struggling, the support channels you will offer, how grievances will be handled, and how you will measure whether the plan works.', 40, "Onboarding steps (8) · Early-warning indicators using data/attendance (10) · Support channels incl. welfare and referral (8) · Fair grievance process (7) · Measures of success (7)"],
    'professional' => ['Describe a time you supported someone through a difficult situation. What did you do, how did you keep it professional, and what was the outcome?', 20, "Empathy shown (5) · Professional boundaries and confidentiality (6) · Practical action (5) · Outcome and reflection (4)"],
],
'Marketing & Communications Officer' => [
    'knowledge' => [
        ['A "call to action" in a social media post is…', ['The post\'s hashtags', 'A clear instruction telling the audience what to do next', 'The brand logo', 'The number of likes'], 1],
        ['Which metric best shows how many people saw a post?', ['Click-through rate', 'Reach', 'Bounce rate', 'Conversion rate'], 1],
        ['Brand consistency mainly means…', ['Posting every hour', 'Using the same voice, colours and logo across all channels', 'Changing the logo often', 'Copying competitors'], 1],
        ['SEO stands for…', ['Social Engagement Optimisation', 'Search Engine Optimisation', 'Sales Email Outreach', 'Secure Easy Online'], 1],
        ['Using a photo you found online in an advert without permission can…', ['Always be done if you credit the source', 'Infringe copyright and harm the brand', 'Improve SEO', 'Only matter outside Kenya'], 1],
    ],
    'situational' => [
        ['A negative comment about the academy goes viral. You…', ['Delete it and block the user', 'Respond quickly, calmly and factually, and move the issue to a private channel to resolve', 'Reply with an angry post', 'Ignore it completely'], 1],
        ['Management asks you to post that "100% of learners get jobs", but there is no data. You…', ['Post it anyway', 'Explain the risk and propose an honest message backed by real data or stories', 'Change it to 95%', 'Post it on a personal account instead'], 1],
        ['A campaign is getting many likes but no enrolments. You…', ['Keep running it unchanged', 'Review the audience, message, landing page and tracking, then test changes', 'Stop all marketing', 'Buy more likes'], 1],
        ['A learner sends a great testimonial. Before using it publicly you…', ['Post it with their photo immediately', 'Get their written consent for how it will be used', 'Edit it to sound better', 'Post it without their name'], 1],
        ['You have a small budget for a new course launch. You…', ['Spend it all on one billboard', 'Define the target audience and test small paid posts on the channels they use, then scale what works', 'Skip promotion', 'Copy last year\'s campaign without review'], 1],
    ],
    'practical' => ['Create a 2-week launch campaign for a new "Web Development for Beginners" course aimed at 18–30-year-olds in Kenya. Include: the key message, 3 sample social media posts (with captions), the channels you would use and why, a simple posting schedule, and how you would measure success.', 40, "Clear, honest key message for the audience (8) · Quality and creativity of 3 sample posts with CTA (12) · Channel choice justified (6) · Realistic schedule (6) · Measurable KPIs and tracking (8)"],
    'professional' => ['Share an example of content or a campaign you created that performed well (or one that failed). What did you learn from the results?', 20, "Specific example (5) · Evidence/metrics (6) · Insight and learning (5) · Communication quality (4)"],
],
'Partnerships & Resource Mobilisation Officer' => [
    'knowledge' => [
        ['A Memorandum of Understanding (MoU) is…', ['A binding loan agreement', 'A written agreement setting out partners\' shared intentions and roles', 'A fundraising receipt', 'An invoice'], 1],
        ['A strong grant proposal usually starts by clearly stating…', ['The organisation\'s history only', 'The problem, the target group and the expected impact', 'How much staff are paid', 'A list of logos'], 1],
        ['Stakeholder mapping helps you to…', ['Design a logo', 'Identify who matters to the project and how to engage them', 'Set course fees', 'Write code'], 1],
        ['In fundraising, "donor stewardship" means…', ['Asking the same donor every week', 'Building the relationship through updates, reporting and thanks', 'Keeping donor names secret from management', 'Refusing small donations'], 1],
        ['Which is an example of in-kind support?', ['A cash grant', 'Donated laptops or free venue space', 'A bank loan', 'Course fees paid by learners'], 1],
    ],
    'situational' => [
        ['A potential sponsor wants their logo on certificates in return for funding. You…', ['Agree immediately', 'Check academy policy and brand guidelines, consult leadership and agree terms in writing', 'Refuse without discussion', 'Add the logo before the money arrives'], 1],
        ['A grant deadline is in 3 days but you lack some required data. You…', ['Invent the data', 'Gather what is available fast, be transparent about gaps, or ask the funder for guidance', 'Skip the application without telling anyone', 'Copy another organisation\'s data'], 1],
        ['A partner company has not delivered promised internship places. You…', ['End the partnership publicly', 'Meet them to understand the issue, review the agreement and agree a plan', 'Ignore it', 'Complain on social media'], 1],
        ['A donor asks how their money was used. You…', ['Send a vague thank-you note', 'Provide a clear report with figures, outcomes and stories (with consent)', 'Tell them it is confidential', 'Ask them to donate again first'], 1],
        ['You meet a promising partner at an event. Your next step is…', ['Wait for them to call', 'Follow up within a few days with a short, tailored proposal for a meeting', 'Send them the full annual report only', 'Add them to a mass mailing list without consent'], 1],
    ],
    'practical' => ['Write a one-page concept note seeking support from a Kenyan tech company to fund 20 scholarships for young women in digital skills at Skope Digital Academy. Include: the problem, the proposed solution, what the partner gives and gets, a simple budget summary, and how impact will be reported.', 40, "Clear problem and target group (8) · Credible solution (8) · Partner value proposition (8) · Simple realistic budget (8) · Impact reporting and accountability (8)"],
    'professional' => ['Describe a partnership or fundraising effort you were involved in. What was your role, what challenges did you face, and what was achieved?', 20, "Specific role (5) · Relationship-building approach (6) · Results with evidence (5) · Reflection (4)"],
],
];
