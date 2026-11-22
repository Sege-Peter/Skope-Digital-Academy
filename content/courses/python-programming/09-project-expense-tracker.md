---
title: "Final Project: Expense Tracker"
module: 4
minutes: 25
---

Time to bring everything together. You will build an **expense tracker** for Achieng, a student in Nairobi who wants to see where her monthly KES 6,000 allowance goes. The program records expenses, saves them to a CSV file, loads them back, and prints a report with totals per category and a budget warning.

## Plan before you code

Professional developers plan first. Our tracker needs to:

1. Add an expense (date, category, description, amount) – and refuse bad amounts
2. Save all expenses to `expenses.csv`
3. Load them back from the file
4. Summarise spending by category and compare it with the budget

Each job becomes one function. Here is how each part maps to what you learned:

| Part | Skill | Lesson |
|---|---|---|
| List of expense dictionaries | lists, dictionaries | [grades analyser](project-grades-analyser) |
| `add_expense()` with a check | functions, `raise` | [errors and clean data](errors-and-clean-data) |
| `save()` / `load()` | `csv` module | [files and CSV](files-and-csv) |
| Report with totals | loops, f-strings | [shopping list](project-shopping-list) |

## Step 1: adding and saving

```example python
import csv

expenses = []

def add_expense(date, category, description, amount):
    if amount <= 0:
        raise ValueError("Amount must be more than zero")
    expenses.append({"date": date, "category": category.title(),
                     "description": description, "amount": amount})

def save(filename):
    with open(filename, "w", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=["date", "category", "description", "amount"])
        writer.writeheader()
        writer.writerows(expenses)

add_expense("2026-11-01", "transport", "Matatu to campus", 1200)
add_expense("2026-11-02", "food", "Lunch for the week", 1500)
add_expense("2026-11-03", "data", "Monthly bundle", 1000)
save("expenses.csv")

with open("expenses.csv") as f:
    print(f.read(), end="")
```

```output
date,category,description,amount
2026-11-01,Transport,Matatu to campus,1200
2026-11-02,Food,Lunch for the week,1500
2026-11-03,Data,Monthly bundle,1000
```

`csv.DictWriter` writes dictionaries directly – you tell it the column order with `fieldnames`. `category.title()` makes "transport" and "TRANSPORT" both become "Transport", so they group together later.

## Step 2: the full tracker

The finished program adds loading, a report and a budget check. Read it slowly – there is nothing here you have not seen before.

```example python
import csv

BUDGET = 6000
expenses = []

def add_expense(date, category, description, amount):
    if amount <= 0:
        raise ValueError("Amount must be more than zero")
    expenses.append({"date": date, "category": category.title(),
                     "description": description, "amount": amount})

def save(filename):
    with open(filename, "w", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=["date", "category", "description", "amount"])
        writer.writeheader()
        writer.writerows(expenses)

def load(filename):
    with open(filename) as f:
        rows = list(csv.DictReader(f))
    for row in rows:
        row["amount"] = float(row["amount"])
    return rows

def report(rows, budget):
    by_category = {}
    for row in rows:
        cat = row["category"]
        by_category[cat] = by_category.get(cat, 0) + row["amount"]

    total = sum(by_category.values())
    print("ACHIENG'S SPENDING – NOVEMBER")
    for cat, amount in sorted(by_category.items(), key=lambda pair: pair[1], reverse=True):
        share = amount / total
        print(f"{cat:<10} KES {amount:>8,.2f}  {share:>4.0%}")
    print(f"{'Total':<10} KES {total:>8,.2f}")

    left = budget - total
    if left < 0:
        print(f"Over budget by KES {-left:,.2f}!")
    elif left < budget * 0.1:
        print(f"Careful: only KES {left:,.2f} left.")
    else:
        print(f"Well done: KES {left:,.2f} left.")

add_expense("2026-11-01", "transport", "Matatu to campus", 1200)
add_expense("2026-11-02", "food", "Lunch for the week", 1500)
add_expense("2026-11-03", "data", "Monthly bundle", 1000)
add_expense("2026-11-08", "food", "Groceries", 950)
add_expense("2026-11-10", "personal", "Haircut", 300)
add_expense("2026-11-15", "transport", "Trip home to Nakuru", 800)

try:
    add_expense("2026-11-16", "food", "Typo", -50)
except ValueError as error:
    print("Not added:", error)

save("expenses.csv")
report(load("expenses.csv"), BUDGET)
```

```output
Not added: Amount must be more than zero
ACHIENG'S SPENDING – NOVEMBER
Food       KES 2,450.00   43%
Transport  KES 2,000.00   35%
Data       KES 1,000.00   17%
Personal   KES   300.00    5%
Total      KES 5,750.00
Careful: only KES 250.00 left.
```

Two small new things:

- `BUDGET` is in capitals – a convention that says "this value is a setting; don't change it inside the program".
- `key=lambda pair: pair[1]` sorts the categories by their amount. A `lambda` is a tiny one-line function. `reverse=True` puts the biggest first.

> **Note:** The percentages are rounded, so they may not add up to exactly 100%.

## Check yourself

```exercise
Q: Write the CSV heading row before the expense rows.
writer = csv.DictWriter(f, fieldnames=["date", "amount"])
writer.___()
A: writeheader
```

```exercise
Q: Add the amount to the running total for its category.
by_category[cat] = by_category.___(cat, ___) + row["amount"]
A: get | 0
```

## Practice task

Make the tracker yours. Use your own budget and at least ten real expenses from the last two weeks (from memory or your M-Pesa messages). Then add **one** improvement: for example, a `biggest_expense()` function, a daily average, or a warning for any single expense above KES 1,000. Save your code – it is a strong portfolio project to show on GitHub or in a job interview.
