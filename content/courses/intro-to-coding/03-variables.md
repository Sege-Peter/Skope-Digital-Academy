---
title: Variables – Storing Information
module: 2
module_title: Week 2 – The Building Blocks
minutes: 14
---

Every program needs to remember things: your name, your M-Pesa balance, the price of a bag of unga. Programs remember information using **variables**.

## A variable is a labelled box

Think of a variable as a box with a name written on the side. You put a value inside, and later you can look inside, use it, or replace it with something new.

```example python
name = "Achieng"
town = "Kisumu"
age = 18

print(name)
print(town)
print(age)
```

```output
Achieng
Kisumu
18
```

The `=` sign does not mean "equals" like in maths. In code it means **"store this value in this box"**. Read `age = 18` as "age gets 18".

## Types of values

Values come in different kinds, called **data types**. The four you will meet first:

| Type | Example | Used for |
|---|---|---|
| Text (string) | `"Brian"` | names, messages, phone numbers |
| Whole number (integer) | `250` | counts, prices in whole shillings |
| Decimal number (float) | `12.5` | weights, averages, percentages |
| True/False (boolean) | `True` | yes/no answers |

Text always goes inside quote marks. Numbers do not. `"250"` is text that looks like a number; `250` is a number you can do maths with.

## Changing and using variables

Variables are useful because they can change and be combined.

```example python
price_of_unga = 210
bags = 3
total = price_of_unga * bags
print("Total for", bags, "bags is KES", total)

price_of_unga = 225
total = price_of_unga * bags
print("New total is KES", total)
```

```output
Total for 3 bags is KES 630
New total is KES 675
```

When the price changed, we only had to update one variable. That is the power of naming your data instead of typing the same number everywhere.

## Exercise

```exercise
Q: Store the name Fatuma in a variable, then print it.
customer ___ "Fatuma"
print(___)
A: = | customer
H: First store the value, then use the box's name.
```

## Naming variables well

Good names make code easy to read – for you next month and for anyone you work with.

- Use clear words: `total_sales` is better than `ts`.
- Use lowercase and underscores in Python: `phone_number`.
- Names cannot start with a number or contain spaces: `2nd_price` and `my price` are not allowed.
- Names are case-sensitive: `Price` and `price` are two different boxes.

> **Tip:** If you have to explain what a variable holds, the name is probably too short. Rename it.

## Same idea, every language

Variables exist in every programming language. In JavaScript you would write `let town = "Nakuru";`. The punctuation changes, but the idea – a named box holding a value – stays the same. Learning ideas, not just syntax, is what lets coders switch languages later.

## Exercise

```exercise
Q: Fill in the result so the comment is correct.
apples = 4
apples = apples + 2
# apples now holds ___
A: 6
H: Take the old value, add 2, and store it back.
```

## Practice task

Write a short Python program about yourself using **at least five variables**: your name, your town, your age, a favourite food and its price in KES. Print a sentence that uses them, for example "Brian from Kitale loves chapati (KES 30)". Run it in the editor and change one value to see the output update.
