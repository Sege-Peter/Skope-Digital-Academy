---
title: Functions and Modules
module: 3
module_title: Week 3 – Functions, Games and Files
minutes: 20
---

Look back at the grades analyser: the block of `if`/`elif` that turns a score into a grade is useful on its own. What if you need it in three different programs? Copying it three times means fixing bugs three times. The answer is a **function** – a named, reusable piece of code.

## Defining a function

Use `def`, a name, brackets for **parameters** and a colon. The indented lines are the function body. `return` sends a result back.

```example python
def grade_for(score):
    if score >= 80:
        return "A"
    elif score >= 65:
        return "B"
    elif score >= 50:
        return "C"
    return "D"

print(grade_for(91))
print(grade_for(58))
print(grade_for(23))
```

```output
A
C
D
```

The code inside a function does nothing until you **call** it with `grade_for(...)`. As soon as a `return` runs, the function stops.

## Several parameters and default values

A parameter can have a **default value**, used when the caller leaves it out. Here is the tip calculator from week 1, turned into a function.

```example python
def bill_total(amount, tip_rate=0.10, vat_rate=0.16):
    """Return the bill total with tip and VAT (example VAT rate)."""
    return round(amount * (1 + tip_rate + vat_rate), 2)

print(bill_total(1000))
print(bill_total(1000, tip_rate=0.05))
print(bill_total(2500, 0, 0.16))
```

```output
1260.0
1210.0
2900.0
```

The text in triple quotes is a **docstring** – a short description of what the function does. Good freelancers write one for every function so clients can understand their code.

> **Tip:** A good function does **one** job and has a name that says what it does: `bill_total`, `grade_for`, `format_kes`.

## print versus return

A beginner mistake is using `print()` inside a function when you meant `return`. `print` only shows text; `return` hands back a value the rest of your program can use.

```example python
def format_kes(amount):
    return f"KES {amount:,.2f}"

prices = [450, 1200, 35000]
for p in prices:
    print(format_kes(p))

message = "Your balance is " + format_kes(5250.5)
print(message)
```

```output
KES 450.00
KES 1,200.00
KES 35,000.00
Your balance is KES 5,250.50
```

## Modules: code other people wrote

A **module** is a file of ready-made functions. Python's **standard library** comes with hundreds of them. You load one with `import`.

| Module | Useful for |
|---|---|
| `math` | square roots, rounding up/down |
| `random` | random numbers and choices (games, samples) |
| `datetime` | dates and times |
| `statistics` | mean, median, mode |
| `csv` | reading and writing spreadsheet files |

```example python
import math
import statistics
from datetime import date

sales = [1200, 950, 1800, 1200, 2100]

print(math.ceil(7 / 3))
print(statistics.mean(sales))
print(statistics.median(sales))

d = date(2026, 12, 12)
print(d.strftime("%d %B %Y"))
```

```output
3
1450
1200
12 December 2026
```

`math.ceil` always rounds **up** – useful for "how many matatus do we need for 40 people if each carries 14?". `from datetime import date` imports just one thing, so you can write `date(...)` instead of `datetime.date(...)`.

> **Note:** You can also make your own module: save functions in a file such as `money.py`, then `import money` in another file in the same folder. Big projects are split this way.

## Check yourself

```exercise
Q: Complete the function so it gives back the area of a plot.
___ area(length, width):
    ___ length * width
A: def | return
```

```exercise
Q: Load the statistics module and find the median.
___ statistics
print(statistics.___([3, 9, 5]))
A: import | median
```

## Practice task

Write three functions you could reuse in a small business: `add_vat(amount, rate)`, `discount(amount, percent)` and `format_kes(amount)`. Call each one with at least two different values and print the results. Then use `statistics.mean` to find the average of five prices you know (e.g. bundles, fares or snacks).
