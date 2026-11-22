---
title: Handling Errors and Messy Data
module: 4
module_title: Week 4 – Final Project
minutes: 15
---

Real data is messy. A customer types "1,500" instead of 1500, a cell in a spreadsheet is empty, someone writes "two hundred". A program that crashes on the first bad value is not much use to a client. This lesson shows how to read error messages and how to handle bad data calmly – an important step before the final project.

## Reading an error message

When Python hits a problem it stops and prints a **traceback**. The most useful part is the **last line**: the error type and a short message.

| Error | Usual cause |
|---|---|
| `SyntaxError` | a typo – missing bracket, quote or colon |
| `IndentationError` | wrong spaces at the start of a line |
| `NameError` | using a variable that does not exist (often a spelling mistake) |
| `TypeError` | mixing types, e.g. `"KES " + 500` |
| `ValueError` | right type, bad value, e.g. `int("abc")` |
| `KeyError` | a dictionary key that is not there |
| `ZeroDivisionError` | dividing by zero |
| `FileNotFoundError` | opening a file that does not exist |

> **Tip:** Don't panic at red text. Read the last line, then look at the line number it mentions. Most errors are one-character fixes.

## try and except

Put risky code in a `try` block. If an error happens, Python jumps to the matching `except` block instead of crashing.

```example python
amounts = ["1500", "250", "two hundred", "800"]

total = 0
for text in amounts:
    try:
        total += int(text)
    except ValueError:
        print(f"Skipped bad value: {text}")

print(f"Total: KES {total:,}")
```

```output
Skipped bad value: two hundred
Total: KES 2,550
```

Only catch the errors you expect (`ValueError` here). A bare `except:` hides real bugs and makes them hard to find.

## Cleaning values before using them

Often you can fix data instead of skipping it. String methods help:

| Method | Example | Result |
|---|---|---|
| `.strip()` | `"  Nyeri "` | `"Nyeri"` |
| `.replace(",", "")` | `"1,500"` | `"1500"` |
| `.lower()` / `.title()` | `"KISUMU"` | `"kisumu"` / `"Kisumu"` |
| `.isdigit()` | `"250"` | `True` |

Here is a reusable cleaning function. It returns `None` (Python's "nothing" value) when a value cannot be fixed.

```example python
def clean_amount(text):
    text = text.strip().upper().replace("KES", "").replace(",", "").strip()
    try:
        return float(text)
    except ValueError:
        return None

raw = [" 1,500 ", "KES 250", "", "abc", "99.50"]
good = []
for value in raw:
    amount = clean_amount(value)
    if amount is None:
        print(f"Could not read {value!r}")
    else:
        good.append(amount)

print(good)
print(f"Sum: {sum(good):,.2f}")
```

```output
Could not read ''
Could not read 'abc'
[1500.0, 250.0, 99.5]
Sum: 1,849.50
```

`{value!r}` shows the value with quotes, so you can see empty strings and stray spaces clearly.

## Raising your own errors

Sometimes *your* function should refuse bad input. `raise` creates an error with a clear message.

```example python
def add_expense(amount):
    if amount <= 0:
        raise ValueError("Amount must be more than zero")
    return amount

try:
    add_expense(-200)
except ValueError as error:
    print("Error:", error)
```

```output
Error: Amount must be more than zero
```

## Check yourself

```exercise
Q: Catch the error when the text is not a number.
___:
    n = int("hello")
___ ValueError:
    print("Not a number")
A: try | except
```

```exercise
Q: Remove the comma so "2,300" can become a number.
clean = "2,300".___(",", "")
A: replace
```

## Practice task

Copy five values from a real receipt, M-Pesa message or price list exactly as they are written (with "KES", commas, spaces). Put them in a list, add two deliberately broken values, and use `clean_amount()` to print a clean list and the total. Write down which error types you met while testing.
