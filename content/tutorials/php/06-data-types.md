---
title: PHP Data Types
module: 2
minutes: 12
---

Every value in PHP has a **type**. The type decides what you can do with the value: you can multiply numbers, join strings and loop over arrays. PHP works out the type for you from the value you assign.

## The main data types

| Type | Example | What it holds |
|---|---|---|
| `string` | `"Mombasa"` | text |
| `int` | `42`, `-7` | whole numbers |
| `float` | `3.14`, `0.5` | numbers with a decimal point (also called double) |
| `bool` | `true`, `false` | yes/no values |
| `array` | `[1, 2, 3]` | an ordered list or map of values |
| `object` | `new Student()` | an instance of a class |
| `null` | `null` | "no value" |

There are also `callable`, `iterable` and `resource` types, which you will meet much later.

## Inspecting types with var_dump()

`echo` shows only the value. `var_dump()` shows the **type and the value**, and for strings the length too. It is the best friend of every PHP developer while debugging.

```example php
<?php
var_dump("Mombasa");
var_dump(42);
var_dump(3.5);
var_dump(true);
var_dump(null);
```

```output
string(7) "Mombasa"
int(42)
float(3.5)
bool(true)
NULL
```

If you just want the type name as text, use `gettype()` or the more modern `get_debug_type()`:

```example php
<?php
echo get_debug_type("hello") . "\n";
echo get_debug_type(10) . "\n";
echo get_debug_type(2.5) . "\n";
echo get_debug_type(false) . "\n";
echo get_debug_type([1, 2]) . "\n";
echo get_debug_type(null) . "\n";
```

```output
string
int
float
bool
array
null
```

## Strings

A string is a sequence of characters inside quotes. See [Strings](strings) for the many things you can do with them.

## Integers

An integer is a whole number without a decimal point. It may be positive or negative. On 64-bit systems the largest integer is `PHP_INT_MAX`, which is 9223372036854775807. You can also write integers in hexadecimal (`0x1A`), octal (`0o17`) or binary (`0b1010`), and use underscores for readability.

```example php
<?php
$population = 1_155_574;   // underscores are ignored
var_dump($population);
var_dump(0b1010);
var_dump(PHP_INT_MAX);
```

```output
int(1155574)
int(10)
int(9223372036854775807)
```

## Floats

A float is a number with a decimal point or in exponent form. Floats can store very large or very small values, but they are **approximations**.

```example php
<?php
var_dump(1.5e3);
var_dump(0.1 + 0.2);
var_dump(0.1 + 0.2 == 0.3);
```

```output
float(1500)
float(0.30000000000000004)
bool(false)
```

> **Warning:** Because of tiny rounding errors, never use floats to store money where accuracy matters. Store amounts as whole cents (or whole shillings) in an `int`, or round results with `round($value, 2)` before comparing.

## Booleans

A boolean is either `true` or `false`. They are mostly the result of comparisons and are used in [if statements](conditions).

```example php
<?php
$marks = 64;
$passed = $marks >= 50;
var_dump($passed);
```

```output
bool(true)
```

## Arrays and objects

An **array** stores many values in one variable. An **object** bundles data and functions together. Both get full lessons later ([Arrays](arrays) and [OOP Basics](oop-basics)); here is a quick look:

```example php
<?php
$counties = ["Kisumu", "Siaya", "Homa Bay"];
var_dump($counties);
```

```output
array(3) {
  [0]=>
  string(6) "Kisumu"
  [1]=>
  string(5) "Siaya"
  [2]=>
  string(8) "Homa Bay"
}
```

## NULL

`null` is a special value meaning "nothing here". A variable is `null` if you assign `null` to it, or if it has been `unset()`.

## Type juggling and casting

PHP converts types automatically when needed. This is called **type juggling**. For example, when you add a numeric string to a number, PHP turns the string into a number.

You can also convert on purpose with a **cast**: write the target type in brackets before the value.

```example php
<?php
var_dump("10" + 5);          // numeric string becomes int
var_dump("2.5" * 2);         // becomes float
var_dump((int) "42 students");
var_dump((string) 3.0);
var_dump((bool) "0");
var_dump((bool) "hello");
var_dump((int) 9.99);
```

```output
int(15)
float(5)
int(42)
string(1) "3"
bool(false)
bool(true)
int(9)
```

Notice that casting a float to int **cuts off** the decimals; it does not round. And the string `"0"` is one of the values that counts as `false`.

## What counts as false?

When PHP converts a value to a boolean, these values become `false`: `false`, `0`, `0.0`, `""` (empty string), `"0"`, `[]` (empty array) and `null`. Everything else is `true`, including `"false"`, `" "` and `-1`.

> **Tip:** When comparing values, prefer the strict operator `===`, which checks the type as well as the value. `0 == "a"` is `false` in PHP 8 but `"1" == "01"` is still `true`. With `===`, there are no surprises. See [Operators](operators).

```exercise
Q: Fill in the function that shows both the type and the value of $x.
___($x);
A: var_dump
```

```exercise
Q: Cast the string to an integer.
$n = (___) "25";
A: int / integer
```
