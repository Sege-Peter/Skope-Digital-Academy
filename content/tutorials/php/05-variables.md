---
title: PHP Variables and Scope
module: 2
module_title: PHP Basics
minutes: 14
---

A **variable** is a named container that holds a value, such as a student's name, a price or a list of marks. In PHP, every variable name starts with a dollar sign `$`.

## Creating variables

You create a variable simply by assigning a value to it with `=`. There is no separate "declare" step and no need to say what type of value it holds.

```example php
<?php
$name = "Wanjiru";
$age = 19;
$fees = 45500.50;
$isEnrolled = true;

echo "$name is $age years old.\n";
echo "Fees: KSh $fees\n";
echo "Enrolled: $isEnrolled\n";
```

```output
Wanjiru is 19 years old.
Fees: KSh 45500.5
Enrolled: 1
```

Two things to notice: PHP drops the unnecessary trailing zero in `45500.50`, and `true` prints as `1` when turned into text. You will see why in [Data Types](data-types).

## Naming rules

- A variable starts with `$` followed by a letter or underscore.
- After that it may contain letters, numbers and underscores (`A-z`, `0-9`, `_`).
- It cannot start with a number: `$2ndPlace` is invalid, `$secondPlace` is fine.
- Names are **case-sensitive**: `$score` and `$Score` are different variables.

Popular styles are `$camelCase` (`$totalFees`) and `$snake_case` (`$total_fees`). Pick one and use it consistently.

## Changing a variable

A variable can be given a new value at any time, even a value of a different type. PHP is **loosely typed**: the type follows the value.

```example php
<?php
$counter = 5;
echo $counter . "\n";

$counter = $counter + 10;
echo $counter . "\n";

$counter = "five";
echo $counter . "\n";
```

```output
5
15
five
```

> **Tip:** Even though PHP allows it, changing a variable from a number to text usually means you need two different variables. Clear, consistent variables make bugs much rarer.

## Printing variables inside strings

Inside **double quotes**, PHP replaces a variable with its value (this is called *interpolation*). When the variable name touches other letters, wrap it in curly braces so PHP knows where the name ends.

```example php
<?php
$item = "mango";
$count = 12;
echo "I bought $count {$item}s at the market.\n";
echo 'Single quotes: $count {$item}s' . "\n";
```

```output
I bought 12 mangos at the market.
Single quotes: $count {$item}s
```

Without the braces, `"$items"` would look for a variable called `$items`, which does not exist.

## Variable scope

The **scope** of a variable is the part of the script where it can be used. PHP has three main scopes:

1. **Global scope**: variables created outside any function.
2. **Local scope**: variables created inside a function. They exist only while that function runs.
3. **Static variables**: local variables that keep their value between function calls.

This surprises people coming from JavaScript: **a function cannot see global variables automatically.**

```example php
<?php
$school = "Maseno School";   // global

function showSchool()
{
    $classroom = "Form 2B";  // local
    echo "Classroom: $classroom\n";
    echo "School inside function: " . ($school ?? "not visible") . "\n";
}

showSchool();
echo "School outside function: $school\n";
```

```output
Classroom: Form 2B
School inside function: not visible
School outside function: Maseno School
```

Inside the function, `$school` is a brand-new, empty local variable. Likewise, `$classroom` is gone once the function finishes.

## The global keyword

To use a global variable inside a function, list it after the `global` keyword. PHP also keeps every global in the special array `$GLOBALS`.

```example php
<?php
$visits = 10;

function addVisit()
{
    global $visits;
    $visits++;
}

function addTwoVisits()
{
    $GLOBALS['visits'] += 2;
}

addVisit();
addTwoVisits();
echo "Total visits: $visits";
```

```output
Total visits: 13
```

> **Warning:** Relying on globals makes code hard to follow and test, because any function might change the value. The better habit is to pass values in as **parameters** and send results back with `return`. You will practise this in [Functions](functions).

## The static keyword

Normally a local variable is wiped clean each time the function ends. Mark it `static` and PHP remembers the value for the next call.

```example php
<?php
function nextTicket()
{
    static $number = 0;
    $number++;
    return "Ticket #$number";
}

echo nextTicket() . "\n";
echo nextTicket() . "\n";
echo nextTicket() . "\n";
```

```output
Ticket #1
Ticket #2
Ticket #3
```

Without `static`, every call would print "Ticket #1".

## Checking and removing variables

- `isset($x)` returns `true` if the variable exists and is not `null`.
- `unset($x)` destroys a variable.
- `var_dump($x)` shows both the type and the value, which is perfect for debugging.

```example php
<?php
$phone = "0712 345 678";
var_dump(isset($phone));
unset($phone);
var_dump(isset($phone));
```

```output
bool(true)
bool(false)
```

> **Note:** Reading a variable that was never set gives a "Warning: Undefined variable" and the value `null`. Use `isset()` or the `??` operator to check first.

```exercise
Q: Create a variable called $city holding the text Nakuru.
___ = "Nakuru";
A: $city
```

```exercise
Q: Make the function able to change the global $total.
function add() {
    ___ $total;
    $total++;
}
A: global
```

```exercise
Q: Make $count keep its value between calls.
function hit() {
    ___ $count = 0;
    return ++$count;
}
A: static
```
