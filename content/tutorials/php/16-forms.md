---
title: PHP Superglobals and Form Handling
module: 5
module_title: PHP on the Web
minutes: 16
---

Forms are how visitors talk to your website: logging in, registering for a course, sending a message, searching for a product. HTML builds the form; PHP receives and processes what the visitor typed. The bridge between the two is a set of special arrays called **superglobals**.

## What are superglobals?

Superglobals are built-in arrays that PHP fills in for every request. They are available everywhere in your script, even inside functions, without the `global` keyword.

| Superglobal | Contains |
|---|---|
| `$_GET` | values sent in the URL query string (`?q=php&page=2`) |
| `$_POST` | values sent in the body of a POST request (most forms) |
| `$_REQUEST` | `$_GET`, `$_POST` and `$_COOKIE` combined (avoid; be specific) |
| `$_SERVER` | information about the request and server (method, URL, IP...) |
| `$_SESSION` | data stored for the current user across pages |
| `$_COOKIE` | cookies sent by the browser |
| `$_FILES` | uploaded files |
| `$_ENV` | environment variables |
| `$GLOBALS` | every global variable in the script |

This lesson focuses on `$_GET`, `$_POST` and `$_SERVER`. Sessions and cookies have their own lesson: [Sessions and Cookies](sessions-cookies).

## GET requests and $_GET

When a form uses `method="get"`, the browser adds the field values to the URL. Every `name` attribute becomes a key in `$_GET`.

Here is a simple search form, saved as `search.html`:

```html
<form action="welcome.php" method="get">
  <label>Name: <input type="text" name="name"></label>
  <label>County: <input type="text" name="county"></label>
  <button type="submit">Send</button>
</form>
```

If Halima types her name and "Kisumu" and presses **Send**, the browser requests:

```text
welcome.php?name=Halima&county=Kisumu
```

And `welcome.php` reads the values like this:

```example php
<?php
$name = $_GET["name"] ?? "visitor";
$county = $_GET["county"] ?? "Kenya";
?>
<h1>Welcome, <?= htmlspecialchars($name) ?>!</h1>
<p>Greetings to everyone in <?= htmlspecialchars($county) ?>.</p>
```

```output
<h1>Welcome, Halima!</h1>
<p>Greetings to everyone in Kisumu.</p>
```

Two important habits are already in this small example:

- `?? "visitor"` gives a default when the field is missing, so someone opening `welcome.php` directly does not get a warning.
- `htmlspecialchars()` makes the value safe to print in HTML. The [Form Validation](form-validation) lesson explains why this matters so much.

### When to use GET

GET values are visible in the address bar, saved in browser history and can be bookmarked or shared. Use GET for things that only **read** data: searches, filters, page numbers.

## POST requests and $_POST

With `method="post"`, the values travel inside the request body instead of the URL. Use POST for anything that **changes** data or is private: logins, registrations, payments, messages.

A common pattern is a single file that both shows the form and processes it. `$_SERVER["REQUEST_METHOD"]` tells you whether the page was just opened (GET) or the form was submitted (POST). The output below shows the HTML returned after the visitor submits `email = juma@example.com` and `course = PHP`.

```example php
<?php
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $course = $_POST["course"] ?? "";
    $message = "Thanks! We will email " . htmlspecialchars($email)
             . " about the " . htmlspecialchars($course) . " course.";
}
?>
<form method="post">
  <input type="email" name="email" placeholder="Email">
  <select name="course">
    <option>PHP</option>
    <option>Python</option>
  </select>
  <button>Register</button>
</form>
<p><?= $message ?></p>
```

```output
<form method="post">
  <input type="email" name="email" placeholder="Email">
  <select name="course">
    <option>PHP</option>
    <option>Python</option>
  </select>
  <button>Register</button>
</form>
<p>Thanks! We will email juma@example.com about the PHP course.</p>
```

On the first visit (a GET request), the `if` block is skipped and the paragraph at the bottom is simply empty: `<p></p>`.

> **Note:** A form with no `action` attribute submits to the same URL it came from. That is what makes the "one file shows and handles the form" pattern work.

### GET vs POST

| | GET | POST |
|---|---|---|
| Data is sent in | the URL | the request body |
| Visible in address bar/history | yes | no |
| Can be bookmarked | yes | no |
| Size limit | small (about 2,000 characters is safe) | large (set by the server) |
| Use for | searching, filtering, reading | logging in, saving, paying |

> **Warning:** POST is not encrypted by itself. Anyone watching the network can read it unless your site uses **HTTPS**. Always run login and payment forms over HTTPS.

## Checkboxes and multiple values

If several inputs share a name ending in `[]`, PHP collects them into an array.

```html
<input type="checkbox" name="days[]" value="Mon"> Monday
<input type="checkbox" name="days[]" value="Wed"> Wednesday
<input type="checkbox" name="days[]" value="Fri"> Friday
```

The handler below receives them as an array. The output shows what happens when the learner ticks Monday and Friday.

```example php
<?php
$days = $_POST["days"] ?? [];

if (count($days) === 0) {
    echo "Please choose at least one day.";
} else {
    echo "Classes on: " . htmlspecialchars(implode(", ", $days));
}
```

```output
Classes on: Mon, Fri
```

Unticked checkboxes are **not sent at all**, which is why the `?? []` default is essential.

## Useful $_SERVER values

| Key | Example value |
|---|---|
| `$_SERVER["REQUEST_METHOD"]` | `GET` or `POST` |
| `$_SERVER["REQUEST_URI"]` | `/courses/php.php?page=2` |
| `$_SERVER["HTTP_HOST"]` | `localhost` |
| `$_SERVER["REMOTE_ADDR"]` | the visitor's IP address |
| `$_SERVER["HTTP_USER_AGENT"]` | the visitor's browser description |
| `$_SERVER["SCRIPT_NAME"]` | `/courses/php.php` |

The output below is for a GET request to `http://localhost/courses/php.php` from your own computer.

```example php
<?php
echo "Method: " . $_SERVER["REQUEST_METHOD"] . "\n";
echo "Page: " . $_SERVER["SCRIPT_NAME"] . "\n";
echo "Your IP: " . $_SERVER["REMOTE_ADDR"] . "\n";
```

```output
Method: GET
Page: /courses/php.php
Your IP: 127.0.0.1
```

> **Warning:** Values such as `HTTP_USER_AGENT`, `HTTP_REFERER` and even `PHP_SELF` can be controlled by the visitor. Treat them like form input: never print them without `htmlspecialchars()`.

Receiving data is only half the job. Next you will learn to **check** it and keep your site safe in [Form Validation and Security](form-validation).

```exercise
Q: Read the "q" field from a form that uses method="get".
$search = ___["q"] ?? "";
A: $_GET
```

```exercise
Q: Check whether the form was submitted with POST.
if ($_SERVER["___"] === "POST") { ... }
A: REQUEST_METHOD
```
