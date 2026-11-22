---
title: Python Lambda and List Comprehensions
module: 5
minutes: 11
---

Python offers some compact tools for small jobs that would otherwise need several lines. A **lambda** is a tiny function written in one expression. A **comprehension** builds a new list (or set or dictionary) from an existing one in a single, readable line.

## Lambda functions

A lambda is written as `lambda parameters: expression`. It returns the value of the expression automatically; there is no `return` keyword.

```example python
square = lambda x: x * x
add = lambda a, b: a + b
print(square(9))
print(add(150, 250))
```

```output
81
400
```

That is equivalent to:

```python
def square(x):
    return x * x
```

> **Note:** Assigning a lambda to a name, as above, is fine for learning, but in real code a normal `def` is clearer. Lambdas really shine when passed directly into another function.

### Lambdas as sort keys

The most common use of a lambda is the `key` argument of `sorted()`, `sort()`, `min()` and `max()`.

```example python
products = [("Unga", 210), ("Sugar", 160), ("Cooking oil", 395), ("Salt", 40)]

by_price = sorted(products, key=lambda p: p[1])
print(by_price)

most_expensive = max(products, key=lambda p: p[1])
print(most_expensive[0])
```

```output
[('Salt', 40), ('Sugar', 160), ('Unga', 210), ('Cooking oil', 395)]
Cooking oil
```

The lambda tells Python: "for each product `p`, compare using `p[1]`", which is the price.

### map() and filter()

`map(function, items)` applies a function to every item. `filter(function, items)` keeps only the items for which the function returns `True`. Both return lazy objects, so wrap them in `list()` to see the result.

```example python
prices_usd = [10, 25, 40]
prices_kes = list(map(lambda p: p * 130, prices_usd))
print(prices_kes)

ages = [12, 19, 25, 15, 31]
adults = list(filter(lambda a: a >= 18, ages))
print(adults)
```

```output
[1300, 3250, 5200]
[19, 25, 31]
```

> **Note:** The exchange rate of 130 is just a round number for the example, not a live rate.

## List comprehensions

A **list comprehension** creates a list from another sequence in one line:

```python
new_list = [expression for item in sequence]
```

Compare a normal loop with the comprehension that does the same thing:

```example python
numbers = [1, 2, 3, 4, 5]

squares = []
for n in numbers:
    squares.append(n * n)
print(squares)

squares = [n * n for n in numbers]
print(squares)
```

```output
[1, 4, 9, 16, 25]
[1, 4, 9, 16, 25]
```

Read it left to right: "make a list of `n * n`, for each `n` in `numbers`."

### Adding a condition

Put an `if` at the end to keep only some items.

```example python
marks = [45, 78, 62, 30, 91, 55]
passed = [m for m in marks if m >= 50]
print(passed)

names = ["kamau", "ATIENO", "Mwende"]
clean = [name.title() for name in names]
print(clean)
```

```output
[78, 62, 91, 55]
['Kamau', 'Atieno', 'Mwende']
```

### if...else inside the expression

To transform every item in one of two ways, put a conditional expression at the **front**.

```example python
marks = [45, 78, 62, 30]
results = ["Pass" if m >= 50 else "Fail" for m in marks]
print(results)
```

```output
['Fail', 'Pass', 'Pass', 'Fail']
```

Note the difference: `if` at the end **filters**; `if...else` at the front **chooses a value**.

## Dictionary and set comprehensions

The same idea works with curly braces.

```example python
towns = ["Nyeri", "Kisii", "Malindi"]
lengths = {town: len(town) for town in towns}
print(lengths)

words = ["chai", "Chai", "CHAI", "kahawa"]
unique = {w.lower() for w in words}
print(sorted(unique))
```

```output
{'Nyeri': 5, 'Kisii': 5, 'Malindi': 7}
['chai', 'kahawa']
```

## A practical example

Given a list of M-Pesa style transactions, find the total received:

```example python
transactions = [
    {"type": "received", "amount": 1500},
    {"type": "sent", "amount": 700},
    {"type": "received", "amount": 2300},
    {"type": "sent", "amount": 450},
]
received = sum(t["amount"] for t in transactions if t["type"] == "received")
print("Total received: KES", received)
```

```output
Total received: KES 3800
```

Writing the comprehension directly inside `sum()` without square brackets creates a *generator expression*. It works the same way but does not build a temporary list.

> **Tip:** Comprehensions are great when they fit comfortably on one line. If yours needs several conditions or nested loops, a normal `for` loop is usually easier to read.

## Exercises

```exercise
Q: Complete the lambda that triples a number.
triple = ___ x: x * 3
A: lambda
```

```exercise
Q: Build a list of the doubled numbers.
doubled = [n * 2 ___ n in numbers]
A: for
```

Next: [Python Classes and Objects](classes-objects).
