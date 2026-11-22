---
title: PHP if, else, match and switch
module: 3
module_title: Control Flow
minutes: 15
---

Programs become useful when they make decisions: show the admin menu only to admins, print "Pass" or "Fail", charge delivery only outside town. PHP gives you `if`, `else`, `elseif`, `switch` and the modern `match` expression to do this.

## The if statement

`if` runs a block of code only when its condition is `true`.

```example php
<?php
$balance = 1200;

if ($balance > 1000) {
    echo "You qualify for a free data bundle.";
}
```

```output
You qualify for a free data bundle.
```

The condition goes in round brackets, and the code to run goes in curly braces `{ }`. Any expression can be a condition; PHP converts it to a boolean using the rules in [Data Types](data-types).

## if ... else

Add `else` for the code that should run when the condition is `false`.

```example php
<?php
$hour = 19;

if ($hour < 12) {
    echo "Habari ya asubuhi!";
} else {
    echo "Habari ya jioni!";
}
```

```output
Habari ya jioni!
```

## if ... elseif ... else

To test several conditions in order, chain them with `elseif`. PHP runs the **first** block whose condition is true and skips the rest.

```example php
<?php
$marks = 68;

if ($marks >= 80) {
    $grade = "A";
} elseif ($marks >= 65) {
    $grade = "B";
} elseif ($marks >= 50) {
    $grade = "C";
} else {
    $grade = "Fail";
}

echo "Marks: $marks, Grade: $grade";
```

```output
Marks: 68, Grade: B
```

Order matters. If we had checked `>= 50` first, a score of 68 would have received a C.

> **Tip:** Combine conditions with `&&` and `||` (see [Operators](operators)): `if ($age >= 18 && $hasId) { ... }`. For very simple choices, the ternary operator `$x ? $a : $b` is a compact alternative.

## Nested if

You can put an `if` inside another `if`. Keep nesting shallow, though; two levels is usually the most that stays readable.

```example php
<?php
$loggedIn = true;
$role = "teacher";

if ($loggedIn) {
    if ($role === "admin") {
        echo "Welcome to the admin panel.";
    } else {
        echo "Welcome back, $role.";
    }
} else {
    echo "Please log in.";
}
```

```output
Welcome back, teacher.
```

## The switch statement

`switch` compares one value against many possible cases. It is an older but still common alternative to a long `elseif` chain.

```example php
<?php
$day = "Sat";

switch ($day) {
    case "Sat":
    case "Sun":
        echo "Weekend: the shop opens at 10am.";
        break;
    case "Fri":
        echo "Friday: we close early at 4pm.";
        break;
    default:
        echo "Weekday: open 8am to 6pm.";
}
```

```output
Weekend: the shop opens at 10am.
```

Three things to remember about `switch`:

- Each `case` should end with `break`. Without it, PHP "falls through" and keeps running the next case's code too. Above, `"Sat"` deliberately falls through to share the weekend message.
- `default` runs when no case matches.
- `switch` uses **loose** comparison (`==`), so `"1"` matches `case 1`.

## The match expression (PHP 8)

`match` is the modern, safer replacement for most `switch` statements. It **returns a value**, uses **strict** comparison (`===`), never falls through, and needs no `break`.

```example php
<?php
$statusCode = 404;

$message = match ($statusCode) {
    200 => "OK",
    301, 302 => "Redirected",
    404 => "Page not found",
    500 => "Server error",
    default => "Unknown status",
};

echo $message;
```

```output
Page not found
```

Separate several values with commas to share one result, as in `301, 302`.

### match with conditions

A neat trick is `match (true)`. Each arm is then a condition, and the first one that is `true` wins. This makes grading code very tidy.

```example php
<?php
$marks = 74;

$grade = match (true) {
    $marks >= 80 => "A",
    $marks >= 65 => "B",
    $marks >= 50 => "C",
    default => "Fail",
};

echo "Grade: $grade";
```

```output
Grade: B
```

> **Warning:** If no arm matches and there is no `default`, `match` throws an `UnhandledMatchError` and stops the script. A `switch` would silently do nothing. Add a `default` arm unless you really want the error.

### switch or match?

| | `switch` | `match` |
|---|---|---|
| Comparison | loose `==` | strict `===` |
| Needs `break` | yes | no |
| Returns a value | no | yes |
| Several statements per case | yes | no, one expression per arm |
| No match | does nothing | throws an error |

Use `match` when you are choosing a **value**. Use `if`/`elseif` or `switch` when each branch needs several statements.

## Alternative syntax for templates

Inside HTML templates, PHP offers a colon syntax that is easier to read than curly braces scattered between tags.

```example php
<?php $cartItems = 0; ?>
<?php if ($cartItems > 0): ?>
<p>You have <?= $cartItems ?> items.</p>
<?php else: ?>
<p>Your cart is empty.</p>
<?php endif; ?>
```

```output
<p>Your cart is empty.</p>
```

```exercise
Q: Complete the condition so the message prints for 18 and older.
if ($age ___ 18) {
    echo "Adult";
}
A: >=
```

```exercise
Q: Complete the chain with the right keywords.
if ($x > 0) {
    echo "positive";
} ___ ($x < 0) {
    echo "negative";
} ___ {
    echo "zero";
}
A: elseif / else if | else
```

```exercise
Q: Fill in the PHP 8 expression that returns a value and uses strict comparison.
$size = ___ ($code) {
    "S" => "Small",
    "L" => "Large",
    ___ => "Medium",
};
A: match | default
```
