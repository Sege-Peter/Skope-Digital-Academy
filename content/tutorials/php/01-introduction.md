---
title: PHP Introduction
module: 1
module_title: Getting Started
minutes: 8
---

PHP is a programming language that runs on a **web server**. When someone visits a page built with PHP, the server runs the PHP code first, and only the finished result (usually HTML) travels to the visitor's browser. The visitor never sees your PHP code.

The name is a recursive acronym: **PHP: Hypertext Preprocessor**. "Preprocessor" is a good clue to what it does: it prepares the page *before* it is sent.

## What can PHP do?

PHP powers a huge share of the web, from small school websites in Kisumu to large platforms such as WordPress. With PHP you can:

- Build pages whose content changes: today's date, the logged-in user's name, the latest products.
- Read and process data sent from HTML forms (sign-up forms, M-Pesa payment callbacks, contact forms).
- Store and fetch data in databases such as MySQL and MariaDB.
- Remember visitors between pages with sessions and cookies.
- Create, read and write files on the server, send email and talk to other web services (APIs).
- Control who can see what, for example an admin dashboard that only staff can open.

## How a PHP page is served

Here is what happens when a learner opens `https://example.co.ke/welcome.php`:

1. The browser sends a **request** to the server for `welcome.php`.
2. The web server (for example Apache) sees the `.php` extension and hands the file to the PHP engine.
3. PHP runs the code in the file from top to bottom and builds the output.
4. The server sends that output back to the browser as the **response**.
5. The browser displays the HTML. It has no idea PHP was involved.

This is the big difference between PHP and JavaScript in the browser. JavaScript in a web page runs on the visitor's computer; PHP runs on your server. That is why PHP can safely hold things like database passwords: they never leave the server.

## A first look

A PHP file usually mixes ordinary HTML with PHP blocks. Code inside `<?php ... ?>` is run; everything outside it is sent as-is.

```example php
<!DOCTYPE html>
<html>
<body>
<h1>Karibu!</h1>
<p><?php echo "This line was written by PHP."; ?></p>
</body>
</html>
```

```output
<!DOCTYPE html>
<html>
<body>
<h1>Karibu!</h1>
<p>This line was written by PHP.</p>
</body>
</html>
```

Notice that the output contains no PHP at all. The `<?php echo ...; ?>` part was replaced by the text it produced. If the visitor chooses "View page source" in their browser, this output is exactly what they would see.

> **Note:** In this tutorial, PHP examples are not run in your browser (PHP needs a server). Each example is shown together with its exact output, so you can compare it with what you get on your own computer.

## PHP can make decisions

The real power of PHP is that the output can be different each time. The next example calculates a value instead of just printing fixed text.

```example php
<?php
$county = "Kisumu";
$students = 120;
$perClass = 40;

echo "$county has " . ($students / $perClass) . " classes.";
```

```output
Kisumu has 3 classes.
```

Do not worry about the details yet. You will learn about variables (the names that start with `$`) and the `.` operator in the coming lessons.

## Why learn PHP?

- **It is everywhere.** Almost every shared hosting plan in Kenya (and the world) supports PHP out of the box, often with cPanel.
- **It is free and open source**, and it runs on Windows, Linux and macOS.
- **It is beginner friendly** but grows with you: modern PHP 8 has types, classes, enums and good performance.
- **It works well with MySQL/MariaDB**, the most common databases on cheap hosting.
- **Huge ecosystem:** WordPress, Laravel, Drupal, Moodle and many more are built with PHP.

## What you should know first

Before starting, you should be comfortable with basic **HTML** (and ideally a little CSS). PHP mostly produces HTML, so knowing what good HTML looks like helps a lot. Any programming experience (for example from our JavaScript or Python tutorials) is a bonus but not required.

## What is a PHP file?

- A PHP file has the extension `.php`.
- It can contain text, HTML, CSS, JavaScript and PHP code.
- PHP code is run on the server, and the result is returned to the browser as plain HTML.

> **Tip:** This tutorial targets **PHP 8.2**. Most examples also work on PHP 8.0 and 8.1, but a few features (such as `match`) do not exist in PHP 7. Check your version with `php -v`, which you will do in the next lesson, [Installing PHP](installing-php).

```exercise
Q: Complete the opening tag that starts a block of PHP code.
<___ echo "Habari"; ?>
A: ?php
H: It starts with a question mark followed by the language name.
```

```exercise
Q: Which file extension tells the server to run a file through PHP?
welcome.___
A: php
```
