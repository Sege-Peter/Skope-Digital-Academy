---
title: PHP Functions
module: 3
minutes: 16
---

A **function** is a named block of code that you can run whenever you need it. Functions let you write a piece of logic once, give it a clear name, and reuse it across your whole site. PHP ships with over a thousand built-in functions (like `strlen()` and `round()`), and you can write your own.

## Defining and calling a function

Use the `function` keyword, a name, round brackets and a body in curly braces. The code inside does nothing until you **call** the function by its name.

```example php
<?php
function greet()
{
    echo "Karibu Skope Digital Academy!\n";
}

greet();
greet();
```

```output
Karibu Skope Digital Academy!
Karibu Skope Digital Academy!
```

Function names follow the same rules as variables (letters, numbers, underscores; no leading number) but have no `$`. They are **not** case-sensitive, though you should always call them exactly as defined.

## Parameters and arguments

**Parameters** are variables listed in the brackets of the definition. The values you pass when calling are the **arguments**.

```example php
<?php
function greetStudent($name, $course)
{
    echo "Hello $name, welcome to $course.\n";
}

greetStudent("Njeri", "PHP");
greetStudent("Baraka", "Python");
```

```output
Hello Njeri, welcome to PHP.
Hello Baraka, welcome to Python.
```

## Returning values

Most functions should calculate something and **return** it rather than echo it. The caller can then decide what to do with the result: print it, store it, or pass it to another function. `return` also ends the function immediately.

```example php
<?php
function addVat($amount)
{
    return $amount * 1.16;
}

$price = addVat(5000);
echo "With VAT: KSh $price\n";
echo "Two items: KSh " . addVat(5000) * 2;
```

```output
With VAT: KSh 5800
Two items: KSh 11600
```

## Default argument values

Give a parameter a default value with `=`. If the caller leaves that argument out, the default is used. Parameters with defaults must come **after** those without.

```example php
<?php
function shippingFee($county, $express = false)
{
    $fee = $county === "Nairobi" ? 200 : 450;
    if ($express) {
        $fee += 300;
    }
    return $fee;
}

echo shippingFee("Nairobi") . "\n";
echo shippingFee("Kisumu") . "\n";
echo shippingFee("Kisumu", true) . "\n";
```

```output
200
450
750
```

## Type declarations

You can declare what **type** each parameter must be, and what type the function returns (after a colon). This catches mistakes early and documents your intent.

```example php
<?php
function average(int $total, int $count): float
{
    return $total / $count;
}

$avg = average(250, 4);
var_dump($avg);
```

```output
float(62.5)
```

Common types are `int`, `float`, `string`, `bool`, `array` and class names. A few special ones:

| Type | Meaning |
|---|---|
| `?string` | a string **or** `null` (nullable) |
| `void` | the function returns nothing |
| `mixed` | any type at all |

You can also allow several types by joining them with a vertical bar, for example `int|float` for "an int or a float". This is called a **union type**.

### Strict types

By default PHP tries to convert arguments to the declared type, so passing the string `"5"` to an `int` parameter quietly becomes `5`. Put `declare(strict_types=1);` on the very first line of a file to switch this off: wrong types then cause a `TypeError`.

```example php
<?php
declare(strict_types=1);

function double(int $n): int
{
    return $n * 2;
}

echo double(21) . "\n";

try {
    echo double("21");
} catch (TypeError $e) {
    echo "Error: wrong type passed to double()";
}
```

```output
42
Error: wrong type passed to double()
```

> **Tip:** Many teams put `declare(strict_types=1);` at the top of every file. It makes bugs show up immediately instead of hiding behind silent conversions. The `try`/`catch` above catches the error so the script can continue.

## Named arguments (PHP 8)

You can pass arguments by **name** instead of position. This lets you skip optional parameters and makes calls self-explanatory.

```example php
<?php
function makeButton(string $text, string $color = "blue", bool $rounded = true): string
{
    $shape = $rounded ? "rounded" : "square";
    return "<button class=\"$color $shape\">$text</button>";
}

echo makeButton("Pay now") . "\n";
echo makeButton("Cancel", rounded: false) . "\n";
```

```output
<button class="blue rounded">Pay now</button>
<button class="blue square">Cancel</button>
```

## Variable number of arguments

Put `...` before the last parameter to collect any number of arguments into an array.

```example php
<?php
function total(int ...$amounts): int
{
    return array_sum($amounts);
}

echo total(100, 250, 75);
```

```output
425
```

## Passing by reference

Normally a function gets a **copy** of each argument, so changing it inside does not affect the original. Put `&` before a parameter to pass the variable itself.

```example php
<?php
function addBonus(int &$points): void
{
    $points += 50;
}

$myPoints = 100;
addBonus($myPoints);
echo $myPoints;
```

```output
150
```

## Anonymous and arrow functions

A function can also be stored in a variable. **Arrow functions** (`fn`) are a short form for one-line functions, and they can use variables from the surrounding code automatically. You will use them a lot with [Array Functions](array-functions).

```example php
<?php
$rate = 130;
$toUsd = fn($kes) => round($kes / $rate, 2);

$square = function ($n) {
    return $n * $n;
};

echo $toUsd(6500) . "\n";
echo $square(9) . "\n";
```

```output
50
81
```

> **Note:** Remember from [Variables and Scope](variables) that ordinary functions cannot see outside variables. Arrow functions are the exception: they capture variables like `$rate` by value automatically. Normal anonymous functions need `use ($rate)` to do the same.

```exercise
Q: Complete the function so it sends back the result.
function square(int $n): int {
    ___ $n * $n;
}
A: return
```

```exercise
Q: Give $greeting a default value of "Hello".
function say(string $name, string $greeting ___ "Hello") {
    return "$greeting, $name";
}
A: =
```

```exercise
Q: Declare that the function returns a string.
function fullName(string $first, string $last): ___ {
    return "$first $last";
}
A: string
```
