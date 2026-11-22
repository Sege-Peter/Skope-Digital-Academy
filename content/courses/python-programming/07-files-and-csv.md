---
title: Working with Files and CSV
module: 3
minutes: 20
---

So far every program forgets everything when it ends. Real programs **save** data: a shop's daily sales, a chama's contributions, a list of customers. In this lesson you learn to write and read **text files** and **CSV files** – the simple spreadsheet format that Excel, Google Sheets and many M-Pesa statement exports can use.

> **Note:** In the online editor, files are saved in a temporary in-memory folder that disappears when you close or re-run the page. That is why each example below writes a file and then reads it back in the same program. On your own computer the files stay in the folder where the script runs.

## Writing and reading a text file

`open(filename, mode)` opens a file. The mode says what you want to do:

| Mode | Meaning |
|---|---|
| `"w"` | write – creates the file, or **replaces** what was there |
| `"a"` | append – adds to the end |
| `"r"` | read (the default) |

Always open files with `with`. It closes the file automatically when the block ends, even if something goes wrong.

```example python
with open("notes.txt", "w") as f:
    f.write("Call Achieng about the logo\n")
    f.write("Buy data bundles\n")

with open("notes.txt", "a") as f:
    f.write("Send invoice to Mama Mboga shop\n")

with open("notes.txt") as f:
    for number, line in enumerate(f, start=1):
        print(number, line.strip())
```

```output
1 Call Achieng about the logo
2 Buy data bundles
3 Send invoice to Mama Mboga shop
```

`\n` is the **newline** character – it ends a line. `line.strip()` removes it (and any extra spaces) when reading.

## CSV files

CSV means **Comma-Separated Values**. Each line is a row and commas separate the columns. The first row is usually the headings:

```text
date,item,quantity,price
2026-11-02,Soda 500ml,12,70
2026-11-02,Bread,5,65
```

Python's `csv` module handles the details for you, such as commas inside quotes.

## Writing a CSV file

`csv.writer` writes lists as rows. Open the file with `newline=""` so you don't get blank lines between rows on Windows.

```example python
import csv

rows = [
    ["date", "item", "quantity", "price"],
    ["2026-11-02", "Soda 500ml", 12, 70],
    ["2026-11-02", "Bread", 5, 65],
    ["2026-11-03", "Soda 500ml", 9, 70],
    ["2026-11-03", "Milk 500ml", 14, 65],
]

with open("sales.csv", "w", newline="") as f:
    writer = csv.writer(f)
    writer.writerows(rows)

with open("sales.csv") as f:
    print(f.read(), end="")
```

```output
date,item,quantity,price
2026-11-02,Soda 500ml,12,70
2026-11-02,Bread,5,65
2026-11-03,Soda 500ml,9,70
2026-11-03,Milk 500ml,14,65
```

`end=""` stops `print()` adding an extra blank line, because the file already ends with a newline.

## Reading and analysing a CSV file

`csv.DictReader` turns each row into a dictionary using the headings as keys. Everything comes back as **text**, so convert numbers with `int()` or `float()` before doing maths.

This is a mini sales report for Kiprono's kiosk in Kericho:

```example python
import csv

with open("sales.csv", "w", newline="") as f:
    writer = csv.writer(f)
    writer.writerow(["date", "item", "quantity", "price"])
    writer.writerow(["2026-11-02", "Soda 500ml", 12, 70])
    writer.writerow(["2026-11-02", "Bread", 5, 65])
    writer.writerow(["2026-11-03", "Soda 500ml", 9, 70])
    writer.writerow(["2026-11-03", "Milk 500ml", 14, 65])

totals = {}
with open("sales.csv") as f:
    for row in csv.DictReader(f):
        money = int(row["quantity"]) * int(row["price"])
        item = row["item"]
        totals[item] = totals.get(item, 0) + money

print("SALES BY ITEM")
for item, money in totals.items():
    print(f"{item:<12} KES {money:>6,}")
print(f"{'TOTAL':<12} KES {sum(totals.values()):>6,}")
```

```output
SALES BY ITEM
Soda 500ml   KES  1,470
Bread        KES    325
Milk 500ml   KES    910
TOTAL        KES  2,705
```

`totals.get(item, 0)` returns the current total for that item, or `0` if it is not in the dictionary yet – a very common pattern for adding things up by group.

> **Tip:** Cleaning and summarising CSV files is a real freelance service. Small businesses and NGOs often have messy exports they need turned into simple reports.

## Check yourself

```exercise
Q: Open a file for appending and close it automatically.
___ open("log.txt", "___") as f:
    f.write("New entry\n")
A: with | a
```

```exercise
Q: Turn the quantity text from a CSV row into a whole number.
qty = ___(row["quantity"])
A: int
```

## Practice task

Make a CSV file of something you actually track – your spending, your phone's data bundle purchases or your chama contributions – with at least eight rows and the columns `date`, `category` and `amount`. Write it from Python, read it back with `DictReader` and print the total for each category.
