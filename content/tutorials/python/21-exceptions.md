---
title: Python Try...Except (Exceptions)
module: 6
minutes: 12
---

Even good programs meet bad situations: a user types "abc" where a number was expected, a file is missing, a division by zero sneaks in. When Python hits a problem it cannot handle, it raises an **exception** and, unless you deal with it, the program stops with an error message. This lesson shows how to catch exceptions and keep your program running.

## What an exception looks like

Running `int("abc")` on its own stops the program with a message like this:

```python
Traceback (most recent call last):
  File "main.py", line 1, in <module>
ValueError: invalid literal for int() with base 10: 'abc'
```

The last line is the most important: it gives the exception **type** (`ValueError`) and a description.

## try and except

Put risky code in a `try` block. If an exception happens, Python jumps straight to the matching `except` block instead of crashing.

```example python
text = "abc"
try:
    number = int(text)
    print("You entered", number)
except ValueError:
    print("That is not a whole number.")
print("Program continues.")
```

```output
That is not a whole number.
Program continues.
```

The line `print("You entered", number)` never ran, because the error happened on the line before it.

## Common exception types

| Exception | Typical cause |
|---|---|
| `ValueError` | right type, wrong value, e.g. `int("abc")` |
| `TypeError` | wrong type, e.g. `"5" + 5` |
| `ZeroDivisionError` | dividing by zero |
| `IndexError` | list index out of range |
| `KeyError` | missing dictionary key |
| `NameError` | using a variable that does not exist |
| `FileNotFoundError` | opening a file that is not there |
| `AttributeError` | calling a method the object does not have |

## Handling several exceptions

You can have more than one `except` block. Python uses the first one that matches.

```example python
def share_cost(total, people):
    try:
        each = total / people
        return round(each, 2)
    except ZeroDivisionError:
        print("Cannot split between zero people.")
    except TypeError:
        print("Total and people must be numbers.")

print(share_cost(3000, 4))
print(share_cost(3000, 0))
print(share_cost("3000", 4))
```

```output
750.0
Cannot split between zero people.
None
Total and people must be numbers.
None
```

When an `except` block runs, the function reaches its end without a `return`, so it returns `None`.

### Getting the error message

Use `as` to store the exception object and read its message.

```example python
prices = {"maize": 60, "beans": 140}
try:
    print(prices["rice"])
except KeyError as error:
    print("Missing item:", error)
```

```output
Missing item: 'rice'
```

## else and finally

- `else` runs only if **no** exception happened in the `try` block.
- `finally` runs **always**, error or not. It is used for clean-up work like closing files or connections.

```example python
def read_age(text):
    try:
        age = int(text)
    except ValueError:
        print(f"'{text}' is not valid.")
    else:
        print("Age accepted:", age)
    finally:
        print("Check complete.")

read_age("27")
read_age("twenty")
```

```output
Age accepted: 27
Check complete.
'twenty' is not valid.
Check complete.
```

> **Tip:** Keep the `try` block small – only the lines that might fail. Put the "success" code in `else`. That way you do not accidentally catch errors from code you did not mean to protect.

## Raising your own exceptions

Use `raise` to signal that something is wrong, for example when a function receives a value that makes no sense.

```example python
def withdraw(balance, amount):
    if amount <= 0:
        raise ValueError("Amount must be positive.")
    if amount > balance:
        raise ValueError("Insufficient balance.")
    return balance - amount

for amount in [500, -20, 5000]:
    try:
        print("New balance:", withdraw(2000, amount))
    except ValueError as e:
        print("Error:", e)
```

```output
New balance: 1500
Error: Amount must be positive.
Error: Insufficient balance.
```

## Keep asking until valid (simulated)

In a real program you might keep asking the user until they type a valid number. Since `input()` is not available in our editor, here we loop over a list of pretend answers instead:

```example python
answers = ["ten", "", "12.5", "15"]
for answer in answers:
    try:
        quantity = int(answer)
        print("Got quantity:", quantity)
        break
    except ValueError:
        print(f"Invalid entry {answer!r}, try again.")
```

```output
Invalid entry 'ten', try again.
Invalid entry '', try again.
Invalid entry '12.5', try again.
Got quantity: 15
```

The `!r` in the f-string shows the value with quotes, which makes an empty string visible.

> **Warning:** Avoid a bare `except:` or `except Exception:` that silently swallows every error. It hides real bugs. Catch the specific exceptions you expect, and let unexpected ones surface so you can fix them.

## Exercises

```exercise
Q: Complete the keywords to catch a division error.
___:
    print(10 / 0)
___ ZeroDivisionError:
    print("Cannot divide by zero")
A: try | except
```

```exercise
Q: Add the block that always runs, whether or not there was an error.
try:
    x = int("5")
except ValueError:
    print("bad")
___:
    print("done")
A: finally
```

Next: [Python File Handling](files).
