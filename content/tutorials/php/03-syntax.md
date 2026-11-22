---
title: PHP Syntax, echo and print
module: 1
minutes: 12
---

Every language has grammar rules. In PHP they are short and easy to remember. This lesson covers how PHP code is marked, how statements end, how case sensitivity works, and the two most common ways to send output: `echo` and `print`.

## PHP tags

PHP code lives between an opening tag `<?php` and a closing tag `?>`. A file can switch between HTML and PHP as many times as you like.

```example php
<p>Before PHP</p>
<?php echo "<p>Inside PHP</p>"; ?>
<p>After PHP</p>
```

```output
<p>Before PHP</p>
<p>Inside PHP</p><p>After PHP</p>
```

Look closely at the output. PHP swallows a single newline that directly follows a closing `?>`, which is why "Inside PHP" and "After PHP" ended up on the same line. In HTML this makes no visible difference, but it is useful to know when you compare page sources.

> **Tip:** If a file contains **only** PHP (no HTML), leave out the closing `?>` at the end of the file. This avoids accidental blank lines being sent to the browser, which can break things like redirects and cookies later on.

## Statements end with a semicolon

Each instruction in PHP is called a **statement**, and each statement ends with a semicolon `;`. Forgetting it is the most common beginner error, and PHP will stop with a "syntax error, unexpected ..." message.

```php
<?php
echo "One";   // correct
echo "Two"    // Parse error: the semicolon is missing
```

The last statement right before a `?>` may skip the semicolon, but it is good practice to always write it.

## Whitespace and case sensitivity

PHP ignores extra spaces, tabs and blank lines between code, so you can format your code to be readable.

Case sensitivity has two rules:

- **Keywords and function names are not case-sensitive.** `echo`, `ECHO` and `Echo` all work, as do `strlen()` and `STRLEN()`.
- **Variable names are case-sensitive.** `$name`, `$Name` and `$NAME` are three different variables.

```example php
<?php
$town = "Kakamega";
ECHO "Town: " . $town . "\n";
Echo "Again: " . $town . "\n";
echo "Capital T: " . ($Town ?? "not set") . "\n";
```

```output
Town: Kakamega
Again: Kakamega
Capital T: not set
```

`$Town` with a capital T was never created, so PHP used the fallback text. (The `??` operator is covered in [Operators](operators).) Stick to lower-case keywords; mixing styles only confuses readers.

## echo

`echo` sends text to the output. It is a language construct rather than a function, so parentheses are optional. You can pass several values separated by commas.

```example php
<?php
echo "Habari yako?";
echo "<br>";
echo "Learning ", "PHP ", "is ", "fun!";
echo "<br>";
echo("Brackets work too.");
```

```output
Habari yako?<br>Learning PHP is fun!<br>Brackets work too.
```

Because the output goes to a browser, we use `<br>` to start a new line. You can echo any HTML you like, including tags with attributes.

## print

`print` does nearly the same job. The differences are small:

| | `echo` | `print` |
|---|---|---|
| Number of values | one or more, comma-separated | exactly one |
| Return value | none | always returns `1` |
| Speed | very slightly faster | very slightly slower |

```example php
<?php
print "Jambo from print!\n";
$result = print "This line is printed, ";
print "and print returned $result.\n";
```

```output
Jambo from print!
This line is printed, and print returned 1.
```

In everyday code most developers use `echo`. You will see `print` in older code and occasionally inside expressions.

## Single vs double quotes

Strings in PHP can use single or double quotes, and they behave differently:

- **Double quotes** replace variables with their values and understand escape sequences like `\n` (newline) and `\t` (tab).
- **Single quotes** print almost everything exactly as typed.

```example php
<?php
$name = "Achieng";
echo "Hello, $name!\n";
echo 'Hello, $name!\n';
echo "\n";
```

```output
Hello, Achieng!
Hello, $name!\n
```

The [Strings](strings) lesson goes deeper into this.

## The short echo tag

When you only need to print one value inside HTML, PHP offers a shortcut: `<?= ... ?>` means the same as `<?php echo ...; ?>`.

```example php
<?php $course = "PHP Basics"; ?>
<h2>Course: <?= $course ?></h2>
```

```output
<h2>Course: PHP Basics</h2>
```

The short echo tag is always available in modern PHP and makes templates much tidier.

> **Warning:** Do not confuse `<?=` with the old "short open tag" `<?` (without `php`). The plain `<?` form is often disabled on servers, so always write `<?php` in full.

```exercise
Q: Complete the code so it prints the word Karibu.
<?php
___ "Karibu";
A: echo / print
```

```exercise
Q: Use the short echo tag to print the variable $user.
<p>Welcome, <___ $user ?></p>
A: ?=
H: It is the opening tag with an equals sign.
```
