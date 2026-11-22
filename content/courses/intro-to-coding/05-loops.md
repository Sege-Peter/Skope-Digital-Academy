---
title: Loops – Repeating Work
module: 2
minutes: 14
---

Imagine sending the same SMS reminder to 200 chama members, one by one. Boring, slow and easy to get wrong. Computers are perfect for repetitive work, and the tool for repetition is the **loop**.

## The for loop

A `for` loop repeats a block of code once for each item in a collection. Here we greet every member of a list:

```example python
members = ["Achieng", "Brian", "Fatuma", "Kiprono"]

for name in members:
    print("Hello", name, "- meeting is on Saturday")
```

```output
Hello Achieng - meeting is on Saturday
Hello Brian - meeting is on Saturday
Hello Fatuma - meeting is on Saturday
Hello Kiprono - meeting is on Saturday
```

Read it as: "For each `name` in `members`, print a greeting." The indented line runs four times, once per name. Add a fifth name to the list and the loop handles it automatically.

## Counting with range()

To repeat something a set number of times, use `range()`. `range(1, 6)` gives the numbers 1, 2, 3, 4, 5 – it stops **before** the second number.

```example python
for week in range(1, 6):
    savings = week * 200
    print("Week", week, "savings: KES", savings)
```

```output
Week 1 savings: KES 200
Week 2 savings: KES 400
Week 3 savings: KES 600
Week 4 savings: KES 800
Week 5 savings: KES 1000
```

## Exercise

```exercise
Q: Complete the loop so it prints the numbers 1 to 3.
___ n in range(1, ___):
    print(n)
A: for | 4
H: range stops one before the end number.
```

## Adding up as you go

A very common pattern is a **running total**: start at zero, then add each item inside the loop.

```example python
daily_sales = [1200, 950, 1800, 600, 2100]
total = 0

for sale in daily_sales:
    total = total + sale

print("Total sales this week: KES", total)
```

```output
Total sales this week: KES 6650
```

This small pattern is the heart of countless real programs: totalling sales, counting votes, adding up marks.

## The while loop

A `while` loop keeps repeating **as long as** a condition is true. It is useful when you do not know in advance how many times to repeat.

```example python
balance = 1000
day = 0

while balance > 0:
    balance = balance - 300
    day = day + 1

print("Money ran out on day", day)
```

```output
Money ran out on day 4
```

> **Warning:** If the condition never becomes false, the loop runs forever – an **infinite loop**. Always make sure something inside the loop moves it towards stopping (here, `balance` gets smaller each time).

## Exercise

```exercise
Q: Fill in the starting value for a running total.
total = ___
for price in [50, 30, 20]:
    total = total + price
A: 0
H: Before adding anything, how much do you have?
```

## Practice task

Make a list of five things you spend money on in a typical week (fare, airtime, lunch…) with their amounts in KES. Write a loop that prints each item and a running total. At the end, print the total spent. If the total is over KES 1,500, print a friendly warning using an `if` after the loop.
