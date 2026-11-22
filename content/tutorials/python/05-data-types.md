---
title: Python Data Types
module: 2
minutes: 9
---

Every value in Python has a **data type**. The type decides what you can do with the value: you can multiply two numbers, but not two names; you can make a word uppercase, but not a number. Knowing the type of your data prevents a lot of confusing errors.

## The built-in types

Python comes with these core types. You will meet each one in detail later in the tutorial.

| Category | Type | Example |
|---|---|---|
| Text | `str` | `"Eldoret"` |
| Numbers | `int`, `float`, `complex` | `42`, `3.5`, `2+3j` |
| Sequences | `list`, `tuple`, `range` | `[1, 2]`, `(1, 2)`, `range(5)` |
| Mapping | `dict` | `{"name": "Amina"}` |
| Sets | `set`, `frozenset` | `{1, 2, 3}` |
| Boolean | `bool` | `True`, `False` |
| Nothing | `NoneType` | `None` |
| Binary | `bytes`, `bytearray` | `b"hi"` |

## Checking a type with type()

The built-in `type()` function tells you what type a value is.

```example python
print(type("Eldoret"))
print(type(42))
print(type(3.5))
print(type(True))
print(type(None))
```

```output
<class 'str'>
<class 'int'>
<class 'float'>
<class 'bool'>
<class 'NoneType'>
```

The word `class` appears because in Python every type is a class. You will learn what that means in [Python Classes and Objects](classes-objects).

## Types are set automatically

You never announce the type yourself. Python looks at the value and picks the type for you.

```example python
county = "Nyeri"             # str
population = 759164          # int
avg_rainfall = 1050.5        # float
is_capital = False           # bool
crops = ["tea", "coffee"]    # list
location = (-0.42, 36.95)    # tuple
farmer = {"name": "Kamau", "acres": 3}  # dict
grades = {"A", "B", "C"}     # set

for value in [county, population, avg_rainfall, is_capital,
              crops, location, farmer, grades]:
    print(type(value).__name__)
```

```output
str
int
float
bool
list
tuple
dict
set
```

Using `type(value).__name__` gives just the type name without the `<class ...>` wrapper. Do not worry about the `for` loop yet; it simply repeats the `print()` for each value.

## Setting a specific type

Sometimes you want a value of a particular type. Each type name can also be called like a function to create a value of that type. This is called **casting**, and it is covered in [Python Numbers and Casting](numbers-casting).

```example python
a = str(2024)
b = int("15")
c = float(7)
d = list("abc")
print(a, type(a).__name__)
print(b, type(b).__name__)
print(c, type(c).__name__)
print(d, type(d).__name__)
```

```output
2024 str
15 int
7.0 float
['a', 'b', 'c'] list
```

## Why types matter

The same operator can behave differently depending on the types involved. The `+` operator adds numbers but joins strings:

```example python
print(5 + 5)
print("5" + "5")
print([1, 2] + [3])
```

```output
10
55
[1, 2, 3]
```

Mixing types that do not fit together raises an error. For example, `"5" + 5` gives a `TypeError` because Python will not guess whether you meant `10` or `"55"`. You must convert one side first.

> **Warning:** Numbers read from a file, a form or a spreadsheet often arrive as **strings**. Always convert them with `int()` or `float()` before doing arithmetic.

## The None value

`None` is a special value meaning "nothing" or "no value yet". It is useful as a placeholder.

```example python
winner = None
print(winner)
winner = "Faith Kipyegon"
print(winner)
```

```output
None
Faith Kipyegon
```

## Checking a type with isinstance()

To ask "is this value of type X?", use `isinstance()`. It returns `True` or `False`.

```example python
amount = 1500
print(isinstance(amount, int))
print(isinstance(amount, str))
```

```output
True
False
```

> **Tip:** Prefer `isinstance()` over comparing `type(x) == int` in real code. It also works correctly with subclasses, which you will meet later.

## Exercises

```exercise
Q: Fill in the function that shows the type of a value.
print(___(3.14))
A: type
```

```exercise
Q: What type name does Python give to True and False?
<class '___'>
A: bool
```

Next: [Python Numbers and Casting](numbers-casting).
