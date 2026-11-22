---
title: Python Booleans and Operators
module: 2
minutes: 11
---

A **Boolean** is a value that is either `True` or `False`. Booleans are how programs make decisions: is the customer over 18? Is the balance enough? Has the deadline passed? In Python, the Boolean type is called `bool`.

## True and False

Note the capital letters. `true` (lowercase) is not a Python value and will cause an error.

```example python
is_member = True
has_paid = False
print(is_member)
print(type(has_paid).__name__)
```

```output
True
bool
```

## Comparison operators

Comparing two values produces a Boolean.

| Operator | Meaning | Example | Result |
|---|---|---|---|
| `==` | equal to | `5 == 5` | `True` |
| `!=` | not equal to | `5 != 3` | `True` |
| `>` | greater than | `5 > 8` | `False` |
| `<` | less than | `5 < 8` | `True` |
| `>=` | greater than or equal | `5 >= 5` | `True` |
| `<=` | less than or equal | `4 <= 3` | `False` |

```example python
balance = 800
fare = 150
print(balance >= fare)
print(balance == 1000)
print("Nairobi" == "nairobi")
```

```output
True
False
False
```

> **Warning:** `=` assigns a value; `==` compares two values. Writing `if x = 5:` is a syntax error. This is one of the most common beginner mistakes.

You can also chain comparisons, just like in maths:

```example python
age = 22
print(18 <= age < 35)
```

```output
True
```

## Logical operators

Logical operators combine Booleans.

| Operator | Result is True when… |
|---|---|
| `and` | both sides are True |
| `or` | at least one side is True |
| `not` | the value is False (it flips it) |

```example python
age = 20
has_id = True
print(age >= 18 and has_id)
print(age < 18 or has_id)
print(not has_id)
```

```output
True
True
False
```

## Truthy and falsy values

The `bool()` function converts any value to a Boolean. Most values count as `True`. Only "empty" or zero-like values count as `False`:

- `False` and `None`
- the number zero: `0`, `0.0`
- empty collections: `""`, `[]`, `()`, `{}`, `set()`

```example python
print(bool("Kisii"))
print(bool(""))
print(bool(0))
print(bool(-5))
print(bool([]))
print(bool([0]))
```

```output
True
False
False
True
False
True
```

This means you can write `if name:` instead of `if name != "":`. You will use this a lot in [Python If...Else](if-else).

## Assignment operators

Assignment operators update a variable using its current value. They are shortcuts.

| Operator | Same as |
|---|---|
| `x += 3` | `x = x + 3` |
| `x -= 3` | `x = x - 3` |
| `x *= 3` | `x = x * 3` |
| `x /= 3` | `x = x / 3` |
| `x //= 3` | `x = x // 3` |
| `x %= 3` | `x = x % 3` |
| `x **= 3` | `x = x ** 3` |

```example python
wallet = 1000
wallet -= 250
wallet += 100
wallet *= 2
print(wallet)
```

```output
1700
```

## Membership and identity operators

`in` and `not in` check whether a value is inside a collection. `is` and `is not` check whether two names point to the **same object** in memory.

```example python
towns = ["Thika", "Machakos", "Embu"]
print("Embu" in towns)
print("Meru" not in towns)

result = None
print(result is None)
```

```output
True
True
True
```

> **Tip:** Use `is` only to compare with `None` (or `True`/`False`). For comparing values such as numbers and strings, always use `==`.

## Operator precedence

When an expression has several operators, Python follows an order, from highest to lowest:

1. `()` brackets
2. `**` power
3. `*`, `/`, `//`, `%`
4. `+`, `-`
5. comparisons: `==`, `!=`, `<`, `>`, `<=`, `>=`, `in`, `is`
6. `not`
7. `and`
8. `or`

```example python
print(2 + 3 * 4)
print((2 + 3) * 4)
print(True or False and False)
```

```output
14
20
True
```

When in doubt, add brackets. They make your intention clear to both Python and the next person reading your code.

## Exercises

```exercise
Q: Complete the comparison that checks if a equals b.
print(a ___ b)
A: ==
```

```exercise
Q: Fill in the logical operator so the result is True only when both are true.
print(is_adult ___ has_ticket)
A: and
```

Next, start working with collections in [Python Lists](lists).
