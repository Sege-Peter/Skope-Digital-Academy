---
title: Data Entry and Spreadsheets
module: 3
module_title: Data, Research and Tools
minutes: 13
---

Data entry means typing or copying information into a system – usually a spreadsheet – so it can be searched, counted and used. It sounds simple, but clients pay for **accuracy**, **consistency** and **speed**, in that order. A fast VA who makes mistakes costs the client money.

## Typical data entry jobs

- Typing receipts and invoices into a sheet
- Building a customer or student contact list
- Copying product details from a supplier catalogue into an online shop list
- Recording survey or form responses
- Cleaning messy lists (removing duplicates, fixing spelling)

## Set up the sheet properly

Before typing anything, design the sheet:

| Date | Receipt No. | Supplier | Item | Quantity | Unit Price (KES) | Total (KES) |
|---|---|---|---|---|---|---|
| 2026-03-03 | 0451 | Mwangi Wholesalers | Cement 50kg | 10 | 850 | 8,500 |
| 2026-03-04 | 0452 | Lake Hardware | Nails 3" (kg) | 5 | 260 | 1,300 |

*(Example data.)*

Rules for a clean sheet:

1. **One row = one record** (one receipt line, one customer).
2. **One column = one type of information.** Don't mix phone numbers and names in one cell.
3. **Header row** at the top, frozen so it stays visible.
4. **Consistent formats** – all dates the same way, all phone numbers the same way (e.g. 0712 345 678).
5. **No empty rows** in the middle of the data.

## Useful formulas

Let the spreadsheet do the maths – never type totals you could calculate.

| Formula | What it does |
|---|---|
| `=E2*F2` | Multiplies quantity by unit price |
| `=SUM(G2:G100)` | Adds all totals |
| `=COUNTIF(C2:C100,"Lake Hardware")` | Counts rows for one supplier |
| `=SUMIF(C2:C100,"Lake Hardware",G2:G100)` | Adds totals for one supplier |
| `=TRIM(A2)` | Removes extra spaces from text |
| `=UPPER(A2)` / `=PROPER(A2)` | Changes text to CAPITALS / Title Case |

> **Tip:** Type a formula once, then drag the small square at the bottom-right of the cell down to copy it to every row.

## Checking your accuracy

- **Spot check:** after every 20 rows, compare 2–3 rows with the original documents.
- **Totals check:** if the receipts have a printed grand total, compare it with your `=SUM`.
- **Duplicates:** in Google Sheets, use Data → Data cleanup → Remove duplicates (on a copy first!).
- **Data validation:** use Data → Data validation to create drop-down lists, so suppliers are always spelled the same way.

## Speed comes with practice

Improve your typing with free online typing practice for 10–15 minutes a day. Learn shortcuts:

| Shortcut (Windows) | Action |
|---|---|
| Ctrl + C / Ctrl + V | Copy / paste |
| Ctrl + Shift + V | Paste without formatting |
| Ctrl + Z | Undo |
| Tab / Enter | Move right / move down |
| Ctrl + F | Find |

> **Warning:** Data entry "jobs" that ask you to pay for software, a "starter kit" or "registration" before sending work are a well-known scam. Real data entry clients pay you for the work.

```exercise
Q: Complete the formula that multiplies quantity (E2) by unit price (F2).
=E2___F2
A: *
H: The multiplication symbol in spreadsheets.
```

```exercise
Q: Fill in the clean-sheet rules.
One row = one ___. One column = one type of ___.
A: record / entry / item | information / data
H: Think about what each row and column holds.
```

## Practice task

Collect **five real receipts** (shop, supermarket, M-Pesa messages – hide personal details). In a new Google Sheet, set up the columns from the example, freeze the header, type in every item, and use formulas for each row total and the grand total. Check that your `=SUM` matches the receipts.
