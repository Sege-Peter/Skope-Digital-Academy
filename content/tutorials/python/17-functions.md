---
title: Python Functions
module: 5
module_title: Functions and Classes
minutes: 12
---

A **function** is a named block of code that does one job. You write it once and run it ("call" it) as many times as you like. Functions keep programs short, make them easier to test, and let you think about one problem at a time.

You have already used many built-in functions: `print()`, `len()`, `type()`, `sum()`. Now you will write your own.

## Defining and calling a function

Use the `def` keyword, a name, brackets and a colon. The body is indented.

```example python
def greet():
    print("Karibu Skope Digital Academy!")

greet()
greet()
```

```output
Karibu Skope Digital Academy!
Karibu Skope Digital Academy!
```

Defining the function does not run it. The code inside only runs when you call `greet()`.

## Parameters and arguments

Functions become powerful when you pass information into them. The names in the definition are **parameters**; the values you pass when calling are **arguments**.

```example python
def greet(name, town):
    print(f"Hello {name} from {town}!")

greet("Wafula", "Bungoma")
greet("Halima", "Garissa")
```

```output
Hello Wafula from Bungoma!
Hello Halima from Garissa!
```

## Returning a value

`return` sends a result back to the caller, so you can store it or use it in a calculation. The function stops as soon as it reaches `return`.

```example python
def add_vat(price):
    return price * 1.16

cost = add_vat(500)
print(cost)
print(add_vat(1000) + add_vat(250))
```

```output
580.0
1450.0
```

A function with no `return` statement returns `None`.

> **Tip:** Prefer functions that **return** results over functions that print them. A returned value can be printed, saved, compared or passed to another function; a printed value can only be read.

### Returning several values

Return several values separated by commas. Python packs them into a tuple, which you can unpack.

```example python
def min_max(numbers):
    return min(numbers), max(numbers)

low, high = min_max([34, 12, 89, 56])
print(low, high)
```

```output
12 89
```

## Default parameter values

Give a parameter a default value with `=`. If the caller leaves that argument out, the default is used.

```example python
def bus_fare(distance_km, rate=10):
    return distance_km * rate

print(bus_fare(30))
print(bus_fare(30, 12))
```

```output
300
360
```

## Keyword arguments

You can pass arguments by name. Then their order does not matter, and the call is easier to read.

```example python
def describe(name, age, county):
    print(f"{name}, {age}, from {county}")

describe(county="Nyandarua", name="Gathoni", age=29)
```

```output
Gathoni, 29, from Nyandarua
```

## Any number of arguments: *args and **kwargs

`*args` collects extra positional arguments into a tuple. `**kwargs` collects extra keyword arguments into a dictionary.

```example python
def total(*amounts):
    return sum(amounts)

def profile(**details):
    for key, value in details.items():
        print(f"{key}: {value}")

print(total(100, 250, 75))
profile(name="Kiprotich", sport="athletics")
```

```output
425
name: Kiprotich
sport: athletics
```

## Variable scope

Variables created inside a function are **local**: they exist only while the function runs and cannot be seen outside. Variables created at the top level of the file are **global** and can be read inside functions.

```example python
rate = 0.16          # global

def tax(amount):
    result = amount * rate   # result is local
    return result

print(tax(200))
print(rate)
```

```output
32.0
0.16
```

Trying to `print(result)` outside the function would raise a `NameError`.

> **Warning:** Avoid changing global variables from inside functions (with the `global` keyword). Pass values in as parameters and return results instead. It makes bugs much easier to find.

## Docstrings

A string on the first line of a function describes what it does. Tools and `help()` display it.

```example python
def area_of_plot(length, width):
    """Return the area of a rectangular plot in square metres."""
    return length * width

print(area_of_plot(30, 20))
print(area_of_plot.__doc__)
```

```output
600
Return the area of a rectangular plot in square metres.
```

## Functions calling functions

Small functions can be combined to build bigger ones.

```example python
def subtotal(prices):
    return sum(prices)

def apply_discount(amount, percent):
    return amount - amount * percent / 100

def checkout(prices, percent=0):
    return apply_discount(subtotal(prices), percent)

print(checkout([200, 300, 500]))
print(checkout([200, 300, 500], 10))
```

```output
1000.0
900.0
```

## Exercises

```exercise
Q: Use the keyword that defines a function.
___ say_hello():
    print("Hello")
A: def
```

```exercise
Q: Send the result back to the caller.
def double(x):
    ___ x * 2
A: return
```

Next: [Python Lambda and List Comprehensions](lambda-comprehensions).
