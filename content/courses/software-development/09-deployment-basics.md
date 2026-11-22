---
title: Deployment Basics
module: 4
module_title: Shipping and Getting Work
minutes: 12
---

Software that only runs on your laptop helps nobody. **Deployment** is the process of putting your app somewhere users can reach it, usually on a server connected to the internet. This lesson explains the main ideas so that you can deploy simple projects and understand what senior developers are talking about.

## Environments

Professional teams keep separate copies of an app, called **environments**:

| Environment | Who uses it | Purpose |
|---|---|---|
| Development (local) | You, on your laptop | Writing and trying code |
| Staging (test) | The team and the client | Checking everything on a server that copies the live one |
| Production (live) | Real users | The real app, with real data |

> **Warning:** Never test risky changes directly on production. A mistake there can affect real customers, real payments or real student records.

## Key words

- **Server:** a computer that is always on and connected to the internet, serving your app to visitors.
- **Hosting:** renting space on a server. Options range from shared hosting (common for PHP sites in Kenya) to cloud platforms.
- **Domain name:** the human-friendly address, such as `mybarber.co.ke`, that points to your server.
- **Static site:** only HTML, CSS and JavaScript files; no database. Cheapest and easiest to host.
- **Dynamic app:** has server code and a database (logins, payments, records). Needs hosting that can run that code.

## Deploying a static site for free

For portfolio projects, several services host static sites for free and connect directly to GitHub, for example **GitHub Pages**, **Netlify** and **Cloudflare Pages**. The general steps are:

1. Push your project to a GitHub repository.
2. Connect the repository in the hosting service (or turn on Pages in the repo's settings).
3. Choose the branch to publish, usually `main`.
4. Wait a minute, then open the web address it gives you.

After that, many of these services redeploy automatically every time you `git push`. That is a simple form of **continuous deployment**.

> **Note:** Free tiers and their limits change from time to time. Read the current terms on each provider's website before you rely on one for a client.

## A deployment checklist

Before any release, even a small one, go through a list like this:

```text
[ ] All tests pass
[ ] Tried the main features on a phone and on a laptop
[ ] No passwords or API keys in the code or in Git
[ ] Settings for production are correct (database name, live M-Pesa keys)
[ ] Backup of the current live version and database taken
[ ] Client knows when the update is happening
[ ] After deploying: open the live site and test the main flow again
```

## Configuration and secrets

The same code runs in development and production, but settings differ (database passwords, payment keys). These are kept in **environment variables** or a config file that is listed in `.gitignore`, never written into the code itself:

```text
# .env (never committed to Git)
DB_HOST=localhost
DB_NAME=chama_app
MPESA_CONSUMER_KEY=put-the-real-key-here
```

## When things go wrong

Even careful teams sometimes deploy a bug. Be ready to:

- **Roll back**: put the last working version back (Git makes this possible because every version is saved).
- **Read the logs**: servers record errors in log files that tell you what failed.
- **Communicate**: tell the client honestly what happened and when it will be fixed.

## Monitoring and maintenance

After launch, keep an eye on the app: is it online, is it fast, are there new errors? Free uptime monitors can email you if your site goes down. Plan time each month for updates, because browsers, libraries and payment APIs change.

```exercise
Q: Name the environment where real users use the app.
The live environment is called ___.
A: production
H: It starts with "p".
```

```exercise
Q: Fill in where secrets like API keys should be kept instead of in the code.
Keep secrets in ___ variables.
A: environment
H: They depend on the environment the app runs in.
```

## Practice task

Deploy your `my-first-repo` project (or any simple HTML page) for free using GitHub Pages or a similar service. Open the live link on your phone, then send it to a friend and ask them to tell you one thing that looks wrong or confusing. Fix it and push again.
