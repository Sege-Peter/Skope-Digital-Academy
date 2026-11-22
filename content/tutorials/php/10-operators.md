---
title: PHP Operators
module: 2
minutes: 14
---

**Operators** are symbols that do something with values: add them, compare them, combine conditions or join text. PHP groups them into families. You will use almost all of these every day.

## Arithmetic operators

| Operator | Name | Example | Result |
|---|---|---|---|
| `+` | addition | `10 + 3` | 13 |
| `-` | subtraction | `10 - 3` | 7 |
| `*` | multiplication | `10 * 3` | 30 |
| `/` | division | `10 / 4` | 2.5 |
| `%` | modulus (remainder) | `10 % 3` | 1 |
| `**` | exponent (power) | `10 ** 3` | 1000 |

```example php
<?php
$total = 2450;
$people = 4;
echo "Each pays: " . $total / $people . "\n";
echo "Left over shillings: " . $total % $people . "\n";
echo "Is 17 even? " . (17 % 2 === 0 ? "yes" : "no") . "\n";
```

```output
Each pays: 612.5
Left over shillings: 2
Is 17 even? no
```

The modulus `%` is perfect for checking whether a number is even (`$n % 2 === 0`) or for doing something every *n*th time in a loop.

## Assignment operators

`=` stores a value. The combined forms do a calculation and store the result in one step.

| Operator | Same as |
|---|---|
| `$x += 5` | `$x = $x + 5` |
| `$x -= 5` | `$x = $x - 5` |
| `$x *= 5` | `$x = $x * 5` |
| `$x /= 5` | `$x = $x / 5` |
| `$x %= 5` | `$x = $x % 5` |
| `$x .= "a"` | `$x = $x . "a"` |
| `$x ??= 5` | `$x = $x ?? 5` |

```example php
<?php
$balance = 1000;
$balance += 500;    // deposit
$balance -= 200;    // withdrawal
$balance *= 2;
echo $balance;
```

```output
2600
```

## Increment and decrement

| Operator | Meaning |
|---|---|
| `++$x` | add 1, then return the new value |
| `$x++` | return the current value, then add 1 |
| `--$x` | subtract 1, then return the new value |
| `$x--` | return the current value, then subtract 1 |

```example php
<?php
$a = 5;
echo $a++ . "\n";   // prints 5, then $a becomes 6
echo $a . "\n";
echo ++$a . "\n";   // $a becomes 7, then prints 7
```

```output
5
6
7
```

## Comparison operators

Comparisons give a boolean (`true` or `false`).

| Operator | Name | True when... |
|---|---|---|
| `==` | equal | values are equal after type juggling |
| `===` | identical | values are equal **and** of the same type |
| `!=` or `<>` | not equal | values differ after type juggling |
| `!==` | not identical | value or type differs |
| `<`, `>`, `<=`, `>=` | less/greater than | as in maths |
| `<=>` | spaceship | returns -1, 0 or 1 |

```example php
<?php
var_dump(5 == "5");
var_dump(5 === "5");
var_dump(5 !== "5");
var_dump(0 == "");
var_dump(null == false);
echo (3 <=> 7) . " " . (7 <=> 7) . " " . (9 <=> 7);
```

```output
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
-1 0 1
```

> **Tip:** Use `===` and `!==` by default. Loose comparison (`==`) converts types first, which leads to surprising results such as `null == false` being `true`. Strict comparison avoids a whole class of bugs.

The spaceship operator `<=>` is mainly used when sorting (see [Array Functions](array-functions)).

## Logical operators

Logical operators combine conditions.

- `&&` (or the word `and`): true when **both** sides are true.
- `||` (or the word `or`): true when **at least one** side is true.
- `!` (not): flips true to false and false to true.
- `xor` (exclusive or): true when **exactly one** side is true.

```example php
<?php
$age = 20;
$hasId = true;
$isMember = false;

var_dump($age >= 18 && $hasId);
var_dump($isMember || $age > 65);
var_dump(!$isMember);
```

```output
bool(true)
bool(false)
bool(true)
```

> **Warning:** `and` and `or` have *lower* priority than `=`. So `$ok = true and false;` sets `$ok` to `true`! Always use `&&` and `||` unless you know exactly why you need the word forms.

PHP stops evaluating as soon as the answer is known ("short-circuit"). In `$user && $user->isAdmin()`, the method is never called if `$user` is empty.

## String operators

There are only two: `.` joins strings, and `.=` appends to a string. See [Strings](strings).

## The ternary operator

`condition ? valueIfTrue : valueIfFalse` is a short `if/else` that produces a value.

```example php
<?php
$marks = 48;
$result = $marks >= 50 ? "Pass" : "Fail";
echo $result . "\n";

$items = 1;
echo "You have $items item" . ($items === 1 ? "" : "s") . "\n";
```

```output
Fail
You have 1 item
```

## Null coalescing operator ??

`$a ?? $b` returns `$a` if it exists and is not `null`, otherwise `$b`. It never raises an "undefined" warning, which makes it ideal for optional form fields and settings.

```example php
<?php
$settings = ["theme" => "dark"];

$theme = $settings["theme"] ?? "light";
$lang = $settings["lang"] ?? "en";
echo "$theme / $lang\n";

$nickname = null;
echo $nickname ?? "Guest";
```

```output
dark / en
Guest
```

## Operator precedence

When an expression has several operators, PHP follows rules similar to school maths: `**` first, then `*`, `/`, `%`, then `+`, `-`, then comparisons, then `&&`, then `||`, then assignment. Use brackets to make your intent clear.

```example php
<?php
echo 2 + 3 * 4 . "\n";
echo (2 + 3) * 4 . "\n";
echo -2 ** 2 . "\n";
```

```output
14
20
-4
```

The last line shows that `**` binds tighter than the minus sign, so it is calculated as `-(2 ** 2)`.

```exercise
Q: Use the operator that checks value AND type.
if ($input ___ "yes") { echo "OK"; }
A: ===
```

```exercise
Q: Give $name a default of "Guest" if $_GET["name"] is missing.
$name = $_GET["name"] ___ "Guest";
A: ??
```

```exercise
Q: Add 100 to $points using a combined assignment operator.
$points ___ 100;
A: +=
```
