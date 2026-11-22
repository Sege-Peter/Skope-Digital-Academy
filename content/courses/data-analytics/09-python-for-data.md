---
title: Introduction to Python for Data
module: 4
minutes: 16
---

Spreadsheets are great, but repeating the same cleaning and summaries every week by hand is slow and error-prone. **Python** lets you write the steps once and run them again in a second. It is free and is one of the most popular languages for data work.

## Why analysts learn Python

- **Automation** – clean and summarise this week's sales file with one click.
- **Size** – handle files too big for a spreadsheet.
- **Power** – libraries such as *pandas* (tables), *matplotlib* (charts) and *scikit-learn* (machine learning).

In this lesson we use only Python's built-in tools so every example runs in your browser. When you are comfortable, *pandas* is the natural next step.

## The building blocks

```example python
# A list holds many values
amounts = [450, 100, 50, 300]

# Loops repeat an action for each value
total = 0
for amount in amounts:
    total = total + amount
print("Total:", total)

# Built-in functions do common jobs
print("Biggest:", max(amounts), "Count:", len(amounts))

# A dictionary links keys to values
budget = {"Food": 3000, "Transport": 1500}
print("Food budget:", budget["Food"])
```

```output
Total: 900
Biggest: 450 Count: 4
Food budget: 3000
```

## Reading a CSV file

**CSV** (comma-separated values) is the most common format for sharing data – Google Sheets and Excel can both save as CSV. Python's `csv` module reads it. Here the CSV text is stored in the program so the example runs anywhere; with a real file you would use `open("spending.csv")`.

```example python
import csv
import io

data = """date,category,amount
2026-03-01,Food,450
2026-03-01,Transport,100
2026-03-02,Airtime,50
2026-03-02,Food,300
2026-03-03,Transport,150
2026-03-03,Food,250
2026-03-04,Airtime,100
"""

totals = {}
for row in csv.DictReader(io.StringIO(data)):
    category = row["category"]
    amount = int(row["amount"])
    totals[category] = totals.get(category, 0) + amount

grand_total = sum(totals.values())
print("Category    KES  Share")
for category, amount in sorted(totals.items(), key=lambda item: item[1], reverse=True):
    share = amount / grand_total * 100
    print(f"{category:<10} {amount:>4} {share:5.1f}%")
print(f"{'Total':<10} {grand_total:>4}")
```

```output
Category    KES  Share
Food       1000  71.4%
Transport   250  17.9%
Airtime     150  10.7%
Total      1400
```

Step by step: `csv.DictReader` turns each line into a dictionary such as `{"date": "2026-03-01", "category": "Food", "amount": "450"}`. Values arrive as **text**, so we convert the amount with `int()` before adding. Then we group totals by category – the same idea as a pivot table.

## Filtering and statistics

```example python
import statistics

daily_customers = {"Mon": 34, "Tue": 28, "Wed": 41, "Thu": 30, "Fri": 52, "Sat": 67, "Sun": 23}

busy_days = [day for day, count in daily_customers.items() if count > 40]
print("Busy days:", busy_days)
print("Average customers:", statistics.mean(daily_customers.values()))
print("Median customers:", statistics.median(daily_customers.values()))
```

```output
Busy days: ['Wed', 'Fri', 'Sat']
Average customers: 39.285714285714285
Median customers: 34
```

The average has many decimals. In a report you would round it: `round(39.285714285714285, 1)` gives `39.3`.

> **Tip:** Free places to practise Python on a phone or slow computer: this site's Try it Yourself editor, Google Colab (in a browser, needs a Google account) and Kaggle's free notebooks and courses.

```exercise
Q: Complete the code to convert the text "450" into a whole number.
amount = ___("450")
A: int
```

```exercise
Q: Complete the code to find the largest value in the list sales.
biggest = ___(sales)
A: max
```

## Practice task

Take the household spending table you started in lesson 2 (or invent 8 rows). Type it as CSV text into the second example above, replacing the sample data, and run it. Does Python's summary match what you get in Google Sheets with a pivot table? If not, find out why – that habit of double-checking is what makes a trustworthy analyst.
