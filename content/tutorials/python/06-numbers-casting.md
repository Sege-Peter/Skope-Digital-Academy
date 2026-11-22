---
title: Python Numbers and Casting
module: 2
minutes: 11
---

Numbers are at the heart of most programs: prices, distances, scores, interest rates. Python has three numeric types and a simple way to convert between types, called **casting**.

## The three number types

- **int** – whole numbers, positive or negative, with no decimal point: `7`, `-40`, `2500000`.
- **float** – numbers with a decimal point: `3.5`, `-0.25`, `98.6`.
- **complex** – numbers with an imaginary part, written with `j`: `2+3j`. These are mostly used in engineering and science.

```example python
students = 45
temperature = 23.7
signal = 3 + 4j
print(type(students).__name__)
print(type(temperature).__name__)
print(type(signal).__name__)
```

```output
int
float
complex
```

### Big numbers

Python integers can be as large as you like. To make big numbers easier to read, you may put underscores between digits. Python ignores them.

```example python
kenya_population = 52_400_000
budget = 3_900_000_000_000
print(kenya_population)
print(budget * 2)
```

```output
52400000
7800000000000
```

## Arithmetic operators

| Operator | Meaning | Example | Result |
|---|---|---|---|
| `+` | addition | `7 + 2` | `9` |
| `-` | subtraction | `7 - 2` | `5` |
| `*` | multiplication | `7 * 2` | `14` |
| `/` | division | `7 / 2` | `3.5` |
| `//` | floor division | `7 // 2` | `3` |
| `%` | remainder (modulus) | `7 % 2` | `1` |
| `**` | power | `7 ** 2` | `49` |

```example python
mandazi = 50
per_box = 12
print(mandazi // per_box, "full boxes")
print(mandazi % per_box, "left over")
print(2 ** 10)
print(10 / 4)
```

```output
4 full boxes
2 left over
1024
2.5
```

> **Note:** The `/` operator always gives a float, even when the answer is whole: `10 / 2` is `5.0`, not `5`. Use `//` when you want a whole number.

## Floats and rounding

Computers store floats in binary, so some decimals cannot be stored exactly. This leads to small surprises:

```example python
print(0.1 + 0.2)
print(round(0.1 + 0.2, 2))
print(round(1234.5678, 1))
```

```output
0.30000000000000004
0.3
1234.6
```

Use `round(number, digits)` when showing results to people. For serious money calculations, professionals often store amounts in cents (as integers) or use the `decimal` module.

## Useful number functions

```example python
print(abs(-350))
print(max(120, 450, 80))
print(min(120, 450, 80))
print(pow(3, 4))
print(divmod(17, 5))
```

```output
350
450
80
81
(3, 2)
```

`divmod(a, b)` returns both the floor division and the remainder in one go. For more maths, such as square roots, see the `math` module in [Python Modules](modules).

## Casting: converting between types

Casting means turning a value of one type into another type. You do it by calling the type name like a function.

### int()

```example python
print(int(9.99))
print(int("250"))
print(int(-3.7))
```

```output
9
250
-3
```

`int()` on a float simply chops off the decimal part; it does **not** round.

### float()

```example python
print(float(5))
print(float("12.5"))
print(float("3"))
```

```output
5.0
12.5
3.0
```

### str()

```example python
amount = 1500
message = "You paid KES " + str(amount)
print(message)
```

```output
You paid KES 1500
```

Without `str()`, joining text and a number with `+` would raise a `TypeError`.

> **Warning:** Casting fails if the text is not a valid number. `int("12 bob")` and `int("3.5")` both raise a `ValueError`. You will learn to catch such errors in [Python Try...Except](exceptions).

## A practical example

Here we calculate a phone bill with 16% VAT, starting from values that arrived as text:

```example python
minutes_text = "135"
rate_text = "4.3"
minutes = int(minutes_text)
rate = float(rate_text)
subtotal = minutes * rate
vat = subtotal * 0.16
total = subtotal + vat
print("Subtotal:", round(subtotal, 2))
print("VAT:", round(vat, 2))
print("Total:", round(total, 2))
```

```output
Subtotal: 580.5
VAT: 92.88
Total: 673.38
```

## Exercises

```exercise
Q: Use the operator that gives the remainder of 17 divided by 5.
print(17 ___ 5)
A: %
```

```exercise
Q: Convert the text "42" into an integer.
age = ___("42")
A: int
```

Next: [Python Strings](strings).
