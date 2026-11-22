---
title: PHP include and require
module: 5
minutes: 12
---

Real websites have many pages that share the same parts: the header with the logo and menu, the footer, the database connection, a set of helper functions. Copying that code into every page would be a maintenance nightmare. Instead, you put each shared part in its own file and pull it in with `include` or `require`.

## How it works

`include "file.php";` takes the contents of `file.php` and runs it **at that spot**, as if you had typed it there. The included file can contain HTML, PHP, or both, and it shares the variables of the place where it was included.

Suppose you create these two files.

`header.php`:

```php
<header>
  <h1><?= $pageTitle ?? "Skope Digital Academy" ?></h1>
  <nav><a href="/">Home</a> | <a href="/courses.php">Courses</a></nav>
</header>
```

`footer.php`:

```php
<footer>Skope Digital Academy, Kisumu</footer>
```

Now any page can use them. Here is `index.php`:

```example php
<?php $pageTitle = "Welcome"; ?>
<?php include "header.php"; ?>
<main>
  <p>Learn coding the practical way.</p>
</main>
<?php include "footer.php"; ?>
```

```output
<header>
  <h1>Welcome</h1>
  <nav><a href="/">Home</a> | <a href="/courses.php">Courses</a></nav>
</header>
<main>
  <p>Learn coding the practical way.</p>
</main>
<footer>Skope Digital Academy, Kisumu</footer>
```

Change the menu in `header.php` once, and every page on the site updates. Notice also that `header.php` could read `$pageTitle`, which was set in `index.php` before the include.

## include vs require

Both do the same job. The difference is what happens when the file **cannot be found**:

| Statement | If the file is missing |
|---|---|
| `include` | shows a **warning** and the script carries on |
| `require` | shows a **fatal error** and the script stops |

Use `require` for files your page cannot work without (configuration, database connection, functions). Use `include` for optional pieces, such as an advert block or a sidebar widget.

```example php
<?php
$result = @include "sidebar-ads.php";   // optional file; @ hides the warning
var_dump($result);
echo "The page still loads without the sidebar.\n";
```

```output
bool(false)
The page still loads without the sidebar.
```

`include` returns `false` when it fails, so you can check the result. The `@` sign suppresses the warning message; use it sparingly, and only when you deliberately handle the failure.

If you used `require "sidebar-ads.php";` instead, PHP would print a warning followed by a fatal error like this (the paths will differ on your computer), and nothing after it would run:

```text
Warning: require(sidebar-ads.php): Failed to open stream: No such file or directory in C:\xampp\htdocs\site\index.php on line 2

Fatal error: Uncaught Error: Failed opening required 'sidebar-ads.php' (include_path='C:\xampp\php\PEAR') in C:\xampp\htdocs\site\index.php:2
```

## include_once and require_once

If a file defines functions or classes, including it twice causes an error ("Cannot redeclare function"). The `_once` versions remember which files were already loaded and skip repeats.

`helpers.php`:

```php
<?php
function money(float $amount): string
{
    return "KSh " . number_format($amount, 2);
}
```

```example php
<?php
require_once "helpers.php";
require_once "helpers.php";   // ignored: already loaded

echo money(2500) . "\n";
echo money(1234567.8);
```

```output
KSh 2,500.00
KSh 1,234,567.80
```

> **Tip:** Use `require_once` for files that define functions, classes or constants. Use plain `include`/`require` for template pieces like headers that you might deliberately output more than once.

## Returning values from an included file

An included file can `return` a value, just like a function. This is the standard way to write configuration files.

`config.php`:

```php
<?php
return [
    "site_name" => "Skope Digital Academy",
    "db_host" => "localhost",
    "items_per_page" => 12,
];
```

```example php
<?php
$config = require "config.php";

echo $config["site_name"] . "\n";
echo "Showing " . $config["items_per_page"] . " courses per page";
```

```output
Skope Digital Academy
Showing 12 courses per page
```

## Reliable paths with __DIR__

A relative path such as `"includes/header.php"` is resolved from the folder of the script that **started** the request, not the file doing the including. That can break when files in different folders include each other. Build paths from `__DIR__` (the folder of the current file) to avoid surprises:

```php
<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/../vendor/autoload.php";
```

## A typical project layout

```text
htdocs/myschool/
  index.php
  courses.php
  includes/
    config.php      (settings, returns an array)
    db.php          (creates the database connection)
    functions.php   (helpers like money() and e())
    header.php
    footer.php
```

Each page starts with something like `require_once __DIR__ . "/includes/functions.php";` and includes the header and footer around its own content.

> **Warning:** Never build an include path from user input, such as `include $_GET["page"] . ".php";`. An attacker could load files you never meant to expose. If you need to choose a page dynamically, check the value against a fixed list of allowed names first.

> **Note:** Files that contain secrets, such as database passwords, are best stored **outside** the public `htdocs`/`public_html` folder, so they can never be downloaded directly even if the server is misconfigured.

```exercise
Q: Load the database file and stop the script if it is missing.
___ "db.php";
A: require / require_once
```

```exercise
Q: Load the functions file only once, using the folder of the current file.
require_once ___ . "/functions.php";
A: __DIR__
```
