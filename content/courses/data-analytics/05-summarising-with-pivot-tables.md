---
title: Summarising with Pivot Tables
module: 2
minutes: 13
---

`SUMIF` works well for one or two answers. But what if the manager asks for totals for *every* branch and *every* product at once? Writing dozens of formulas is slow. A **pivot table** builds that summary in seconds.

## What a pivot table does

A pivot table **groups** rows that share a value (such as branch) and **summarises** another column for each group (such as SUM of amount). You choose:

| Part | Question it answers | Our example |
|---|---|---|
| **Rows** | What do I want to list down the side? | branch |
| **Columns** (optional) | What do I want across the top? | product |
| **Values** | What should be calculated? | SUM of amount_kes |
| **Filters** (optional) | Which records should be included? | only one date or one region |

## Building one in Google Sheets

Using the sales data from [Spreadsheets for Analysis](spreadsheets-for-analysis):

1. Click any cell in the data.
2. Choose **Insert → Pivot table**, select "New sheet" and click **Create**.
3. In the editor on the right, next to **Rows** click **Add** → `branch`.
4. Next to **Values** click **Add** → `amount_kes` (it uses SUM by default).
5. Optional: next to **Columns** click **Add** → `product`.

In Excel the steps are similar: **Insert → PivotTable**, then drag fields into Rows, Columns and Values.

## The result

| branch | Bread | Milk | Sugar | Grand total |
|---|---|---|---|---|
| Eldoret | 520 | | 640 | 1160 |
| Kisumu | 650 | 900 | 800 | 2350 |
| Nakuru | 780 | 1200 | 960 | 2940 |
| **Grand total** | **1950** | **2100** | **2400** | **6450** |

In seconds we can see that Nakuru sold the most, Sugar brought in the most money, and Eldoret sold no milk that day – a question worth asking the branch manager.

> **Tip:** The grand total of a pivot table should always equal the `SUM` of the original column (here 6450). If it doesn't, some rows are missing or contain text instead of numbers.

## Other summaries

Values don't have to be SUM. You can switch to:

- **COUNTA** – how many sales (transactions) per branch
- **AVERAGE** – average sale per branch
- **MAX / MIN** – biggest or smallest sale

## How a pivot table thinks

Behind the scenes, a pivot table loops through the rows and keeps a running total for each group. This Python code does exactly that for the "Rows = branch, Values = SUM of amount" pivot:

```example python
sales = [
    ("Kisumu", "Bread", 650), ("Nakuru", "Milk", 1200),
    ("Kisumu", "Sugar", 800), ("Eldoret", "Bread", 520),
    ("Nakuru", "Bread", 780), ("Kisumu", "Milk", 900),
    ("Eldoret", "Sugar", 640), ("Nakuru", "Sugar", 960),
]

totals = {}
for branch, product, amount in sales:
    totals[branch] = totals.get(branch, 0) + amount

for branch in sorted(totals):
    print(branch, totals[branch])
print("Grand total", sum(totals.values()))
```

```output
Eldoret 1160
Kisumu 2350
Nakuru 2940
Grand total 6450
```

```exercise
Q: In a pivot table showing total sales per branch, branch goes in ___ and amount_kes goes in ___.
A: rows | values
```

```exercise
Q: From the pivot table above, what is the total amount for Milk across all branches?
___
A: 2100
```

## Practice task

Build the pivot table above in Google Sheets. Then change the Values summary from SUM to **COUNTA** and write down how many sales each branch made. Finally, move `product` from Columns to Rows and `branch` to Columns – notice how the same data can be viewed in different ways. Write one sentence of advice you would give the Eldoret branch manager.
