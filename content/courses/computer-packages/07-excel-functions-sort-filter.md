---
title: Excel – AVERAGE, IF, Lookups, Sorting and Filtering
module: 3
minutes: 16
---

With SUM you can already total a column. Now we add the functions that make Excel truly useful at work: **AVERAGE**, **MIN**, **MAX**, **COUNT**, **IF** and the lookup functions, plus sorting and filtering.

We will use this marks sheet from a tuition centre in Nakuru:

| | A | B | C |
|---|---|---|---|
| **1** | Student | Adm No | Maths |
| **2** | Achieng | 101 | 72 |
| **3** | Brian | 102 | 45 |
| **4** | Fatuma | 103 | 88 |
| **5** | Kiprono | 104 | 39 |
| **6** | Wanjiru | 105 | 61 |

## Quick statistics

| Formula | Result | Meaning |
|---|---|---|
| `=AVERAGE(C2:C6)` | 61 | The mean mark |
| `=MAX(C2:C6)` | 88 | The highest mark |
| `=MIN(C2:C6)` | 39 | The lowest mark |
| `=COUNT(C2:C6)` | 5 | How many cells contain numbers |
| `=COUNTA(A2:A6)` | 5 | How many cells are not empty (text too) |

## The IF function

**IF** makes a decision. Its pattern is:

```text
=IF(test, value_if_true, value_if_false)
```

To label each student *Pass* (50 and above) or *Fail*, type in D2 and fill down:

```text
=IF(C2>=50,"Pass","Fail")
```

Achieng gets *Pass*, Brian gets *Fail*. Note that text answers go inside double quotes.

Comparison symbols you can use in the test:

| Symbol | Meaning |
|---|---|
| `=` | equal to |
| `>` / `<` | greater than / less than |
| `>=` / `<=` | greater than or equal to / less than or equal to |
| `<>` | not equal to |

> **Tip:** Some Excel installations use a semicolon `;` instead of a comma `,` between arguments, depending on the computer's regional settings. If Excel rejects your formula, try semicolons.

## Looking things up: VLOOKUP and XLOOKUP

A parent calls asking for the Maths mark of admission number 103. With 500 students, scrolling is slow. A lookup function finds it instantly.

**VLOOKUP** works in every Excel version and in Google Sheets:

```text
=VLOOKUP(103, B2:C6, 2, FALSE)
```

- `103` – what to look for.
- `B2:C6` – the table; the value must be in its **first** column.
- `2` – return the value from the 2nd column of that table (Maths).
- `FALSE` – exact match only. Almost always use FALSE.

Result: **88**.

**XLOOKUP** is newer and easier. It is available in Microsoft 365, Excel 2021 and later, and Google Sheets:

```text
=XLOOKUP(103, B2:B6, C2:C6, "Not found")
```

You give the column to search, the column to return and an optional message when nothing matches. Unlike VLOOKUP, the return column can be on the left of the search column.

> **Note:** If a computer has an older Excel (2016 or 2019), XLOOKUP shows `#NAME?`. Use VLOOKUP there.

## Sorting

Click any cell in your table, then go to the **Data** tab:

- **A → Z** / **Smallest to Largest** sorts ascending.
- **Z → A** / **Largest to Smallest** sorts descending.
- **Sort** (the big button) lets you sort by several columns, e.g. by class and then by marks.

> **Warning:** Never select just one column and sort it on its own. The names would move but the marks would stay, mixing up everyone's results. Click inside the table and let Excel select all of it.

## Filtering

**Data** › **Filter** (or **Ctrl + Shift + L**) adds drop-down arrows to the headings. Use them to show only some rows – for example only students who *Failed*, or only sales from *Kisumu* branch. The hidden rows are not deleted; clear the filter to see them again.

```exercise
Q: Complete the formula that shows "Pass" when C2 is 50 or more, otherwise "Fail".
=IF(C2___50,"Pass","Fail")
A: >=
```

```exercise
Q: Complete the formulas.
Average of C2 to C6: =___(C2:C6)
Highest value in C2 to C6: =___(C2:C6)
A: AVERAGE | MAX
```

## Practice task

Type the marks sheet above, add two more students, then:

1. Add a *Result* column with IF.
2. Add the class average, highest and lowest marks below the table.
3. Use VLOOKUP (or XLOOKUP) to find one student's mark by admission number.
4. Sort by Maths from highest to lowest, then filter to show only *Pass*.
