---
title: Spreadsheets for Analysis
module: 2
minutes: 15
---

Spreadsheets (Microsoft Excel or the free Google Sheets) are the most widely used data tool in the world. In this lesson you will use the essentials: sorting, filtering and the formulas analysts type every day.

## Our sample data

A small bakery-and-grocery chain recorded one day's sales. Type this into a new Google Sheet, starting in cell A1:

| | A | B | C | D |
|---|---|---|---|---|
| **1** | branch | product | qty | amount_kes |
| **2** | Kisumu | Bread | 10 | 650 |
| **3** | Nakuru | Milk | 20 | 1200 |
| **4** | Kisumu | Sugar | 5 | 800 |
| **5** | Eldoret | Bread | 8 | 520 |
| **6** | Nakuru | Bread | 12 | 780 |
| **7** | Kisumu | Milk | 15 | 900 |
| **8** | Eldoret | Sugar | 4 | 640 |
| **9** | Nakuru | Sugar | 6 | 960 |

> **Tip:** Make row 1 bold and use **View → Freeze → 1 row** so the headings stay visible as you scroll.

## Sorting

Sorting puts rows in order. Select the data, then **Data → Sort range**, tick "Data has header row" and choose a column.

- Sort `amount_kes` **Z → A** (largest first) to see the biggest sales at the top: Nakuru Milk (1200) comes first.
- Sort `branch` **A → Z** to group branches together.

> **Warning:** Always select *all* columns before sorting. Sorting just one column mixes up your rows – amounts end up next to the wrong branch.

## Filtering

A filter hides rows you don't need right now. Click **Data → Create a filter**, then use the small icon on a heading. For example, filter `branch` to show only Kisumu. Nothing is deleted – remove the filter and every row returns.

## Essential formulas

Every formula starts with `=`. A **range** like `D2:D9` means "cells D2 to D9".

| Formula | What it does | Result on our data |
|---|---|---|
| `=SUM(D2:D9)` | Adds the numbers | 6450 |
| `=AVERAGE(D2:D9)` | Mean of the numbers | 806.25 |
| `=MAX(D2:D9)` | Largest value | 1200 |
| `=MIN(D2:D9)` | Smallest value | 520 |
| `=COUNT(D2:D9)` | How many cells contain numbers | 8 |
| `=COUNTIF(A2:A9,"Kisumu")` | Counts cells matching a condition | 3 |
| `=SUMIF(A2:A9,"Nakuru",D2:D9)` | Adds amounts where the condition matches | 2940 |
| `=COUNTIF(D2:D9,">800")` | Counts amounts above 800 | 3 |

Check the last one yourself: the amounts above 800 are 1200, 900 and 960.

## Cell references and copying formulas

If you type `=C2*65` in E2 and drag it down, the spreadsheet changes it to `=C3*65`, `=C4*65` and so on. This is a **relative reference**. To keep a reference fixed when copying, add dollar signs: `$G$1` is an **absolute reference**. For example, put a price list or a tax rate in one cell and refer to it with `$`.

## Checking your work in Python

Analysts often double-check spreadsheet results in a second tool:

```example python
branches = ["Kisumu", "Nakuru", "Kisumu", "Eldoret", "Nakuru", "Kisumu", "Eldoret", "Nakuru"]
amounts = [650, 1200, 800, 520, 780, 900, 640, 960]

print("SUM:", sum(amounts))
print("AVERAGE:", sum(amounts) / len(amounts))
print("COUNTIF Kisumu:", branches.count("Kisumu"))

nakuru_total = 0
for branch, amount in zip(branches, amounts):
    if branch == "Nakuru":
        nakuru_total += amount
print("SUMIF Nakuru:", nakuru_total)
```

```output
SUM: 6450
AVERAGE: 806.25
COUNTIF Kisumu: 3
SUMIF Nakuru: 2940
```

```exercise
Q: Complete the formula that adds the amounts in D2 to D9.
=___(D2:D9)
A: SUM
```

```exercise
Q: Complete the formula that counts how many sales happened in Eldoret, and write its result.
=___(A2:A9,"Eldoret") gives ___
A: COUNTIF | 2
```

```exercise
Q: Using the sample data, what does =SUMIF(A2:A9,"Kisumu",D2:D9) return?
___
A: 2350
H: Add the Kisumu amounts: 650, 800 and 900.
```

## Practice task

Enter the sample data in Google Sheets. Add a column E called `price_each` with the formula `=D2/C2` and drag it down. Then use formulas to find: total quantity sold, the average sale amount, the number of Bread sales, and the total amount for Eldoret. (Answers to check yourself: 80, 806.25, 3 and 1160.)
