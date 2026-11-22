---
title: PHP Constants
module: 2
minutes: 9
---

A **constant** is a name for a value that cannot change while the script runs. Use constants for values that are fixed for the whole application: a VAT rate, the site name, the maximum upload size, or the number of days in a week.

## Constants vs variables

| | Variable | Constant |
|---|---|---|
| Starts with `$` | Yes | No |
| Can change | Yes | No, once defined it is fixed |
| Scope | local inside functions | available everywhere, including inside functions |
| Naming convention | `$camelCase` | `UPPER_CASE` |

## Creating constants with const

The modern way is the `const` keyword. Constant names do not start with `$`, and by convention they are written in capital letters with underscores.

```example php
<?php
const SITE_NAME = "Skope Digital Academy";
const VAT_RATE = 0.16;

$price = 2000;
echo SITE_NAME . "\n";
echo "VAT on KSh $price is KSh " . $price * VAT_RATE . "\n";
```

```output
Skope Digital Academy
VAT on KSh 2000 is KSh 320
```

Notice that constants are not replaced inside double-quoted strings. Writing `"Rate: VAT_RATE"` would print the words `VAT_RATE` literally, so constants must be joined to strings with the `.` operator.

## Creating constants with define()

The older `define()` function does the same thing. Its first argument is the name as a string, the second is the value.

```example php
<?php
define("MAX_STUDENTS", 40);
define("COUNTRY", "Kenya");

echo "Each class takes up to " . MAX_STUDENTS . " students in " . COUNTRY . ".";
```

```output
Each class takes up to 40 students in Kenya.
```

### const or define()?

- `const` is checked when the file is compiled, so it must be used at the top level of a file (or inside a class). It cannot be placed inside an `if` block or a function.
- `define()` is a normal function call, so it can be used anywhere, including conditionally, and the name can be built at run time.

```example php
<?php
$mode = "live";

if ($mode === "live") {
    define("PAYMENT_URL", "https://api.example.co.ke/pay");
} else {
    define("PAYMENT_URL", "https://sandbox.example.co.ke/pay");
}

echo PAYMENT_URL;
```

```output
https://api.example.co.ke/pay
```

For everyday use, prefer `const`. Reach for `define()` when you need to decide the value at run time, as above.

## Constants are global

Unlike variables, constants can be used inside any function without the `global` keyword.

```example php
<?php
const SCHOOL = "Kisumu Girls";

function badge(string $student): string
{
    return "$student, " . SCHOOL;
}

echo badge("Atieno");
```

```output
Atieno, Kisumu Girls
```

## Constants cannot be changed

Trying to define the same constant twice produces a warning and the original value stays. Trying to assign to it with `=` is a syntax error.

```php
<?php
const LIMIT = 10;
LIMIT = 20;   // Parse error: syntax error, unexpected token "="
```

> **Tip:** Use `defined("NAME")` to check whether a constant exists before using it. This is a common pattern in configuration files: `if (!defined("APP_START")) { exit; }` stops a file from being opened directly.

## Array constants

A constant can also hold an array, which is handy for fixed lists of options.

```example php
<?php
const PAYMENT_METHODS = ["M-Pesa", "Airtel Money", "Card"];

echo "We accept " . count(PAYMENT_METHODS) . " methods.\n";
echo "Most popular: " . PAYMENT_METHODS[0];
```

```output
We accept 3 methods.
Most popular: M-Pesa
```

## Built-in (predefined) constants

PHP comes with many constants already defined. Some useful ones:

| Constant | Meaning |
|---|---|
| `PHP_VERSION` | the running PHP version, e.g. `8.2.12` |
| `PHP_EOL` | the correct end-of-line character for the operating system |
| `PHP_INT_MAX` | the largest integer |
| `M_PI` | the number pi |
| `E_ALL` | "report all errors" setting |

## Magic constants

**Magic constants** start and end with two underscores. Their value depends on *where* they are used in your code.

| Magic constant | Gives you |
|---|---|
| `__LINE__` | the current line number |
| `__FILE__` | the full path of the current file |
| `__DIR__` | the folder of the current file |
| `__FUNCTION__` | the name of the current function |
| `__CLASS__` | the name of the current class |

```example php
<?php
function report()
{
    return "Running " . __FUNCTION__ . " on line " . __LINE__;
}

echo report();
```

```output
Running report on line 4
```

`__DIR__` is extremely useful with [include and require](include-require), because it lets you build file paths that work no matter which folder the script was started from.

```exercise
Q: Create a constant named RATE with the value 0.16 using the const keyword.
___ RATE = 0.16;
A: const
```

```exercise
Q: Create the same constant using a function instead.
___("RATE", 0.16);
A: define
```

```exercise
Q: Which magic constant gives the folder of the current file?
require ___ . "/config.php";
A: __DIR__
```
