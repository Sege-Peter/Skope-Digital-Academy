---
title: Spreadsheet Bookkeeping and Monthly Summaries
module: 4
module_title: Week 4 – Tools and Earning
minutes: 15
---

A spreadsheet such as **Google Sheets** (free, works on a phone) or **Microsoft Excel** makes bookkeeping faster and reduces arithmetic mistakes. The computer adds up; you focus on recording correctly.

## Set up a transactions sheet

Create one row per transaction and these columns:

| | A | B | C | D | E | F |
|---|---|---|---|---|---|---|
| **1** | Date | Details | Ref | Category | Money in | Money out |
| **2** | 01/08 | Opening balance | – | – | 3,000 | |
| **3** | 01/08 | Sales | SB-01 | Sales | 4,200 | |
| **4** | 02/08 | Flour and oil | R-410 | Stock | | 1,800 |
| **5** | 03/08 | Rent | R-411 | Rent | | 4,000 |
| **6** | 03/08 | Sales | SB-02 | Sales | 3,900 | |

Add a column G for the running balance.

## Useful formulas

```text
G2  =E2-F2                       first balance
G3  =G2+E3-F3                    then copy this down the column
    =SUM(E2:E200)                total money in
    =SUM(F2:F200)                total money out
    =SUMIF(D2:D200,"Rent",F2:F200)    total spent on rent
    =SUMIF(D2:D200,"Sales",E2:E200)   total sales
```

With the data above, the running balance in G6 is:

```text
3,000 + 4,200 − 1,800 − 4,000 + 3,900 = 5,300
```

> **Tip:** Use a **drop-down list** (Data → Data validation) for the Category column. Typing "Rent" in one row and "rent " in another can break `SUMIF` totals.

## Monthly summary

At month end, build a short summary on a second sheet using `SUMIF`. This is what the owner actually reads.

**Mwangaza Bakery – August summary**

| Item | KES |
|---|---|
| Sales | 96,500 |
| Stock and ingredients | 41,200 |
| Rent | 8,000 |
| Wages | 15,000 |
| Transport | 3,300 |
| Utilities | 4,000 |
| **Total expenses** | **71,500** |
| **Profit before drawings** | **25,000** |
| Owner's drawings | 12,000 |
| Debtors (owed to the business) at 31 Aug | 6,400 |
| Creditors (owed by the business) at 31 Aug | 9,800 |
| Cash and M-Pesa at 31 Aug | 18,700 |

Then add **two or three sentences** of comment: "Sales up compared with July because of school orders. Transport costs high – consider buying flour in bulk."

## Good spreadsheet habits

- Keep a **backup** – Google Sheets saves online; for Excel, copy the file weekly to a flash disk or cloud storage.
- Lock or protect formula cells so they are not typed over by mistake.
- Never delete a wrong entry silently – correct it and leave a note.
- Reconcile the closing balance with the real cash and M-Pesa statement every week.

> **Warning:** Business records contain private information. Do not share the sheet link publicly, and use strong passwords on the email account that owns it.

```exercise
Q: Complete the formula that adds money in from E2 to E50.
=___(E2:E50)
A: SUM
```

```exercise
Q: Sales were KES 50,000 and total expenses were KES 38,500. Find the profit before drawings.
Profit = KES ___
A: 11500 / 11,500
```

## Practice task

Open Google Sheets (or Excel) and **build the transactions sheet** above with a running-balance column and at least ten transactions. Add a summary sheet that uses `SUMIF` to total each category, and write two sentences commenting on the month.
