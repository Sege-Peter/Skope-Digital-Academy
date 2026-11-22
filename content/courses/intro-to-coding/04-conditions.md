---
title: Conditions – Making Decisions
module: 2
minutes: 14
---

Programs become useful when they can make choices. Should the ATM give cash or show "Insufficient funds"? Should the school portal show "Pass" or "Fail"? These choices are made with **conditions**.

## if, elif and else

A condition asks a yes/no question. If the answer is yes (`True`), a block of code runs. In Python it looks like this:

```example python
balance = 800
price = 650

if balance >= price:
    print("Payment successful")
else:
    print("Insufficient funds")
```

```output
Payment successful
```

Two things to notice:

- The line with `if` ends with a colon `:`.
- The code that belongs to the `if` is **indented** (moved in by four spaces). Python uses indentation to know which lines belong together.

Change `balance` to `500` and run again – the other message appears.

## Comparing values

Conditions use **comparison operators**:

| Operator | Meaning | Example (`marks = 65`) | Result |
|---|---|---|---|
| `==` | equal to | `marks == 65` | True |
| `!=` | not equal to | `marks != 50` | True |
| `>` | greater than | `marks > 70` | False |
| `<` | less than | `marks < 70` | True |
| `>=` | greater than or equal | `marks >= 65` | True |
| `<=` | less than or equal | `marks <= 64` | False |

> **Warning:** One `=` stores a value; two `==` compare values. Mixing them up is one of the most common beginner bugs.

## More than two choices

Use `elif` ("else if") when there are several possibilities. Python checks each condition from top to bottom and runs the **first** one that is true.

```example python
marks = 72

if marks >= 80:
    grade = "A"
elif marks >= 65:
    grade = "B"
elif marks >= 50:
    grade = "C"
else:
    grade = "D"

print("Kiprono scored", marks, "- grade", grade)
```

```output
Kiprono scored 72 - grade B
```

Even though 72 is also greater than 50, Python stops at the first match (`>= 65`), so the grade is B. Order matters!

## Exercise

```exercise
Q: Complete the condition so "Adult" prints for ages 18 and above.
age = 19
___ age >= 18:
    print("Adult")
___:
    print("Minor")
A: if | else
H: The first keyword asks the question; the second catches everything else.
```

## Combining conditions

Sometimes you need two things to be true at once, or either one. Python uses the plain words `and`, `or` and `not`.

```example python
has_ticket = True
age = 16

if has_ticket and age >= 18:
    print("Welcome to the concert")
else:
    print("Sorry, entry not allowed")
```

```output
Sorry, entry not allowed
```

- `and` – both sides must be true.
- `or` – at least one side must be true.
- `not` – flips True to False and the other way round.

## Exercise

```exercise
Q: Choose the operator that checks if two values are equal.
if pin ___ 1234:
    print("Correct PIN")
A: ==
H: It is two of the symbol you use to store a value.
```

## Practice task

Write a program for a small shop in your area: set a variable `amount_spent`. If it is KES 1,000 or more, print "You get a free soda!". If it is between 500 and 999, print "Spend a little more for a gift". Otherwise print "Thank you for shopping". Test it with three different amounts.
