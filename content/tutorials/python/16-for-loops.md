---
title: Python For Loops and range()
module: 4
minutes: 11
---

A `for` loop walks through a sequence – a list, a string, a tuple, a dictionary, a range of numbers – and runs its block once for each item. It is the loop you will use most in Python.

## Looping over a list

```example python
counties = ["Kilifi", "Kwale", "Lamu", "Mombasa"]
for county in counties:
    print(county, "is on the coast.")
```

```output
Kilifi is on the coast.
Kwale is on the coast.
Lamu is on the coast.
Mombasa is on the coast.
```

On each pass, the loop variable `county` takes the next item. You choose its name; pick one that reads well.

Unlike `while`, there is no counter to update and no risk of forgetting it. The loop ends by itself when the items run out.

## Looping over a string

```example python
for ch in "Juja":
    print(ch.upper())
```

```output
J
U
J
A
```

## The range() function

`range()` produces a sequence of whole numbers. It is how you repeat something a fixed number of times.

| Form | Numbers produced |
|---|---|
| `range(5)` | 0, 1, 2, 3, 4 |
| `range(2, 6)` | 2, 3, 4, 5 |
| `range(0, 20, 5)` | 0, 5, 10, 15 |
| `range(5, 0, -1)` | 5, 4, 3, 2, 1 |

The end number is never included, just like in slicing.

```example python
for i in range(3):
    print("Round", i)

for year in range(2020, 2025, 2):
    print(year)
```

```output
Round 0
Round 1
Round 2
2020
2022
2024
```

## A multiplication table

```example python
n = 7
for i in range(1, 11):
    print(f"{n} x {i:>2} = {n * i:>2}")
```

```output
7 x  1 =  7
7 x  2 = 14
7 x  3 = 21
7 x  4 = 28
7 x  5 = 35
7 x  6 = 42
7 x  7 = 49
7 x  8 = 56
7 x  9 = 63
7 x 10 = 70
```

## Accumulating a total

A very common pattern is to start a variable at zero and add to it inside the loop.

```example python
daily_sales = [4200, 3800, 5100, 2950, 6100]
total = 0
for amount in daily_sales:
    total += amount
print("Week total: KES", total)
```

```output
Week total: KES 22150
```

(For this exact job, `sum(daily_sales)` is shorter. But the pattern works for any calculation, not just adding.)

## enumerate() and zip()

`enumerate()` gives you a counter alongside each item. `zip()` walks through two or more lists side by side.

```example python
runners = ["Kiptoo", "Jepkosgei", "Wekesa"]
times = ["31:05", "31:40", "32:12"]
for position, (runner, time) in enumerate(zip(runners, times), start=1):
    print(position, runner, time)
```

```output
1 Kiptoo 31:05
2 Jepkosgei 31:40
3 Wekesa 32:12
```

Here the results of a school 10 km race are stored in two separate lists, and `zip()` pairs them up.

## Looping over a dictionary

```example python
scores = {"Amani": 82, "Baraka": 74, "Neema": 91}
for name, score in scores.items():
    print(f"{name}: {score}")
```

```output
Amani: 82
Baraka: 74
Neema: 91
```

See [Python Dictionaries](dictionaries) for `keys()`, `values()` and `items()`.

## break, continue and else

These work in `for` loops exactly as in [while loops](while-loops).

```example python
phone_numbers = ["0711000111", "0722abc333", "0733444555"]
for number in phone_numbers:
    if not number.isdigit():
        print("Invalid number found:", number)
        break
    print("OK:", number)
else:
    print("All numbers valid.")
```

```output
OK: 0711000111
Invalid number found: 0722abc333
```

The `else` block did not run, because the loop ended with `break`.

```example python
for n in range(1, 8):
    if n % 3 == 0:
        continue
    print(n, end=" ")
print()
```

```output
1 2 4 5 7 
```

The `end=" "` argument tells `print()` to finish with a space instead of a new line, so the numbers appear on one line.

## Nested loops

A loop inside a loop runs the inner loop completely for each pass of the outer one.

```example python
sizes = ["Small", "Large"]
flavours = ["Mango", "Passion"]
for size in sizes:
    for flavour in flavours:
        print(size, flavour, "juice")
```

```output
Small Mango juice
Small Passion juice
Large Mango juice
Large Passion juice
```

> **Tip:** Do not change a list (adding or removing items) while looping over it. Loop over a copy instead – `for x in items.copy():` – or build a new list.

## Exercises

```exercise
Q: Print the numbers 0 to 4 using range.
for i in ___(5):
    print(i)
A: range
```

```exercise
Q: Complete the loop so it visits each fruit in the list.
for fruit ___ fruits:
    print(fruit)
A: in
```

Next, package code into reusable blocks with [Python Functions](functions).
