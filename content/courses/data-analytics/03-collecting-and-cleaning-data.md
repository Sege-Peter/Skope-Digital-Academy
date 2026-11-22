---
title: Collecting and Cleaning Data
module: 2
module_title: Week 2 – Preparing and Analysing Data
minutes: 14
---

Analysts often say they spend most of their time *cleaning* data, not analysing it. Messy data gives wrong answers, so cleaning is where careful analysts earn their trust.

## Ways to collect data

| Method | Good for | Tools |
|---|---|---|
| **Forms and surveys** | Opinions, customer details, feedback | Google Forms, KoboToolbox (popular with NGOs) |
| **Business records** | Sales, stock, expenses | Till/POS exports, M-Pesa statements, receipt books |
| **Observation** | Counting things | Tally sheet of customers per hour at a kiosk |
| **Existing datasets** | Background and comparison | KNBS, county reports, World Bank Open Data |

> **Note:** If you collect personal data (names, phone numbers), get people's consent, store it securely and only collect what you need. Kenya's Data Protection Act (2019) applies – see odpc.go.ke.

## Common data problems

Here is a messy export from a small shop's records:

| date | customer | town | amount |
|---|---|---|---|
| 02/03/2026 | Fatuma Ali | Mombasa | 1,200 |
| 2026-03-02 | fatuma ali | mombasa  | 1200 |
| 03/03/2026 | Kiprono | Eldoret | |
| 04/03/2026 | Grace | Kisumu | 15000 |
| 04/03/2026 | Brian | Nairobi | KES 850 |

Problems you can spot:

1. **Duplicates** – rows 1 and 2 are the same sale typed twice.
2. **Inconsistent formats** – two date formats; "1,200" vs "1200" vs "KES 850".
3. **Inconsistent text** – "Mombasa" vs "mombasa " (lowercase and an extra space).
4. **Missing values** – Kiprono's amount is blank.
5. **Possible errors (outliers)** – Grace's 15000 might really be 1500. Check the receipt.

## A cleaning checklist

```text
[ ] Keep an untouched copy of the raw data.
[ ] Remove exact duplicate rows.
[ ] Trim extra spaces and fix capitalisation.
[ ] Use one date format (e.g. YYYY-MM-DD).
[ ] Store amounts as plain numbers (no "KES", no commas as text).
[ ] Decide how to handle blanks: find the true value, mark as missing, or exclude.
[ ] Investigate extreme values before deleting or changing them.
[ ] Write down every change you made.
```

## Spreadsheet cleaning tools

| Task | Google Sheets / Excel |
|---|---|
| Remove extra spaces | `=TRIM(A2)` |
| Fix capitals | `=PROPER(A2)` turns "fatuma ali" into "Fatuma Ali" |
| Remove duplicates | Data → Data cleanup → Remove duplicates (Sheets); Data → Remove Duplicates (Excel) |
| Find and replace | Ctrl + H – e.g. replace "KES " with nothing |
| Find blanks | Filter the column and choose "(Blanks)" |

## Cleaning with Python

The same ideas in code: strip spaces, fix capitals, drop empty values and find the unique towns.

```example python
raw_towns = ["  kisumu", "Nairobi ", "NAIROBI", "nakuru", "Kisumu", "", "Nakuru  "]

cleaned = []
for town in raw_towns:
    town = town.strip().title()
    if town:
        cleaned.append(town)

print(cleaned)
print("Unique towns:", sorted(set(cleaned)))
print("Removed", len(raw_towns) - len(cleaned), "empty value(s)")
```

```output
['Kisumu', 'Nairobi', 'Nairobi', 'Nakuru', 'Kisumu', 'Nakuru']
Unique towns: ['Kisumu', 'Nairobi', 'Nakuru']
Removed 1 empty value(s)
```

```exercise
Q: Write the spreadsheet formula that removes extra spaces from cell B2.
=___(B2)
A: TRIM
```

```exercise
Q: Fill in the blanks: the same record typed twice is a ___; an extreme value that may be an error is an ___.
A: duplicate | outlier
```

## Practice task

Create a Google Form with 4 questions (name, town, favourite snack, how much you spend on snacks per week in KES). Send it to 8–10 friends. Open the responses in Google Sheets and clean them using the checklist: fix town spellings, remove "KES" and "bob" from amounts, and note any blanks. Write down every change you made.
