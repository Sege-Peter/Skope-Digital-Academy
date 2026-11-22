---
title: Python Variables
module: 2
module_title: Variables and Data Types
minutes: 10
---

A **variable** is a name that points to a value. Think of it as a label you stick on a box so you can find what is inside later. Variables let your program remember things: a customer's name, the price of a bag of maize, the number of tickets sold.

## Creating variables

Python has no special command to declare a variable. You create one the moment you assign a value to it with `=`.

```example python
name = "Wanjiru"
age = 24
town = "Nakuru"
print(name)
print(age)
print(town)
```

```output
Wanjiru
24
Nakuru
```

The `=` sign here does not mean "equals" as in maths. It means "store the value on the right in the name on the left".

## Changing a variable

A variable can be given a new value at any time. It can even switch to a different type of value.

```example python
balance = 500
print(balance)
balance = balance + 250
print(balance)
balance = "closed"
print(balance)
```

```output
500
750
closed
```

The line `balance = balance + 250` reads the current value (500), adds 250 and stores the result back in `balance`.

> **Note:** Python is *dynamically typed*: you never say "this variable holds a number". The type belongs to the value, not to the name. See [Python Data Types](data-types) for how to check a value's type.

## Naming rules

Variable names must follow these rules:

- They may contain letters, digits and underscores (`_`).
- They must **not** start with a digit.
- They cannot contain spaces or symbols such as `-`, `@` or `$`.
- They are case-sensitive: `price` and `Price` are different.
- They cannot be a Python keyword such as `if`, `for`, `class` or `True`.

| Name | Valid? | Why |
|---|---|---|
| `customer_name` | Yes | letters and underscore |
| `total2` | Yes | digit is not first |
| `_count` | Yes | may start with underscore |
| `2total` | No | starts with a digit |
| `first-name` | No | hyphen is not allowed |
| `class` | No | reserved keyword |

### Naming style

Python programmers use **snake_case** for variables: all lowercase, words joined with underscores, like `monthly_rent` or `phone_number`. Choose names that describe the value. `rent` beats `r`, and `rent_kes` is even clearer.

## Assigning several variables at once

You can assign many variables in one line by separating names and values with commas.

```example python
county, code, region = "Kisumu", 42, "Nyanza"
print(county)
print(code)
print(region)
```

```output
Kisumu
42
Nyanza
```

You can also give several variables the same value:

```example python
x = y = z = 0
print(x, y, z)
```

```output
0 0 0
```

### Swapping values

A neat Python trick is swapping two variables without a temporary helper.

```example python
morning = "tea"
evening = "uji"
morning, evening = evening, morning
print(morning, evening)
```

```output
uji tea
```

## Printing variables with text

The easiest way to mix text and variables in `print()` is to separate them with commas. Python adds a space between each item.

```example python
item = "sukuma wiki"
price = 30
print("One bunch of", item, "costs", price, "shillings.")
```

```output
One bunch of sukuma wiki costs 30 shillings.
```

You can also join strings with `+`, but only if every part is already text. Trying `"Age: " + 24` causes a `TypeError`. A cleaner way is the f-string, which you will meet in [Python String Methods](string-methods):

```example python
name = "Otieno"
age = 31
print(f"{name} is {age} years old.")
```

```output
Otieno is 31 years old.
```

## Constants

Python has no true constants, but by convention a name written in ALL CAPS means "please do not change this value".

```python
VAT_RATE = 0.16
```

> **Tip:** Using a named constant like `VAT_RATE` instead of typing `0.16` everywhere makes your code easier to update when the rate changes.

## Exercises

```exercise
Q: Create a variable called county with the value "Mombasa".
___ = "Mombasa"
A: county
```

```exercise
Q: Assign the values to both variables in one line.
first, second ___ 10, 20
A: =
```

Continue to [Python Data Types](data-types).
