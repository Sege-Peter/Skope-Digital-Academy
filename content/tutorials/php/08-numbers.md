---
title: PHP Numbers and Math
module: 2
minutes: 12
---

PHP has two number types: **integers** (whole numbers) and **floats** (numbers with decimals). This lesson shows how to check them, convert them, and use PHP's built-in math functions for everyday tasks such as rounding prices or picking random numbers.

## Integers and floats

```example php
<?php
$students = 35;        // int
$average = 72.4;       // float
$big = 2.5e6;          // float in exponent form

var_dump($students, $average, $big);
var_dump(10 / 4);      // division can produce a float
var_dump(10 / 5);      // still a float... or is it?
```

```output
int(35)
float(72.4)
float(2500000)
float(2.5)
int(2)
```

When `/` divides evenly between two integers, the result is an `int`; otherwise it is a `float`. For whole-number division that always gives an integer, use `intdiv()`.

## Checking number types

| Function | Returns `true` when... |
|---|---|
| `is_int($x)` | `$x` is an integer |
| `is_float($x)` | `$x` is a float |
| `is_numeric($x)` | `$x` is a number **or** a numeric string such as `"45"` or `"4.5"` |
| `is_nan($x)` | `$x` is "not a number" (for example `sqrt(-1)`) |

```example php
<?php
var_dump(is_int(5));
var_dump(is_float(5));
var_dump(is_numeric("2500"));
var_dump(is_numeric("25 bob"));
```

```output
bool(true)
bool(false)
bool(true)
bool(false)
```

`is_numeric()` is very useful for checking form input, because everything sent from a form arrives as a string.

## Converting to numbers

```example php
<?php
$fromForm = "1500";
$price = (int) $fromForm;
var_dump($price);
var_dump((float) "12.75");
var_dump(intval("08"));
var_dump(floatval("3.9kg"));
```

```output
int(1500)
float(12.75)
int(8)
float(3.9)
```

## Limits: infinity and NaN

Integers have a maximum size (`PHP_INT_MAX`). If a calculation goes beyond it, PHP switches to a float. Floats that grow too big become `INF` (infinity).

```example php
<?php
var_dump(PHP_INT_MAX + 1);
var_dump(1.8e308);
var_dump(is_nan(sqrt(-1)));
```

```output
float(9.223372036854776E+18)
float(INF)
bool(true)
```

> **Note:** Dividing by zero with `/` or `%` throws a `DivisionByZeroError` in PHP 8, which stops your script unless you catch it. Always check a divisor that comes from user input.

## Math functions

### Rounding

| Function | What it does | `x = 4.567` |
|---|---|---|
| `round($x)` | nearest whole number | 5 |
| `round($x, 2)` | nearest with 2 decimals | 4.57 |
| `floor($x)` | always down | 4 |
| `ceil($x)` | always up | 5 |

```example php
<?php
$bill = 1234.567;
echo round($bill) . "\n";
echo round($bill, 2) . "\n";
echo floor($bill) . "\n";
echo ceil($bill) . "\n";

// How many 14-seater matatus for 40 passengers?
echo ceil(40 / 14) . " matatus\n";
```

```output
1235
1234.57
1234
1235
3 matatus
```

### Min, max, absolute value and powers

```example php
<?php
$marks = [67, 82, 45, 90, 73];
echo max($marks) . "\n";
echo min($marks) . "\n";
echo min(12, 7, 30) . "\n";
echo abs(-250) . "\n";
echo sqrt(81) . "\n";
echo pow(2, 10) . "\n";
echo 2 ** 3 . "\n";
echo intdiv(17, 5) . " remainder " . 17 % 5 . "\n";
echo pi() . "\n";
```

```output
90
45
7
250
9
1024
8
3 remainder 2
3.1415926535898
```

### Random numbers

`random_int($min, $max)` returns a random whole number, including both ends. It is cryptographically secure, so it is safe for things like one-time PIN codes. (`rand()` and `mt_rand()` also exist, but are not safe for secrets.)

```php
<?php
$otp = random_int(100000, 999999);
echo "Your verification code is $otp";
// e.g. Your verification code is 482915  (different every time)
```

## Formatting numbers for display

`number_format()` adds thousands separators and fixes the number of decimals, perfect for prices.

```example php
<?php
$revenue = 1250000.5;
echo number_format($revenue) . "\n";
echo number_format($revenue, 2) . "\n";
echo "KSh " . number_format(3499.999, 2) . "\n";
echo number_format(1234.5, 2, ",", ".") . "\n";
```

```output
1,250,001
1,250,000.50
KSh 3,500.00
1.234,50
```

The last line uses a comma for decimals and a dot for thousands, the style used in many European countries.

> **Tip:** Do your calculations with raw numbers and only call `number_format()` at the very end, when printing. The result of `number_format()` is a string with commas, so you cannot do math with it any more.

```exercise
Q: Round 7.256 to two decimal places.
echo ___(7.256, 2);
A: round
```

```exercise
Q: Always round 4.1 up to the next whole number.
echo ___(4.1);
A: ceil
```

```exercise
Q: Print 15000 as 15,000.
echo ___(15000);
A: number_format
```
