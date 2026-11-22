---
title: Teamwork with Agile and Scrum
module: 3
minutes: 11
---

Very few apps are built by one person. Even freelancers work with clients, designers and sometimes other developers. Teams need a shared way of planning, communicating and delivering. The most common approach today is **Agile**, and the most popular Agile framework is **Scrum**.

## The Agile idea

Agile is a mindset rather than a strict rulebook. Its main ideas are:

- Deliver **working software in small pieces**, often (every one to four weeks).
- **Talk to the client regularly** instead of only at the start and the end.
- **Welcome change**: if the client learns something new, adjust the plan.
- **Reflect and improve** how the team works.

Compare this with spending six months building everything, then discovering the client wanted something different.

## Scrum roles

| Role | Responsibility |
|---|---|
| Product Owner | Decides *what* to build and in what order; represents the client and users |
| Scrum Master | Helps the team follow Scrum and removes blockers (for example, chasing missing server access) |
| Developers | Everyone who builds, tests and delivers the work, usually 3–9 people |

## Scrum events

Work happens in **sprints**, fixed periods of usually one or two weeks.

| Event | When | Purpose |
|---|---|---|
| Sprint planning | Start of the sprint | Choose stories from the backlog for this sprint |
| Daily scrum (stand-up) | Every day, about 15 minutes | Share progress and blockers |
| Sprint review | End of the sprint | Show the working result to the Product Owner and client |
| Retrospective | After the review | Discuss what went well and what to improve |

In the daily stand-up, each person answers three short questions:

```text
Yesterday: I finished the M-Pesa payment form.
Today: I will write tests for the payment form.
Blockers: I still need the test API keys from Wanjiru.
```

## The backlog and the board

The **product backlog** is the ordered list of all user stories and bugs, with the most important at the top. During sprint planning the team pulls some of them into the **sprint backlog**.

Teams track progress on a **Kanban board** with columns like these:

```text
| To Do              | In Progress        | In Review        | Done            |
|--------------------|--------------------|------------------|-----------------|
| Low stock report   | Record a sale      | Login page       | Add new item    |
| SMS receipts       |                    |                  | Member list     |
```

Free tools such as Trello, GitHub Projects or Jira (free tier) work well, and so does a whiteboard with sticky notes.

> **Tip:** Limit how many cards each person has "In Progress" at once, ideally one or two. Finishing work is more valuable than starting lots of it.

## Estimating work

Teams often estimate stories with **story points** (a relative size such as 1, 2, 3, 5, 8) rather than hours. A "3" is roughly three times the effort of a "1". Over a few sprints the team learns how many points it can finish, called its **velocity**, which helps with planning.

## Being a good teammate

Your technical skills get you hired; your teamwork keeps you hired.

- **Update your cards** so others know where things stand.
- **Say early when you are stuck** or when a deadline is at risk. Silence is the worst option.
- **Review code kindly**: comment on the code, not the person ("This loop could skip the first item" rather than "You made a mistake").
- **Keep commitments small and realistic.** Under-promise and over-deliver.
- **Write things down** in the issue or chat so decisions are not lost.

> **Note:** You can use Scrum ideas as a solo freelancer too: keep a backlog for each client, work in one-week sprints and send a short "sprint review" message every Friday with a link to what you finished.

```exercise
Q: Fill in the name of a fixed one- or two-week work period in Scrum.
Work is done in ___s.
A: sprint
H: A short, fast run.
```

```exercise
Q: Complete the name of the role that decides what to build and in what order.
The Product ___ prioritises the backlog.
A: owner
H: They "own" the product.
```

## Practice task

Create a free Trello board (or draw one on paper) for your app idea with the columns **To Do, In Progress, In Review, Done**. Add your user stories from lesson 3 as cards in priority order. Then write your own three-line stand-up update for today.
