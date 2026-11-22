---
title: PHP Sessions and Cookies
module: 5
minutes: 16
---

HTTP, the language browsers use to talk to servers, has no memory. Each request arrives on its own, and the server has no idea that the visitor who just logged in is the same one now opening the dashboard. **Sessions** and **cookies** solve this by letting your site remember a visitor between pages.

## Cookies vs sessions

| | Cookie | Session |
|---|---|---|
| Stored | in the visitor's **browser** | on the **server** |
| Visitor can read/change it | yes | no (only a random ID is in the browser) |
| Size | small (about 4 KB) | as large as you need |
| Lifetime | until its expiry date | until the browser closes or it times out |
| Good for | preferences: theme, language, "remember me" | login state, shopping cart, form messages |

In fact, sessions use a cookie behind the scenes: PHP gives the browser a cookie named `PHPSESSID` holding a long random ID, and keeps the actual data in a file on the server under that ID.

## Starting a session

Call `session_start()` at the top of **every** page that needs the session, before any HTML or `echo`. After that, the `$_SESSION` superglobal works like a normal array whose contents survive from page to page.

`login.php` (after the password has been checked):

```example php
<?php
session_start();

// In a real app these values come from the database
// after password_verify() succeeds.
session_regenerate_id(true);
$_SESSION["user_id"] = 17;
$_SESSION["name"] = "Wanjiku";
$_SESSION["role"] = "student";

echo "Login successful. Go to your dashboard.";
```

```output
Login successful. Go to your dashboard.
```

> **Warning:** `session_start()`, `setcookie()` and `header()` all send HTTP headers, and headers must go out **before** any page content. If even a blank line or space is output first, you get the error "Cannot modify header information - headers already sent". Keep these calls at the very top of the file, and leave out the closing `?>` in PHP-only files.

`session_regenerate_id(true)` gives the visitor a fresh session ID at login. This blocks an attack called **session fixation**, where someone plants a known session ID in a victim's browser before they log in.

## Reading session data on another page

`dashboard.php` checks whether the visitor is logged in. If not, it sends them back to the login page. The output below is what Wanjiku sees after logging in with the code above.

```example php
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$name = htmlspecialchars($_SESSION["name"]);
echo "<h1>Karibu, $name!</h1>\n";
echo "<p>You are logged in as a {$_SESSION['role']}.</p>";
```

```output
<h1>Karibu, Wanjiku!</h1>
<p>You are logged in as a student.</p>
```

Always call `exit` right after a `header("Location: ...")` redirect. The header only *asks* the browser to go elsewhere; without `exit`, PHP would keep running and could still output the protected page.

## A session counter

Session values can be updated like any variable. This page counts how many times the visitor has opened it during the current session. On a visitor's first visit it shows:

```example php
<?php
session_start();

$_SESSION["views"] = ($_SESSION["views"] ?? 0) + 1;

echo "You have opened this page {$_SESSION['views']} time(s).";
```

```output
You have opened this page 1 time(s).
```

Refresh the page and the number goes up: 2, 3, 4... Open the page in a private window and it starts from 1 again, because that window has a different session.

## Logging out

To log out, clear the session array, delete the session cookie and destroy the session data on the server.

```example php
<?php
session_start();

$_SESSION = [];
setcookie(session_name(), "", time() - 3600, "/");
session_destroy();

echo "You have been logged out.";
```

```output
You have been logged out.
```

Setting a cookie with an expiry time in the past is how you tell the browser to delete it.

## Cookies

`setcookie()` asks the browser to store a small value. The browser sends it back with every later request, and PHP puts it in `$_COOKIE`.

```php
setcookie(name, value, options);
```

The most common options are:

| Option | Meaning |
|---|---|
| `expires` | Unix timestamp when it expires (`time() + 86400 * 30` = 30 days) |
| `path` | which part of the site receives it (`"/"` = the whole site) |
| `secure` | only send it over HTTPS |
| `httponly` | hide it from JavaScript (protects against XSS theft) |
| `samesite` | `"Lax"` or `"Strict"` limits sending it from other sites (helps against CSRF) |

This page saves the visitor's chosen theme for 30 days, then reads the cookie. The output shows a returning visitor whose browser sent `theme=dark`.

```example php
<?php
if (isset($_GET["theme"])) {
    setcookie("theme", $_GET["theme"], [
        "expires" => time() + 60 * 60 * 24 * 30,
        "path" => "/",
        "httponly" => true,
        "samesite" => "Lax",
    ]);
}

$theme = $_COOKIE["theme"] ?? "light";
$theme = in_array($theme, ["light", "dark"], true) ? $theme : "light";

echo "<body class=\"theme-$theme\">";
```

```output
<body class="theme-dark">
```

> **Note:** A new cookie is **not** in `$_COOKIE` until the *next* request, because `$_COOKIE` holds what the browser sent with the current request. If you need the value immediately, use the variable you just stored.

Notice that the cookie value is checked against a list of allowed themes before being printed. Cookies come from the browser, so a visitor can change them to anything: treat them exactly like form input.

## Security checklist

- Never store passwords or sensitive data in cookies. Store only an ID or a preference.
- Regenerate the session ID on login with `session_regenerate_id(true)`.
- Set the session cookie options in `php.ini` or with `session_set_cookie_params()`: `httponly`, `secure` (on HTTPS sites) and `samesite`.
- Log users out after a period of inactivity by storing a "last activity" time in the session and checking it on each request.
- Escape session values when you print them; they often started life as user input.

> **Tip:** In Kenya, many people share computers in cyber cafés. Make the logout link easy to find, and do not set long-lived "remember me" cookies by default.

```exercise
Q: Start the session before using $_SESSION.
___();
$_SESSION["cart"] = [];
A: session_start
```

```exercise
Q: Read the "lang" cookie, falling back to "en".
$lang = ___["lang"] ?? "en";
A: $_COOKIE
```

```exercise
Q: Stop the script right after redirecting.
header("Location: login.php");
___;
A: exit / die
```
