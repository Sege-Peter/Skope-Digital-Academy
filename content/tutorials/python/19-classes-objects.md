---
title: Python Classes and Objects
module: 5
minutes: 13
---

Python is an **object-oriented** language. Every value you have used – strings, lists, numbers – is an **object**, and every object is built from a **class**. A class is a blueprint; an object is a thing made from that blueprint. In this lesson you will write your own classes.

## Why classes?

Imagine a SACCO app that tracks member accounts. Each account has data (owner, balance) and actions (deposit, withdraw). A class bundles that data and those actions together, so every account looks after itself.

## Creating a class and an object

Use the `class` keyword. By convention class names use **CapitalisedWords**.

```example python
class Account:
    pass

acc = Account()
print(type(acc).__name__)
```

```output
Account
```

`Account()` creates a new object (also called an *instance*) of the class.

## The `__init__` method

`__init__` is a special method that runs automatically when an object is created. Use it to set up the object's data, which are called **attributes**.

```example python
class Account:
    def __init__(self, owner, balance=0):
        self.owner = owner
        self.balance = balance

a = Account("Wambui", 5000)
b = Account("Odera")
print(a.owner, a.balance)
print(b.owner, b.balance)
```

```output
Wambui 5000
Odera 0
```

`self` refers to the object being created or used. `self.owner = owner` stores the argument on that particular object. Each object has its own copy of the attributes.

> **Note:** `self` must be the first parameter of every method, but you never pass it yourself. Python fills it in: `a.deposit(100)` becomes `Account.deposit(a, 100)` behind the scenes.

## Methods

A **method** is a function defined inside a class. It can read and change the object's attributes through `self`.

```example python
class Account:
    def __init__(self, owner, balance=0):
        self.owner = owner
        self.balance = balance

    def deposit(self, amount):
        self.balance += amount

    def withdraw(self, amount):
        if amount > self.balance:
            print(f"Sorry {self.owner}, insufficient funds.")
            return False
        self.balance -= amount
        return True

acc = Account("Njoroge", 1000)
acc.deposit(500)
acc.withdraw(2000)
acc.withdraw(300)
print("Balance:", acc.balance)
```

```output
Sorry Njoroge, insufficient funds.
Balance: 1200
```

## The `__str__` method

Printing an object normally shows something unhelpful like `<__main__.Account object at 0x...>`. Define `__str__` to control what `print()` shows.

```example python
class Product:
    def __init__(self, name, price):
        self.name = name
        self.price = price

    def __str__(self):
        return f"{self.name} - KES {self.price:,}"

item = Product("Solar lamp", 2450)
print(item)
```

```output
Solar lamp - KES 2,450
```

## Class attributes

Attributes set directly in the class body (not inside `__init__`) are shared by **all** objects of that class.

```example python
class Student:
    school = "Kakamega School"     # class attribute

    def __init__(self, name):
        self.name = name           # instance attribute

s1 = Student("Shikuku")
s2 = Student("Makokha")
print(s1.name, "-", s1.school)
print(s2.name, "-", s2.school)
```

```output
Shikuku - Kakamega School
Makokha - Kakamega School
```

## Changing attributes

You can change an object's attributes directly, though it is often cleaner to do it through methods.

```example python
class Phone:
    def __init__(self, model, battery):
        self.model = model
        self.battery = battery

p = Phone("Tecno Spark", 80)
p.battery = 45
print(p.model, p.battery)
```

```output
Tecno Spark 45
```

## Inheritance

A class can **inherit** from another class. The child class gets all the parent's attributes and methods, and can add new ones or change existing ones.

```example python
class Vehicle:
    def __init__(self, plate, seats):
        self.plate = plate
        self.seats = seats

    def describe(self):
        return f"{self.plate} with {self.seats} seats"

class Matatu(Vehicle):
    def __init__(self, plate, seats, route):
        super().__init__(plate, seats)
        self.route = route

    def describe(self):
        return super().describe() + f" on route {self.route}"

car = Vehicle("KDA 123A", 5)
mat = Matatu("KCB 456B", 14, "Kisumu - Kakamega")
print(car.describe())
print(mat.describe())
print(isinstance(mat, Vehicle))
```

```output
KDA 123A with 5 seats
KCB 456B with 14 seats on route Kisumu - Kakamega
True
```

`super()` gives access to the parent class, so the child can reuse the parent's `__init__` and `describe()` instead of repeating that code. A `Matatu` is also a `Vehicle`, which is why `isinstance()` returns `True`.

> **Tip:** Use inheritance when the child truly "is a" kind of the parent (a matatu *is a* vehicle). If one thing merely *has* another, store it as an attribute instead.

## Exercises

```exercise
Q: Use the keyword that creates a class.
___ Farm:
    pass
A: class
```

```exercise
Q: Complete the name of the method that runs when an object is created.
def ___(self, name):
    self.name = name
A: __init__
```

Next: [Python Modules](modules).
