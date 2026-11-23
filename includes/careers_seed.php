<?php
/**
 * Default recruitment assessments: 15 basic multiple-choice questions per role (auto-marked, 1 mark each),
 * taken in 20 minutes. Applicants are emailed their score straight after the test.
 * Format: [prompt, [options], correct index]. Admins edit them in Admin > Careers > Assessment.
 */
return [
'Academic & Training Coordinator' => [
    'knowledge' => [
        ['A learning outcome is best written as…', ['A list of topics the course will cover', 'A measurable statement of what the learner will be able to do', 'The number of hours of teaching', 'A summary of the instructor\'s experience'], 1],
        ['Which assessment mainly gives feedback while learning is still happening?', ['Summative assessment', 'Formative assessment', 'Entry exam', 'Final certification exam'], 1],
        ['In the ADDIE instructional design model, the "E" stands for…', ['Execution', 'Engagement', 'Evaluation', 'Enrolment'], 2],
        ['A rubric is most useful for…', ['Scoring open-ended work consistently against clear criteria', 'Timetabling instructors', 'Recording attendance', 'Setting course fees'], 0],
        ['Which is the strongest sign that a training programme works?', ['Learners enjoyed the sessions', 'Learners can apply the new skills in real tasks', 'The course had many slides', 'The instructor finished on time'], 1],
        ['A curriculum is…', ['Only the final exam', 'The planned content, activities and assessments of a programme', 'The academy\'s budget', 'A list of learners'], 1],
        ['What is a prerequisite?', ['A course or skill needed before starting another course', 'A type of certificate', 'A payment plan', 'A holiday break'], 0],
        ['Why pilot (test) a new course with a small group first?', ['To find and fix problems before a full launch', 'To avoid paying instructors', 'To shorten the course', 'It is not useful'], 0],
    ],
    'situational' => [
        ['Two instructors are booked for the same lab at the same time next week. What do you do first?', ['Cancel both sessions', 'Check the timetable, agree a fix with both instructors and tell affected learners', 'Let the instructors sort it out themselves', 'Wait to see who arrives first'], 1],
        ['Half of a class failed the same quiz question. What is the best first step?', ['Remove the question immediately', 'Review the question and the lesson for clarity, then reteach or fix it', 'Tell learners to study harder', 'Give everyone full marks without checking'], 1],
        ['An instructor keeps uploading lesson materials late. How do you handle it?', ['Report them to management without talking to them', 'Discuss the cause privately, agree deadlines and support, then follow up', 'Upload your own materials without telling them', 'Ignore it if learners don\'t complain'], 1],
        ['A learner asks to skip a prerequisite because they "already know it". You…', ['Refuse outright', 'Offer a placement test or check their evidence against clear criteria', 'Allow it without checking', 'Ask other learners what they think'], 1],
        ['Feedback says a course is too theoretical. What is the best response?', ['Ignore it; theory is important', 'Review it with instructors and add practical tasks and examples', 'Cut the course in half', 'Replace the instructor immediately'], 1],
        ['A learner with a hearing impairment joins a video-based course. You…', ['Tell them the course is not for them', 'Arrange captions or transcripts so they can take part fully', 'Ask them to manage on their own', 'Remove all videos for everyone'], 1],
        ['Exam results are due tomorrow but some marks are missing. You…', ['Publish the results anyway', 'Follow up with the markers now and tell learners if there will be a short delay', 'Fill in the missing marks yourself', 'Delete the incomplete results'], 1],
    ],
],
'Technology & Innovation Officer' => [
    'knowledge' => [
        ['What does LMS stand for in education technology?', ['Local Media Server', 'Learning Management System', 'Lesson Monitoring Software', 'Linked Module Storage'], 1],
        ['Which practice best protects stored user passwords?', ['Store them in plain text with restricted access', 'Encrypt them with a key kept in the same database', 'Hash them with a slow algorithm such as bcrypt', 'Encode them in Base64'], 2],
        ['An HTTP 500 error usually means…', ['The page was not found', 'The user is not logged in', 'Something failed on the server', 'The request was redirected'], 2],
        ['The main purpose of regular, tested backups is to…', ['Speed up the website', 'Recover data after failure, mistakes or attacks', 'Reduce hosting costs', 'Improve search ranking'], 1],
        ['In SQL, which helps prevent SQL injection?', ['Building queries by joining strings', 'Prepared statements with bound parameters', 'Hiding error messages only', 'Using uppercase keywords'], 1],
        ['HTTPS on a website means…', ['The site loads faster', 'Data between the browser and the server is encrypted', 'The site has no bugs', 'The site is free to use'], 1],
        ['What does DNS do?', ['Stores user passwords', 'Turns domain names into server addresses', 'Sends emails', 'Designs web pages'], 1],
        ['Two-factor authentication adds…', ['A second password typed twice', 'A second check, such as a code sent to the user', 'A faster login', 'A bigger screen'], 1],
    ],
    'situational' => [
        ['The LMS goes down 30 minutes before an exam. What do you do first?', ['Rebuild the server from scratch', 'Check logs to find the cause, inform academics and learners, and restore service or agree a new time', 'Wait to see if it comes back', 'Tell learners to email their answers'], 1],
        ['A learner says they cannot log in. Best first step?', ['Reset the password and send it by SMS in plain text', 'Confirm who they are, check the account and guide them through the reset', 'Create a new account for them', 'Tell them to try tomorrow'], 1],
        ['A colleague asks for the database password to "check something quickly". You…', ['Share it on WhatsApp', 'Find out what they need and give limited access or do the task for them', 'Post it in the staff group', 'Ignore the request'], 1],
        ['You find a security update for an LMS plugin. You…', ['Apply it on the live site during class hours', 'Back up, test it on a copy, then apply it at a quiet time and check', 'Wait until something breaks', 'Remove the plugin permanently'], 1],
        ['A new tool needs every learner\'s personal data sent to another company. You…', ['Install it immediately', 'Check privacy, security and the Data Protection Act before approving', 'Let each instructor decide', 'Try it with real data first'], 1],
        ['Staff get an email asking them to "verify" their account via a strange link. You…', ['Click it to see what happens', 'Warn staff it is likely phishing and report or block it', 'Forward it to all learners', 'Reply asking who sent it'], 1],
        ['Learners say videos keep buffering on mobile data. A good first fix is…', ['Make the videos longer', 'Offer smaller, compressed versions or audio and text alternatives', 'Ask learners to buy faster internet', 'Remove all lessons'], 1],
    ],
],
'Student Affairs & Welfare Officer' => [
    'knowledge' => [
        ['The first goal when handling a learner\'s complaint is to…', ['Close it as quickly as possible', 'Listen, understand the issue and record it accurately', 'Decide who is at fault', 'Forward it to the director'], 1],
        ['Which information should be kept confidential?', ['Course timetables', 'A learner\'s health or personal circumstances', 'Public event dates', 'Course fees'], 1],
        ['Active listening includes…', ['Interrupting to save time', 'Repeating back what you heard and asking questions', 'Giving advice immediately', 'Checking your phone while they speak'], 1],
        ['A good way to keep learners from dropping out is…', ['Contacting them only when fees are due', 'Following up early with learners who fall behind', 'Removing inactive learners without notice', 'Waiting for them to ask for help'], 1],
        ['Alumni engagement mainly helps the academy to…', ['Reduce staff workload', 'Build a network for mentoring, referrals and feedback', 'Avoid new admissions', 'Replace instructors'], 1],
        ['Onboarding new learners means…', ['Charging them extra fees', 'Welcoming them and showing them how to start and where to get help', 'Giving them the final exam first', 'Sending them the timetable only'], 1],
        ['A referral in student support means…', ['Directing a learner to the right specialist help or service', 'Ignoring the problem', 'Giving them a discount code', 'Sending them to another course'], 0],
        ['Why keep written records of learner cases?', ['To follow up properly and handle issues fairly and consistently', 'To share them on social media', 'It is not needed', 'To use them in adverts'], 0],
    ],
    'situational' => [
        ['A learner says another learner is harassing them. You…', ['Tell them to ignore it', 'Listen, make sure they are safe, record it and follow the academy\'s procedure', 'Post about it in the class group', 'Confront the other learner publicly'], 1],
        ['An angry parent calls about their child\'s grades. You…', ['Argue that the grades are correct', 'Stay calm, listen, check consent and records, and arrange a follow-up', 'Change the grades to end the call', 'Hang up'], 1],
        ['A learner has missed three weeks of classes. You…', ['Remove them from the course', 'Contact them kindly to understand why and offer support', 'Wait for them to come back', 'Report them to the police'], 1],
        ['A learner says they can no longer afford the course. You…', ['Tell them to drop out', 'Explain options such as scholarships or payment plans and refer them correctly', 'Promise free fees yourself', 'Say fees are not your job'], 1],
        ['Two learners in a group project keep arguing. You…', ['Split the group without talking to them', 'Meet them, clarify roles and agree a way forward', 'Fail both learners', 'Leave it to the instructor'], 1],
        ['A learner seems very stressed and mentions not coping. You…', ['Tell them everyone is stressed', 'Listen with care and connect them to counselling or other support', 'Share their situation with the class', 'Give them extra assignments to stay busy'], 1],
        ['Many learners ask the same question about certificates. You…', ['Answer each one separately forever', 'Answer them and add a clear FAQ or announcement for everyone', 'Ignore the questions', 'Tell them to ask the director'], 1],
    ],
],
'Marketing & Communications Officer' => [
    'knowledge' => [
        ['A "call to action" in a social media post is…', ['The post\'s hashtags', 'A clear instruction telling people what to do next', 'The brand logo', 'The number of likes'], 1],
        ['Which metric shows how many people saw a post?', ['Click-through rate', 'Reach', 'Bounce rate', 'Conversion rate'], 1],
        ['Brand consistency mainly means…', ['Posting every hour', 'Using the same voice, colours and logo on every channel', 'Changing the logo often', 'Copying competitors'], 1],
        ['SEO stands for…', ['Social Engagement Optimisation', 'Search Engine Optimisation', 'Sales Email Outreach', 'Secure Easy Online'], 1],
        ['Using a photo found online in an advert without permission can…', ['Always be done if you credit the source', 'Break copyright and harm the brand', 'Improve SEO', 'Only matter outside Kenya'], 1],
        ['A "target audience" is…', ['Everyone in the world', 'The specific group of people a campaign is meant to reach', 'Only existing customers', 'The marketing team'], 1],
        ['What is a "conversion" in digital marketing?', ['Changing the website colours', 'When someone takes the action you want, such as signing up or enrolling', 'Translating a post', 'Deleting a post'], 1],
        ['A content calendar helps you…', ['Plan what to post, where and when', 'Track staff holidays', 'Store passwords', 'Measure the office temperature'], 0],
    ],
    'situational' => [
        ['A negative comment about the academy goes viral. You…', ['Delete it and block the person', 'Reply quickly, calmly and factually, and move the issue to a private channel', 'Reply with an angry post', 'Ignore it completely'], 1],
        ['Management wants to post that "100% of learners get jobs", but there is no data. You…', ['Post it anyway', 'Explain the risk and suggest an honest message backed by real data or stories', 'Change it to 95%', 'Post it from a personal account'], 1],
        ['A campaign gets many likes but no enrolments. You…', ['Keep running it unchanged', 'Review the audience, message, landing page and tracking, then test changes', 'Stop all marketing', 'Buy more likes'], 1],
        ['A learner sends a great testimonial. Before using it publicly you…', ['Post it with their photo immediately', 'Get their written permission for how it will be used', 'Edit it to sound better', 'Post it without their name'], 1],
        ['You have a small budget for a new course launch. You…', ['Spend it all on one billboard', 'Pick the target audience and test small paid posts where they are, then scale what works', 'Skip promotion', 'Copy last year\'s campaign without review'], 1],
        ['You spot a spelling mistake in a post that is already live. You…', ['Leave it', 'Correct it quickly and check posts more carefully before publishing', 'Delete the whole page', 'Blame a colleague'], 1],
        ['A journalist calls asking for a comment on a sensitive issue. You…', ['Give your personal opinion on the spot', 'Take their details and agree an official response with management first', 'Refuse to ever speak to the media', 'Post the conversation online'], 1],
    ],
],
'Partnerships & Resource Mobilisation Officer' => [
    'knowledge' => [
        ['A Memorandum of Understanding (MoU) is…', ['A binding loan agreement', 'A written agreement setting out partners\' shared intentions and roles', 'A fundraising receipt', 'An invoice'], 1],
        ['A strong grant proposal usually starts by stating…', ['Only the organisation\'s history', 'The problem, who is affected and the expected impact', 'How much staff are paid', 'A list of logos'], 1],
        ['Stakeholder mapping helps you to…', ['Design a logo', 'Identify who matters to a project and how to engage them', 'Set course fees', 'Write code'], 1],
        ['In fundraising, "donor stewardship" means…', ['Asking the same donor every week', 'Building the relationship through updates, reports and thanks', 'Keeping donor names from management', 'Refusing small donations'], 1],
        ['Which is an example of in-kind support?', ['A cash grant', 'Donated laptops or free venue space', 'A bank loan', 'Fees paid by learners'], 1],
        ['CSR (in business) stands for…', ['Customer Sales Report', 'Corporate Social Responsibility', 'Central Service Registry', 'Course Student Register'], 1],
        ['A partnership is a "win-win" when…', ['Only the academy benefits', 'Both partners get clear value from it', 'Only the partner benefits', 'Nobody signs anything'], 1],
        ['Why report back to funders on how money was used?', ['It builds trust and makes future support more likely', 'It is never needed', 'Only to ask for more money', 'To keep them busy'], 0],
    ],
    'situational' => [
        ['A sponsor wants their logo on certificates in return for funding. You…', ['Agree immediately', 'Check policy and brand rules, consult leadership and agree terms in writing', 'Refuse without discussion', 'Add the logo before the money arrives'], 1],
        ['A grant deadline is in 3 days but some required data is missing. You…', ['Invent the data', 'Gather what you can quickly, be open about gaps, or ask the funder for guidance', 'Skip the application without telling anyone', 'Copy another organisation\'s data'], 1],
        ['A partner company has not provided the internship places it promised. You…', ['End the partnership publicly', 'Meet them to understand why, review the agreement and agree a plan', 'Ignore it', 'Complain on social media'], 1],
        ['A donor asks how their money was used. You…', ['Send a vague thank-you note', 'Give a clear report with figures, results and stories (with consent)', 'Say it is confidential', 'Ask them to donate again first'], 1],
        ['You meet a promising partner at an event. Next step?', ['Wait for them to call', 'Follow up within a few days with a short, tailored proposal to meet', 'Send only the full annual report', 'Add them to a mass mailing list without asking'], 1],
        ['A company offers funding but wants learners\' contact details for its own marketing. You…', ['Agree and share the list', 'Decline that condition and protect learners\' data; propose another way to recognise them', 'Share only phone numbers', 'Ask learners in a group chat'], 1],
        ['Two possible sponsors are business rivals. You…', ['Hide one from the other', 'Be transparent, check for conflicts and agree clear terms with each', 'Let them compete in public', 'Reject both'], 1],
    ],
],
];
