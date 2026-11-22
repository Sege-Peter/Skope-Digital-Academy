---
title: Python Modules (math, random, datetime)
module: 6
module_title: Going Further
minutes: 13
---

A **module** is a file of Python code – functions, classes and variables – that you can load into your own program. Python ships with a large **standard library** of modules, often described as "batteries included". This lesson shows how to import modules and tours three you will use constantly: `math`, `random` and `datetime`.

## Importing a module

Use `import` followed by the module name. Then reach its contents with a dot.

```example python
import math

print(math.sqrt(144))
print(math.pi)
```

```output
12.0
3.141592653589793
```

### Other ways to import

```example python
from math import sqrt, floor     # import specific names
import datetime as dt            # give the module a short alias

print(sqrt(81))
print(floor(7.9))
print(dt.date(2024, 12, 12))
```

```output
9.0
7
2024-12-12
```

`from module import name` lets you use the name without the module prefix. An alias (`as dt`) saves typing for long module names.

> **Warning:** Avoid `from math import *`. It dumps every name from the module into your program, which can silently overwrite your own variables and makes it hard to tell where a name came from.

## The math module

| Function / constant | Meaning |
|---|---|
| `math.sqrt(x)` | square root |
| `math.ceil(x)` | round up to a whole number |
| `math.floor(x)` | round down to a whole number |
| `math.pow(x, y)` | x to the power y (as a float) |
| `math.pi`, `math.e` | the constants π and e |
| `math.factorial(n)` | n! |
| `math.gcd(a, b)` | greatest common divisor |

```example python
import math

guests = 47
per_table = 8
print("Tables needed:", math.ceil(guests / per_table))

radius = 3.5
area = math.pi * radius ** 2
print(f"Area of a round tank base: {area:.2f} m2")

print(math.factorial(5))
print(math.gcd(24, 36))
```

```output
Tables needed: 6
Area of a round tank base: 38.48 m2
120
12
```

`math.ceil()` is handy for "how many containers do I need?" questions, where a partial table still needs a whole table.

## The random module

The `random` module generates pseudo-random numbers: useful for games, simulations, sampling and shuffling.

| Function | What it does |
|---|---|
| `random.random()` | float from 0.0 up to (not including) 1.0 |
| `random.randint(a, b)` | whole number from a to b, both included |
| `random.choice(seq)` | one random item from a sequence |
| `random.sample(seq, k)` | k different random items |
| `random.shuffle(lst)` | shuffles a list in place |
| `random.seed(n)` | fixes the starting point so results repeat |

Random output changes every run. Try this a few times in the editor:

```example python
import random

print(random.randint(1, 6))
print(random.choice(["Kisumu", "Nairobi", "Mombasa", "Eldoret"]))
```

Your numbers will be different each time, so there is no fixed output for this example.

### Repeatable randomness with seed()

When you call `random.seed()` with a fixed number, the "random" sequence becomes the same on every run. This is essential for testing, and it lets us show you the exact output.

```example python
import random

random.seed(42)
print(random.randint(1, 100))
print(random.choice(["red", "green", "blue"]))

students = ["Akoth", "Baraka", "Chelimo", "Dida", "Esiankiki"]
random.shuffle(students)
print(students)
print(random.sample(range(1, 50), 3))
```

```output
82
red
['Baraka', 'Dida', 'Esiankiki', 'Chelimo', 'Akoth']
[9, 48, 7]
```

> **Note:** Do not use `random` for passwords or security tokens. Use the `secrets` module, which is designed for that.

## The datetime module

`datetime` handles dates, times and the differences between them.

### Creating dates and times

```example python
from datetime import date, datetime

independence = date(1963, 12, 12)
print(independence)
print(independence.year)
print(independence.strftime("%A, %d %B %Y"))

meeting = datetime(2025, 3, 14, 9, 30)
print(meeting)
print(meeting.strftime("%d/%m/%Y at %H:%M"))
```

```output
1963-12-12
1963
Thursday, 12 December 1963
2025-03-14 09:30:00
14/03/2025 at 09:30
```

`strftime()` ("string format time") turns a date into text using codes:

| Code | Meaning | Example |
|---|---|---|
| `%Y` | 4-digit year | 2025 |
| `%m` | month number | 03 |
| `%d` | day of month | 14 |
| `%B` | month name | March |
| `%A` | weekday name | Friday |
| `%H:%M` | 24-hour time | 09:30 |

### Date arithmetic with timedelta

Subtracting two dates gives a `timedelta`. Adding a `timedelta` to a date moves it forward.

```example python
from datetime import date, timedelta

start = date(2025, 1, 6)
end = date(2025, 4, 4)
term = end - start
print("Term length:", term.days, "days")

due = start + timedelta(days=30)
print("Fees due:", due)
```

```output
Term length: 88 days
Fees due: 2025-02-05
```

### Today's date

`date.today()` and `datetime.now()` give the current date and time. The output depends on when you run it:

```example python
from datetime import date, datetime

print(date.today())
print(datetime.now().strftime("%H:%M"))
```

## Exploring a module

`dir(module)` lists everything inside a module, and `help(name)` shows its documentation.

```example python
import math
names = [n for n in dir(math) if not n.startswith("_")]
print(names[:6])
```

```output
['acos', 'acosh', 'asin', 'asinh', 'atan', 'atan2']
```

> **Tip:** Your own `.py` files are modules too. If you save functions in `helpers.py`, another file in the same folder can use `import helpers`.

## Exercises

```exercise
Q: Import the math module.
___ math
A: import
```

```exercise
Q: Print a random whole number between 1 and 10.
import random
print(random.___(1, 10))
A: randint
```

Next: handle errors gracefully with [Python Try...Except](exceptions).
