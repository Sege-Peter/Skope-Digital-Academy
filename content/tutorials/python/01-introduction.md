---
title: Python Introduction
module: 1
module_title: Getting Started
minutes: 8
---

Python is a programming language that reads almost like plain English. It is used to build websites, analyse data, automate boring office tasks, train machine-learning models and control hardware. It is also one of the friendliest first languages you can pick, which is why universities and bootcamps across Kenya and the rest of the world teach it early.

In this tutorial you will go from printing your first line of text to writing classes, handling errors and working with files. Every lesson has examples you can run in your browser, so you do not need to install anything to follow along.

## What is Python?

Python was created by Guido van Rossum and first released in 1991. The name comes from the British comedy group Monty Python, not from the snake. Today it is maintained by the Python Software Foundation and a large community of volunteers.

Python is:

- **Interpreted** – you run the code directly; there is no separate compile step to wait for.
- **High-level** – you think about your problem, not about memory addresses or CPU registers.
- **General-purpose** – the same language works for a quick script, a web backend or a data report.
- **Free and open source** – anyone can download it, use it and read its source code.

## Your first Python program

Here is the classic first program. It uses the built-in `print()` function to show text on the screen.

```example python
print("Habari, Kenya!")
```

```output
Habari, Kenya!
```

Click **Try it Yourself »** to open the code in our editor, change the message and run it again. Experimenting like this is the fastest way to learn.

## What can Python do?

Python is everywhere once you start looking:

- **Web development** – frameworks such as Django and Flask power many websites and APIs.
- **Data analysis** – libraries like pandas help analysts work through sales records, survey results and M-Pesa statements.
- **Automation** – a short script can rename hundreds of files or send reminder emails.
- **Machine learning and AI** – most AI research and tooling is written in Python.
- **Education and science** – researchers use it to process lab results, weather data and maps.

## Why learn Python first?

Python has a small set of rules and very little "punctuation noise". Look at this short program that works out a total price. You can probably guess what it does before reading any further.

```example python
price = 250
quantity = 4
print("Total:", price * quantity, "KES")
```

```output
Total: 1000 KES
```

There are no semicolons, no type declarations and no curly braces. Python uses **indentation** (spaces at the start of a line) to group code, which forces you to write neat, readable programs. You will learn more about that in [Python Syntax](syntax).

> **Note:** This tutorial uses **Python 3**. Python 2 reached its end of life in 2020, so any new code you write should be Python 3. If you see `print "hello"` without brackets online, that is old Python 2 code.

## Python 3 versions

Python 3 gets a new minor version roughly every year (3.10, 3.11, 3.12 and so on). Each version adds features and speed improvements, but code written for an earlier Python 3 version almost always runs on a newer one. Our in-browser editor runs Python 3.12.

## How this tutorial is organised

The lessons are grouped into modules:

1. **Getting Started** – what Python is, how to run it and its basic syntax.
2. **Variables and Data Types** – storing numbers, text and true/false values.
3. **Collections** – lists, tuples, sets and dictionaries.
4. **Control Flow** – making decisions and repeating work with loops.
5. **Functions and Classes** – organising code into reusable pieces.
6. **Going Further** – modules, error handling and files.
7. **Reference and Quiz** – a built-in functions table and a quiz to test yourself.

Each lesson ends with short exercises. Try them before moving on; they only take a minute and they make the ideas stick.

> **Tip:** Do not just read the examples – run them, break them on purpose and fix them again. Error messages are teachers, not enemies.

## Exercise

```exercise
Q: Complete the code so it prints the greeting.
___("Karibu Python!")
A: print
H: It is the built-in function that shows text on the screen.
```

Next, learn how to run Python on your own computer in [Python Getting Started](getting-started).
