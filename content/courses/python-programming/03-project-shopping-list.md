---
title: "Project: Shopping List Manager"
module: 2
module_title: Week 2 – Lists, Loops and Decisions
minutes: 20
---

Fatuma does the weekly shopping for her family in Mombasa. This week we build her a program that keeps a shopping list, adds and removes items, and prints a tidy list with prices. To do that you need two new tools: **lists** and **loops**.

## Lists hold many values

A **list** is written with square brackets, items separated by commas. Each item has a position called an **index**, starting at **0**.

```example python
shopping = ["sugar", "unga", "milk", "tomatoes"]

print(shopping)
print(shopping[0])
print(shopping[-1])
print(len(shopping))
```

```output
['sugar', 'unga', 'milk', 'tomatoes']
sugar
tomatoes
4
```

`shopping[-1]` means "the last item", and `len()` counts the items.

## Changing a list

Lists can grow and shrink while the program runs:

| Code | What it does |
|---|---|
| `items.append("rice")` | add to the end |
| `items.remove("milk")` | remove the first matching item |
| `items.insert(0, "salt")` | add at a position |
| `items.sort()` | sort A–Z (or smallest to largest) |
| `"rice" in items` | `True` if the item is in the list |

```example python
shopping = ["sugar", "unga", "milk"]

shopping.append("rice")
shopping.remove("milk")
shopping.insert(0, "salt")
print(shopping)

shopping.sort()
print(shopping)
print("milk" in shopping)
```

```output
['salt', 'sugar', 'unga', 'rice']
['rice', 'salt', 'sugar', 'unga']
False
```

## Loops: do something for every item

A `for` loop repeats a block of code once for each item. The indented lines belong to the loop.

```example python
shopping = ["sugar", "unga", "rice"]

for item in shopping:
    print("- " + item)

print("Done!")
```

```output
- sugar
- unga
- rice
Done!
```

> **Warning:** Indentation matters in Python. Use 4 spaces for lines inside a loop. If the indentation is wrong you get an `IndentationError`.

`enumerate()` gives you a counter as well, which is handy for numbered lists: `for number, item in enumerate(shopping, start=1):`.

## The shopping list manager

Now the full project. Each item is a small list of `[name, quantity, price]`, and the whole shopping list is a list of those. We add an item, remove one that Fatuma already has at home, then print a numbered list with a running total.

```example python
shopping = [
    ["Unga (2kg)", 2, 210],
    ["Sugar (1kg)", 1, 180],
    ["Milk (500ml)", 4, 65],
    ["Cooking oil (1L)", 1, 390],
]

# Add an item
shopping.append(["Tomatoes (kg)", 2, 120])

# Remove sugar - there is some at home
for row in shopping:
    if row[0] == "Sugar (1kg)":
        shopping.remove(row)
        break

total = 0
print("FATUMA'S SHOPPING LIST")
for number, row in enumerate(shopping, start=1):
    name, qty, price = row
    cost = qty * price
    total = total + cost
    print(f"{number}. {name:<18} x{qty}  KES {cost:>5,}")

print(f"Items: {len(shopping)}   Total: KES {total:,}")
```

```output
FATUMA'S SHOPPING LIST
1. Unga (2kg)         x2  KES   420
2. Milk (500ml)       x4  KES   260
3. Cooking oil (1L)   x1  KES   390
4. Tomatoes (kg)      x2  KES   240
Items: 4   Total: KES 1,310
```

A few things to notice:

- `name, qty, price = row` **unpacks** a 3-item list into three variables in one line.
- `total = total + cost` is a **running total** – it grows each time round the loop.
- `:<18` left-aligns the name in 18 spaces so the columns line up.
- `if row[0] == "Sugar (1kg)":` only runs the indented lines when the name matches – you will learn `if` properly in the [grades analyser](project-grades-analyser).
- `break` stops the loop early once sugar is found.

> **Note:** The prices here are examples. Real prices change from shop to shop and week to week.

## Check yourself

```exercise
Q: Add "bread" to the end of the list.
items = ["eggs", "milk"]
items.___("bread")
A: append
H: The method name means "attach at the end".
```

```exercise
Q: Print every name in the list.
names = ["Achieng", "Otieno", "Wanjiru"]
___ name ___ names:
    print(name)
A: for | in
```

## Practice task

Write the shopping list for your own household this week with at least five items and the prices you expect to pay. Run the program, then change it so it also prints which item costs the most. (Hint: keep a variable `biggest` and compare each `cost` to it inside the loop.)
