---
title: Python Syntax and Comments
module: 1
minutes: 10
---

Syntax is the set of rules that decides how code must be written so the computer understands it. Python's syntax is famously clean, but it has one rule that surprises newcomers: **indentation matters**.

## One statement per line

In Python, each line is normally one instruction (a *statement*). You do not need a semicolon at the end.

```example python
school = "Maseno School"
students = 1200
print(school)
print(students)
```

```output
Maseno School
1200
```

Python runs the lines from top to bottom, one after the other.

## Indentation

Many languages use curly braces `{ }` to group lines together. Python uses **indentation** – the spaces at the start of a line. Lines that belong to the same block must be indented by the same amount.

```example python
marks = 78
if marks >= 50:
    print("Pass")
    print("Well done, Achieng!")
print("Results printed.")
```

```output
Pass
Well done, Achieng!
Results printed.
```

The two indented `print()` lines belong to the `if` statement, so they only run when the condition is true. The last line is not indented, so it always runs. You will learn about `if` properly in [Python If...Else](if-else).

The standard is **four spaces** per level. Code editors insert them automatically when you press Tab.

> **Warning:** Mixing different amounts of indentation in one block causes an `IndentationError`, and the program will not run at all. Pick four spaces and stick to it.

Here is what broken indentation looks like (display only – it will not run):

```python
if marks >= 50:
    print("Pass")
      print("Well done!")   # IndentationError: unexpected indent
```

## Case sensitivity

Python treats uppercase and lowercase letters as different. `name`, `Name` and `NAME` are three separate variables, and `Print("hi")` fails because the function is called `print`, all lowercase.

## Comments

A **comment** is a note for humans. Python ignores it completely. Comments start with a `#` and run to the end of the line.

```example python
# Calculate the cost of a matatu trip for a group
fare = 100          # fare per person in KES
people = 5
print(fare * people)  # total cost
```

```output
500
```

Good comments explain **why** the code does something, not just repeat what it obviously does.

### Commenting out code

You can put `#` in front of a line to stop it from running without deleting it. This is useful while testing.

```example python
print("This line runs.")
# print("This line is switched off.")
print("So does this one.")
```

```output
This line runs.
So does this one.
```

### Multi-line notes

Python has no special multi-line comment symbol. You can either start each line with `#`, or use a string in triple quotes that is not assigned to anything. Python evaluates the string and throws it away.

```example python
"""
This program prints the capital cities
of three East African countries.
"""
print("Kenya: Nairobi")
print("Uganda: Kampala")
print("Tanzania: Dodoma")
```

```output
Kenya: Nairobi
Uganda: Kampala
Tanzania: Dodoma
```

> **Tip:** Triple-quoted strings at the top of a file or function are called *docstrings*. Tools use them to build documentation, which is why you will see them a lot in real projects.

## Long lines

If a line gets too long, you can break it inside brackets. Python knows the statement continues until the bracket closes.

```example python
total = (1200 + 3500 +
         800 + 450)
print(total)
```

```output
5950
```

## Exercises

```exercise
Q: Add the symbol that starts a comment.
___ This is a comment
print("Hello")
A: #
```

```exercise
Q: How many spaces is the standard indentation in Python? Fill in the number.
Standard indentation = ___ spaces
A: 4 / four
```

Next: store values in [Python Variables](variables).
