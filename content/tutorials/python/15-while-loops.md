---
title: Python While Loops
module: 4
minutes: 10
---

A **loop** repeats a block of code. Python has two kinds: `while` loops and `for` loops. A `while` loop keeps going **as long as a condition stays true**. Use it when you do not know in advance how many times you need to repeat, for example "keep saving until I reach my target".

## The while loop

```example python
count = 1
while count <= 5:
    print("Count:", count)
    count += 1
print("Finished")
```

```output
Count: 1
Count: 2
Count: 3
Count: 4
Count: 5
Finished
```

Each time round the loop (each *iteration*), Python checks the condition. When `count` becomes 6, `count <= 5` is `False`, so the loop stops and the program continues after it.

> **Warning:** If nothing inside the loop ever makes the condition false, the loop runs forever. This is an **infinite loop**. Here, forgetting `count += 1` would print "Count: 1" endlessly. If it happens in the editor, stop the run or refresh the page.

## A practical example: saving towards a goal

Kamau saves KES 1,500 every month and wants KES 10,000 for a new phone. How many months will it take?

```example python
target = 10000
saved = 0
months = 0
while saved < target:
    saved += 1500
    months += 1
print("Months needed:", months)
print("Amount saved:", saved)
```

```output
Months needed: 7
Amount saved: 10500
```

We did not know the number of months beforehand, which is exactly when `while` shines.

## break: leaving a loop early

`break` stops the loop immediately, even if the condition is still true.

```example python
pins = ["1111", "2580", "1234", "9999"]
correct = "1234"
attempt = 0
while attempt < len(pins):
    if pins[attempt] == correct:
        print("Unlocked on attempt", attempt + 1)
        break
    print("Wrong PIN:", pins[attempt])
    attempt += 1
```

```output
Wrong PIN: 1111
Wrong PIN: 2580
Unlocked on attempt 3
```

## continue: skipping to the next round

`continue` skips the rest of the current iteration and jumps back to the condition.

```example python
n = 0
while n < 10:
    n += 1
    if n % 2 == 0:
        continue
    print(n)
```

```output
1
3
5
7
9
```

Even numbers trigger `continue`, so their `print()` is skipped.

> **Tip:** When using `continue` in a `while` loop, make sure the counter is updated **before** the `continue`, as above. Otherwise you can create an infinite loop.

## while True with break

A common pattern is an "endless" loop that exits with `break` when some condition is met inside. This keeps the exit logic in one clear place.

```example python
balance = 2000
day = 0
while True:
    day += 1
    balance -= 450
    if balance < 450:
        break
print("Money lasted", day, "days")
print("Left over:", balance)
```

```output
Money lasted 4 days
Left over: 200
```

## else on a loop

A `while` loop can have an `else` block. It runs when the loop ends **normally** (the condition became false) but **not** when it was stopped by `break`.

```example python
stock = [5, 3, 8, 2]
i = 0
while i < len(stock):
    if stock[i] == 0:
        print("An item is out of stock!")
        break
    i += 1
else:
    print("All items are in stock.")
```

```output
All items are in stock.
```

## Counting down

Loops do not have to count up.

```example python
seconds = 3
while seconds > 0:
    print(seconds)
    seconds -= 1
print("Liftoff!")
```

```output
3
2
1
Liftoff!
```

## When to use while vs for

- Use **while** when the number of repetitions depends on a condition you check as you go.
- Use **for** when you are walking through a collection or a known range of numbers. See [Python For Loops](for-loops).

## Exercises

```exercise
Q: Complete the loop so it runs while i is less than 5.
i = 0
___ i < 5:
    print(i)
    i += 1
A: while
```

```exercise
Q: Use the keyword that exits the loop immediately.
while True:
    print("once")
    ___
A: break
```

Next: [Python For Loops](for-loops).
