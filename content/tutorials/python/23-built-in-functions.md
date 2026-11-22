---
title: Python Built-in Functions Reference
module: 7
module_title: Reference and Quiz
minutes: 8
---

Python has around 70 **built-in functions** that are always available, with no `import` needed. This page lists the ones you are most likely to use, grouped by job. Use it as a quick reference while you code.

## Input and output

| Function | Description | Example |
|---|---|---|
| `print()` | Shows values on the screen | `print("Habari", 5)` |
| `input()` | Reads a line typed by the user (not available in our browser editor) | `name = input("Name: ")` |
| `open()` | Opens a file and returns a file object | `open("data.txt", "r")` |
| `format()` | Formats a value with a format specifier | `format(1234.5, ",.2f")` → `'1,234.50'` |
| `repr()` | Returns a developer-friendly text form of a value | `repr("hi")` → `"'hi'"` |

## Types and conversion

| Function | Description | Example |
|---|---|---|
| `int()` | Converts to a whole number | `int("42")` → `42` |
| `float()` | Converts to a decimal number | `float("3.5")` → `3.5` |
| `str()` | Converts to text | `str(100)` → `'100'` |
| `bool()` | Converts to `True` or `False` | `bool("")` → `False` |
| `list()` | Creates a list | `list("abc")` → `['a', 'b', 'c']` |
| `tuple()` | Creates a tuple | `tuple([1, 2])` → `(1, 2)` |
| `set()` | Creates a set (removes duplicates) | `set([1, 1, 2])` → `{1, 2}` |
| `dict()` | Creates a dictionary | `dict(a=1)` → `{'a': 1}` |
| `complex()` | Creates a complex number | `complex(2, 3)` → `(2+3j)` |
| `type()` | Returns the type of a value | `type(5)` → `<class 'int'>` |
| `isinstance()` | Checks if a value is of a given type | `isinstance(5, int)` → `True` |

## Numbers and maths

| Function | Description | Example |
|---|---|---|
| `abs()` | Absolute (positive) value | `abs(-7)` → `7` |
| `round()` | Rounds to a number of decimal places | `round(3.14159, 2)` → `3.14` |
| `pow()` | Raises to a power | `pow(2, 5)` → `32` |
| `divmod()` | Floor division and remainder together | `divmod(17, 5)` → `(3, 2)` |
| `sum()` | Adds up the items of an iterable | `sum([1, 2, 3])` → `6` |
| `min()` | Smallest item | `min(4, 9, 2)` → `2` |
| `max()` | Largest item | `max(4, 9, 2)` → `9` |
| `bin()` | Binary text form of an integer | `bin(5)` → `'0b101'` |
| `hex()` | Hexadecimal text form of an integer | `hex(255)` → `'0xff'` |
| `oct()` | Octal text form of an integer | `oct(8)` → `'0o10'` |

## Sequences and iteration

| Function | Description | Example |
|---|---|---|
| `len()` | Number of items | `len("Kenya")` → `5` |
| `range()` | A sequence of numbers | `list(range(3))` → `[0, 1, 2]` |
| `enumerate()` | Pairs each item with a counter | `list(enumerate("ab"))` → `[(0, 'a'), (1, 'b')]` |
| `zip()` | Combines several iterables item by item | `list(zip([1, 2], "ab"))` → `[(1, 'a'), (2, 'b')]` |
| `sorted()` | Returns a new sorted list | `sorted([3, 1, 2])` → `[1, 2, 3]` |
| `reversed()` | Returns the items in reverse order | `list(reversed([1, 2, 3]))` → `[3, 2, 1]` |
| `map()` | Applies a function to every item | `list(map(str.upper, ["a", "b"]))` → `['A', 'B']` |
| `filter()` | Keeps items for which a function is true | `list(filter(None, [0, 1, 2]))` → `[1, 2]` |
| `any()` | `True` if at least one item is true | `any([0, 0, 1])` → `True` |
| `all()` | `True` if every item is true | `all([1, 1, 0])` → `False` |
| `iter()` | Gets an iterator from an iterable | `it = iter([1, 2])` |
| `next()` | Gets the next item from an iterator | `next(iter([1, 2]))` → `1` |

## Text and characters

| Function | Description | Example |
|---|---|---|
| `chr()` | Character for a Unicode code | `chr(65)` → `'A'` |
| `ord()` | Unicode code for a character | `ord("A")` → `65` |
| `ascii()` | Text form with non-ASCII characters escaped | `print(ascii("é"))` shows `'\xe9'` |

## Objects and classes

| Function | Description | Example |
|---|---|---|
| `id()` | Unique identity number of an object | `id(x)` |
| `dir()` | Lists the names an object or module contains | `dir(str)` |
| `help()` | Shows built-in documentation | `help(len)` |
| `hasattr()` | Checks if an object has an attribute | `hasattr("hi", "upper")` → `True` |
| `getattr()` | Gets an attribute by name | `getattr("hi", "upper")()` → `'HI'` |
| `setattr()` | Sets an attribute by name | `setattr(obj, "age", 30)` |
| `callable()` | Checks if something can be called | `callable(print)` → `True` |
| `super()` | Accesses the parent class | `super().__init__()` |
| `issubclass()` | Checks if a class inherits from another | `issubclass(bool, int)` → `True` |

## Seeing them in action

```example python
scores = [72, 88, 95, 61]
print(len(scores), sum(scores), min(scores), max(scores))
print(sorted(scores, reverse=True))
print(any(s < 65 for s in scores), all(s >= 60 for s in scores))
print(list(zip(["Amina", "Baraka"], scores)))
```

```output
4 316 61 95
[95, 88, 72, 61]
True True
[('Amina', 72), ('Baraka', 88)]
```

```example python
print(round(1234.5678, 2), abs(-15), divmod(100, 7))
print(int("250") + float("0.5"), str(2025) + "!")
print(ord("K"), chr(75), bin(10), hex(255))
print(isinstance(3.0, float), type([]).__name__)
```

```output
1234.57 15 (14, 2)
250.5 2025!
75 K 0b1010 0xff
True list
```

> **Tip:** Type `help(function_name)` in the Python shell (for example `help(sorted)`) to read the official description of any built-in function.

Related lessons: [Numbers and Casting](numbers-casting), [Lists](lists), [For Loops](for-loops), [Lambda and Comprehensions](lambda-comprehensions).

Ready to test yourself? Take the [Python Quiz](quiz).
