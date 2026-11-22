# Free skills courses (content/courses)

Free courses for school leavers, focused on skills that earn an income. Learners register (free),
choose **self-paced** or a **structured weekly track**, and learn every lesson for free. When they
finish, the platform generates their certificate; they unlock it by paying the certificate fee the
admin sets.

Lessons use **exactly the same Markdown format as the tutorials** – read `content/README.md` first
(front matter, `example`/`output` blocks, `exercise`, `quiz`, callouts, tables).

Import (same command as the tutorials):

    php import_tutorials.php

## Folder

`content/courses/<slug>/` with `course.json` + `NN-lesson-slug.md` files.

## course.json

```json
{
  "type": "course",
  "title": "Introduction to Online Work and Freelancing",
  "short": "Freelancing",
  "description": "Two or three sentences: who it is for and what they will be able to do.",
  "category": "Business",
  "level": "beginner",
  "order": 1,
  "outcomes": [
    "Set up a professional freelancer profile",
    "Find and apply for your first online jobs safely",
    "Price your work and get paid"
  ]
}
```

* `category`: one of **Technology, Business, Design, Artificial Inteligence, Financial Literacy, Career & Personal Skills**
  (spell exactly like that).
* `outcomes`: 4–6 short, concrete "you will be able to…" statements (shown as *What you'll learn*).

## Course shape

* **4 modules = 4 weeks** (`module: 1` … `module: 4`, `module_title` on the first lesson of each).
  On the structured track one module opens per week.
* **8–10 lessons** in total, 350–700 words each, plain friendly English for a 17–20 year old.
* Every lesson ends with a `## Practice task` section: a small real-world task the learner can do
  today (e.g. "Write your Fiverr gig title", "Track your spending for 3 days").
* Most lessons also have 1–2 `exercise` blocks (fill-in-the-blank works for any subject – the blank can
  be a word, a number or a formula).
* The **last lesson** is a short wrap-up plus a `quiz` of **10–12 questions** covering the whole course.
  Learners must score 70% to complete the course and generate their certificate.
* Use `example` blocks only where code really runs (html, css, javascript, python, sql). Non-coding courses
  use tables, checklists, callouts and sample texts in plain ```text``` blocks instead.

## Context and accuracy

* Write for Kenya / East Africa: KES amounts, M-Pesa, local businesses and towns, names like Achieng, Brian,
  Fatuma, Kiprono. Mention global platforms (Upwork, Fiverr, LinkedIn, Canva, Google) where relevant.
* Be accurate and practical. **Do not state specific tax rates, statutory deductions, fees or legal thresholds
  unless you are certain and they are stable** – say "check the current rates on kra.go.ke" (or the official
  source) instead. Don't promise income amounts; give realistic ranges framed as examples.
* Warn about scams (fake jobs asking for "registration fees", sharing passwords/M-Pesa PINs) wherever it fits.
* Original wording only – never copy from other courses or websites.
