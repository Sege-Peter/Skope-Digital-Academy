---
title: GitHub and Working Together
module: 2
minutes: 12
---

Git keeps history on your own computer. **GitHub** is a website that stores your Git repositories online, so you have a backup, you can work with others and, importantly for your career, employers can see your code. GitLab and Bitbucket do a similar job, but GitHub is the one most recruiters check.

## Local and remote

| Word | Meaning |
|---|---|
| Local repository | The copy on your laptop |
| Remote repository | The copy on GitHub, usually called `origin` |
| Push | Send your new commits from local to remote |
| Pull | Bring new commits from remote down to local |
| Clone | Download a full copy of a remote repo for the first time |

## Putting your project on GitHub

1. Create a free account on github.com. Use a professional username (for example `kiprono-dev`, not `kiprono_killer254`).
2. Click **New repository**, give it a name such as `chama-app`, and create it.
3. GitHub shows you the commands to connect your local project. They look like this:

```bash
git remote add origin https://github.com/kiprono-dev/chama-app.git
git branch -M main
git push -u origin main
```

After the first time, sending new work is simply:

```bash
git add .
git commit -m "Add monthly report page"
git push
```

> **Warning:** GitHub no longer accepts your account password in the terminal. When asked, use a personal access token or sign in through the window Git opens. Never paste your password or token into a WhatsApp group or a public file.

## Working on a team project

Most teams follow a pattern called the **feature branch workflow**:

```bash
git clone https://github.com/team/school-portal.git   # once
git switch -c fix-login-error                          # new branch for your task
# ...edit, add, commit...
git push -u origin fix-login-error                     # share your branch
```

Then, on GitHub, you open a **pull request (PR)**. A PR says: "Here are my changes; please review them and merge them into `main`." Teammates read your code, leave comments, and you push fixes to the same branch. When everyone is happy, the PR is merged.

Before starting new work each day, get your teammates' latest changes:

```bash
git switch main
git pull
```

## Merge conflicts

If you and Brian both change the same line of the same file, Git cannot decide which version to keep. This is a **merge conflict**. Git marks the file like this:

```text
<<<<<<< HEAD
<h1>Welcome to Mombasa Chama</h1>
=======
<h1>Karibu Mombasa Chama</h1>
>>>>>>> brian-heading
```

To fix it, edit the file so it contains the version you agree on, delete the marker lines, then `git add` and `git commit`. Conflicts are normal; talk to your teammate if you are unsure which version is right.

## A good README

Every repository should have a `README.md` file. It is the first thing a visitor sees. Include:

- What the project does, in one or two sentences
- A screenshot
- How to run it
- The technologies used
- What you learned or would improve next

A clear README makes a small project look professional and tells an employer you can communicate.

## Other GitHub features worth knowing

- **Issues:** a to-do and bug list attached to the repo.
- **Forks:** your own copy of someone else's repo, used to contribute to open source.
- **GitHub Pages:** free hosting for simple static websites (covered in the deployment lesson).

```exercise
Q: Complete the command that sends your commits to GitHub.
git ___
A: push
H: The opposite of pull.
```

```exercise
Q: Fill in the name of the GitHub request that asks teammates to review and merge your branch.
Open a ___ request.
A: pull
H: Its short form is PR.
```

## Practice task

Create a GitHub account and push the `my-first-repo` project from the last lesson. Add a `README.md` with a one-line description and a "What I learned" section. Then open the repo page in your browser and check that your commits appear.
