---
title: Functions and the Debugging Mindset
module: 3
module_title: Week 3 – Building Your First Projects
minutes: 15
---

As programs grow, you start repeating the same chunks of code. **Functions** let you package a chunk, give it a name and reuse it. And as programs grow, mistakes appear – so this lesson also covers how to calmly find and fix them.

## Making a function

A function is a named set of instructions. You **define** it once with `def`, then **call** it whenever you need it.

```example python
def greet(name):
    print("Karibu,", name + "!")

greet("Achieng")
greet("Otieno")
```

```output
Karibu, Achieng!
Karibu, Otieno!
```

`name` is a **parameter** – a placeholder that gets a real value each time the function is called.

## Functions that give back an answer

Many functions calculate something and hand the result back with `return`.

```example python
def airtime_bonus(amount):
    if amount >= 100:
        return amount * 0.1
    return 0

print(airtime_bonus(250))
print(airtime_bonus(50))
```

```output
25.0
0
```

> **Note:** This bonus rule is made up for practice. Real bundles and bonuses change, so always check the provider's official offers.

## Exercise

```exercise
Q: Complete the function so it gives back double the number.
___ double(n):
    ___ n * 2
A: def | return
H: One keyword starts a function; the other sends the answer back.
```

## Bugs happen to everyone

A **bug** is anything that makes a program behave differently from what you wanted. Experienced developers write bugs every day – the difference is that they have a routine for fixing them.

There are three main kinds:

| Kind | What happens | Example |
|---|---|---|
| Syntax error | The program will not run at all | a missing `:` or bracket |
| Runtime error | It starts, then crashes | using a variable that does not exist |
| Logic error | It runs, but the answer is wrong | using `+` instead of `*` |

## A debugging routine

1. **Read the error message.** Python tells you the line number and the type of error, such as `NameError` or `SyntaxError`. Do not panic at the red text – it is a clue.
2. **Reproduce it.** Run again and make sure you can see the problem every time.
3. **Check your assumptions.** Add `print()` lines to see what your variables really hold.
4. **Change one thing at a time,** then run again.
5. **Explain the code out loud** – to a friend or even a cup of chai. This "rubber duck" trick finds many bugs.

Here is a logic bug. The total should be 450, but it is not. Run it and look:

```example python
prices = [100, 150, 200]
total = 0
for p in prices:
    total = p
print("Total:", total)
```

```output
Total: 200
```

The line `total = p` **replaces** the total each time instead of adding to it. The fix is `total = total + p`. Make the change and run it again.

## Exercise

```exercise
Q: Fix the bug so the loop adds each price to the total.
for p in prices:
    total = ___ + p
A: total
H: You need the old total plus the new price.
```

## Practice task

Write a function `shop_total(items)` that takes a list of prices and returns their sum. Call it with two different lists (for example, a breakfast order and a stationery order) and print both results. Then deliberately break it – remove a colon or misspell a variable – read the error message, and fix it.
