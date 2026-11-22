---
title: Your Python Toolkit
module: 1
module_title: Week 1 – First Programs
minutes: 15
---

Welcome to the course! Over the next four weeks you will build five small but real programs in Python. You do not need a laptop with special software – every example here has a **Try it Yourself »** button that runs Python right in your browser, even on a phone.

## Why Python?

Python is one of the most popular programming languages in the world. It reads almost like English, so beginners pick it up quickly. It is also used for serious work:

- **Data analysis** – reports for banks, NGOs, SACCOs and hospitals
- **Automation** – scripts that rename files, send reports or clean spreadsheets
- **Web back-ends** – the server side of websites and apps
- **AI and machine learning** – most AI tools are built with Python

That means the skills from this course can lead to freelance gigs, internships and jobs.

## Your first program

A program is a list of instructions. Python runs them from top to bottom. The `print()` function shows text on the screen.

```example python
print("Habari! Welcome to Python.")
print("I am learning to code in Kisumu.")
```

```output
Habari! Welcome to Python.
I am learning to code in Kisumu.
```

Text inside quotes is called a **string**. You can use single `'...'` or double `"..."` quotes – just be consistent.

## Variables: labelled boxes

A **variable** stores a value so you can use it later. You create one with `=`.

```example python
name = "Achieng"
town = "Nakuru"
age = 18
height = 1.65

print(name)
print(town)
print(age + 1)
print(height)
```

```output
Achieng
Nakuru
19
1.65
```

Python has a few basic value types you will use all the time:

| Type | Example | Used for |
|---|---|---|
| `str` | `"Mombasa"` | text |
| `int` | `250` | whole numbers |
| `float` | `99.5` | numbers with a decimal point |
| `bool` | `True` / `False` | yes/no answers |

> **Tip:** Variable names cannot have spaces. Use underscores instead: `phone_number`, `total_price`.

## f-strings: mixing text and values

Most programs need to put values inside sentences. The easiest way is an **f-string** – put an `f` before the quotes and wrap variables in `{ }`.

```example python
name = "Brian"
item = "airtime"
amount = 50

print(f"{name} bought KES {amount} of {item}.")
print(f"Next week he will spend KES {amount * 2}.")
```

```output
Brian bought KES 50 of airtime.
Next week he will spend KES 100.
```

You can even do maths inside the braces, as the second line shows.

## Comments

A line starting with `#` is a **comment**. Python ignores it. Comments are notes for humans – your future self or a client reading your code.

```python
# Price of one mandazi in KES
price = 10
```

## Practice the basics

```exercise
Q: Store the town in a variable and print it.
town ___ "Eldoret"
print(___)
A: = | town
H: Use the assignment sign, then the variable name without quotes.
```

```exercise
Q: Complete the f-string so it prints "Fatuma is 19".
name = "Fatuma"
age = 19
print(___"{name} is {___}")
A: f | age
H: f-strings start with one letter before the quote.
```

## Practice task

Open a new example (click **Try it Yourself »** on any box above) and write a short program about yourself: store your name, your town and a skill you want to sell online in three variables. Then print one sentence using an f-string, for example *"I am Kiprono from Kericho and I will offer data entry services."* Keep the code – you will reuse the idea in later lessons.
