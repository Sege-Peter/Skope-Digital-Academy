---
title: Python If...Else
module: 4
module_title: Control Flow
minutes: 10
---

Programs become useful when they can make decisions: give a discount only to members, show "Fail" only when marks are low, block a withdrawal when the balance is too small. In Python, decisions are made with `if`, `elif` and `else`.

## The if statement

An `if` statement runs a block of code only when a condition is `True`. The condition ends with a colon, and the block underneath is indented.

```example python
balance = 1200
withdrawal = 500
if withdrawal <= balance:
    balance -= withdrawal
    print("Withdrawal successful.")
print("Balance:", balance)
```

```output
Withdrawal successful.
Balance: 700
```

The condition can be any expression that gives a Boolean, using the comparison and logical operators from [Python Booleans and Operators](booleans-operators).

> **Warning:** Forgetting the colon or the indentation is a syntax error. Every block in Python starts after a `:` and is indented by four spaces.

## else

`else` gives an alternative block that runs when the condition is `False`.

```example python
age = 16
if age >= 18:
    print("You can register to vote.")
else:
    print("You are too young to vote.")
```

```output
You are too young to vote.
```

## elif

When there are more than two possibilities, use `elif` (short for "else if"). Python checks each condition from the top and runs the **first** block whose condition is true. The rest are skipped.

```example python
marks = 67
if marks >= 80:
    grade = "A"
elif marks >= 65:
    grade = "B"
elif marks >= 50:
    grade = "C"
elif marks >= 40:
    grade = "D"
else:
    grade = "E"
print("Grade:", grade)
```

```output
Grade: B
```

Even though 67 is also greater than 50 and 40, only the `B` block runs, because it was the first match. Order matters: always put the most specific or highest condition first.

## Combining conditions

Use `and`, `or` and `not` to build more complex conditions.

```example python
age = 25
has_licence = True
if age >= 18 and has_licence:
    print("You may drive the matatu.")

day = "Sunday"
if day == "Saturday" or day == "Sunday":
    print("Weekend rates apply.")
```

```output
You may drive the matatu.
Weekend rates apply.
```

> **Tip:** `if day in ("Saturday", "Sunday"):` is a shorter way to write the second condition and is easier to extend.

## Nested if

You can put an `if` inside another `if`. Each level is indented further.

```example python
amount = 3000
is_member = True
if amount >= 2000:
    print("You qualify for free delivery.")
    if is_member:
        print("Members also get 10% off.")
        amount = amount * 0.9
print("Amount to pay:", amount)
```

```output
You qualify for free delivery.
Members also get 10% off.
Amount to pay: 2700.0
```

Keep nesting shallow. Two or three levels is fine; deeper than that, look for a way to combine conditions with `and`.

## Truthy conditions

Because every value has a truth value, you can test collections and strings directly. An empty string or list counts as `False`.

```example python
name = ""
if name:
    print("Hello,", name)
else:
    print("Name is missing.")

cart = ["bread"]
if cart:
    print("You have", len(cart), "item(s) in your cart.")
```

```output
Name is missing.
You have 1 item(s) in your cart.
```

## Short-hand if (conditional expression)

For a simple either/or value, Python has a one-line form: `value_if_true if condition else value_if_false`.

```example python
temperature = 31
feeling = "hot" if temperature > 28 else "pleasant"
print("Kisumu feels", feeling, "today.")
```

```output
Kisumu feels hot today.
```

## pass

A block cannot be empty. If you want to leave one for later, write `pass`, which does nothing.

```example python
score = 10
if score > 100:
    pass   # TODO: handle high scores later
print("Done")
```

```output
Done
```

## match (a quick look)

Python 3.10 added the `match` statement, which compares one value against several patterns. It is handy when you would otherwise write a long chain of `elif value == ...`.

```example python
light = "amber"
match light:
    case "green":
        print("Go")
    case "amber":
        print("Get ready to stop")
    case "red":
        print("Stop")
    case _:
        print("Unknown signal")
```

```output
Get ready to stop
```

The `_` case matches anything, like an `else`.

## Exercises

```exercise
Q: Complete the keyword for the extra condition.
if x > 10:
    print("big")
___ x > 5:
    print("medium")
else:
    print("small")
A: elif
```

```exercise
Q: Add the symbol that must end an if line.
if balance > 0___
    print("OK")
A: :
```

Next: repeat work with [Python While Loops](while-loops).
