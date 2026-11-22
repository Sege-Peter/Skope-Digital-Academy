---
title: "Project: Grades Analyser"
module: 2
minutes: 20
---

Mr Otieno teaches mathematics at a secondary school in Homa Bay. After every test he spends an evening working out averages, the top student and letter grades. This week you will write a program that does it in a second. You will learn **decisions** (`if`, `elif`, `else`) and **dictionaries**.

## Comparing values

A comparison gives `True` or `False`:

| Operator | Meaning |
|---|---|
| `==` | equal to |
| `!=` | not equal to |
| `>` / `<` | greater / less than |
| `>=` / `<=` | greater or equal / less or equal |

You can combine comparisons with `and`, `or` and `not`.

## Making decisions with if

`if` runs a block only when its condition is `True`. `elif` ("else if") checks another condition, and `else` catches everything left over. Python checks them from top to bottom and stops at the first match.

```example python
score = 67

if score >= 80:
    grade = "A"
elif score >= 65:
    grade = "B"
elif score >= 50:
    grade = "C"
elif score >= 40:
    grade = "D"
else:
    grade = "E"

print(f"Score {score} gets grade {grade}")
```

```output
Score 67 gets grade B
```

> **Note:** This grading scale is just an example. Every school and exam body sets its own boundaries.

## Dictionaries: values with labels

A list finds things by position. A **dictionary** finds things by a **key**. It is written with curly braces as `key: value` pairs – perfect for "student name → score".

```example python
scores = {"Achieng": 78, "Brian": 54}

scores["Kiprono"] = 91        # add a new student
scores["Brian"] = 58          # update after a re-mark

print(scores["Achieng"])
print(len(scores))

for name, score in scores.items():
    print(name, score)
```

```output
78
3
Achieng 78
Brian 58
Kiprono 91
```

`.items()` gives you each key and value together, which is exactly what we need in a loop.

## Useful built-in functions

| Function | Example | Gives |
|---|---|---|
| `sum()` | `sum([60, 70, 80])` | `210` |
| `max()` / `min()` | `max([60, 70, 80])` | `80` |
| `len()` | `len([60, 70, 80])` | `3` |
| `sorted()` | `sorted([70, 60, 80])` | `[60, 70, 80]` |

## The grades analyser

```example python
scores = {
    "Achieng": 78,
    "Brian": 58,
    "Kiprono": 91,
    "Fatuma": 66,
    "Otieno": 39,
    "Wanjiru": 84,
}

print("FORM 3 MATHS – END OF TERM")
counts = {"A": 0, "B": 0, "C": 0, "D": 0, "E": 0}

for name, score in scores.items():
    if score >= 80:
        grade = "A"
    elif score >= 65:
        grade = "B"
    elif score >= 50:
        grade = "C"
    elif score >= 40:
        grade = "D"
    else:
        grade = "E"
    counts[grade] += 1
    print(f"{name:<9}{score:>4}   {grade}")

values = list(scores.values())
average = sum(values) / len(values)
top = max(scores, key=scores.get)

print(f"Class average: {average:.1f}")
print(f"Top student: {top} ({scores[top]})")
print("Grade counts:", counts)

needs_help = [name for name, s in scores.items() if s < 50]
print("Needs extra help:", ", ".join(needs_help))
```

```output
FORM 3 MATHS – END OF TERM
Achieng    78   B
Brian      58   C
Kiprono    91   A
Fatuma     66   B
Otieno     39   E
Wanjiru    84   A
Class average: 69.3
Top student: Kiprono (91)
Grade counts: {'A': 2, 'B': 2, 'C': 1, 'D': 0, 'E': 1}
Needs extra help: Otieno
```

New ideas here:

- `counts[grade] += 1` adds 1 to the count for that grade. `x += 1` is short for `x = x + 1`.
- `max(scores, key=scores.get)` finds the **key** with the biggest value.
- The `needs_help` line is a **list comprehension** – a one-line loop that builds a list of names that pass a condition.
- `", ".join(list)` glues a list of strings into one string.

## Check yourself

```exercise
Q: Complete the decision so scores of 50 and above pass.
if score ___ 50:
    print("Pass")
___:
    print("Try again")
A: >= | else
```

```exercise
Q: Look up Wanjiru's score in the dictionary.
scores = {"Wanjiru": 84, "Brian": 58}
print(scores[___])
A: "Wanjiru" / 'Wanjiru'
H: Keys are strings, so keep the quotes.
```

## Practice task

Ask a teacher, coach or chama treasurer what numbers they track by hand (test marks, attendance, contributions). Build a small dictionary with at least six real-looking entries and adapt the analyser: print each entry, the average, the highest and lowest, and a list of entries below a target you choose.
