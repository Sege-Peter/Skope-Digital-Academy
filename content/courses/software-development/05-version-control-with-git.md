---
title: Version Control with Git
module: 2
minutes: 13
---

Have you ever saved files as `project-final.docx`, `project-final-2.docx` and `project-REALLY-final.docx`? Developers had the same problem, so they built **version control**: a system that records every change to your files, who made it and why, so you can go back in time whenever you need to.

The most popular version control system in the world is **Git**. Almost every software job and many freelance clients expect you to know it.

## What Git gives you

- **History:** every saved version of your project, with a message explaining it.
- **Undo:** go back to a version that worked when you break something.
- **Branches:** try a new feature without touching the working version.
- **Teamwork:** several developers can work on the same project and combine their changes.

Git runs on your own computer. **GitHub** (next lesson) is a website that stores copies of Git projects online.

## Key words

| Term | Meaning |
|---|---|
| Repository (repo) | A project folder that Git is tracking |
| Commit | A saved snapshot of your files, with a message |
| Staging area | The list of changes you have chosen to include in the next commit |
| Branch | A separate line of work inside the repo |
| Merge | Combining the changes from one branch into another |

## Installing and setting up

Download Git from git-scm.com (it is free) and install it. Then open a terminal (on Windows, "Git Bash" is installed with it) and tell Git who you are. You only do this once per computer:

```bash
git config --global user.name "Achieng Otieno"
git config --global user.email "achieng@example.com"
```

## The everyday workflow

These commands are for your terminal, not for the online editor. Read them carefully; you will type them hundreds of times.

```bash
# 1. Start tracking a project folder (once per project)
cd chama-app
git init

# 2. See what has changed
git status

# 3. Choose the changes to save (stage them)
git add index.html
git add .            # the dot means "everything that changed"

# 4. Save a snapshot with a clear message
git commit -m "Add member list page"

# 5. See the history
git log --oneline
```

The pattern is always the same: **edit, `git status`, `git add`, `git commit`**.

> **Tip:** Commit small and often, each time one thing works. A message like "Add M-Pesa payment option to sale form" is far more useful later than "changes" or "update".

## Branches

A branch lets you work on a feature safely. The main, working version usually lives on a branch called `main`.

```bash
git branch                     # list branches
git switch -c low-stock-report # create a new branch and move to it
# ...edit files, add, commit...
git switch main                # go back to main
git merge low-stock-report     # bring the finished feature into main
```

If the feature goes badly, you can simply abandon the branch, and `main` is untouched.

## Going back in time

```bash
git log --oneline              # find the commit you want, e.g. a1b2c3d
git diff                       # see changes you have not committed yet
git restore index.html         # throw away uncommitted changes to one file
```

> **Warning:** `git restore` permanently discards uncommitted changes to that file. Run `git status` and `git diff` first so you know exactly what you are throwing away.

## What not to commit

Never commit passwords, M-Pesa API keys or other secrets. Create a file called `.gitignore` listing things Git should ignore:

```text
.env
node_modules/
*.log
```

```exercise
Q: Complete the command that saves a snapshot with a message.
git ___ -m "Add login page"
A: commit
H: A snapshot in Git is called a...
```

```exercise
Q: Fill in the two commands of the everyday workflow, in order.
git status, then git ___, then git ___
A: add | commit
H: First stage the changes, then save them.
```

## Practice task

Install Git, create a folder called `my-first-repo` with a simple `index.html` inside, and run `git init`, `git add .` and `git commit -m "First commit"`. Change the file, commit again with a clear message, then run `git log --oneline` and confirm you can see both commits.
