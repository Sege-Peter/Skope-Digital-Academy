---
title: Excel – Charts and a Simple Budget
module: 3
minutes: 15
---

Numbers become clearer when people can see them. In this lesson you will turn data into **charts** and then put all your Excel skills together in a **monthly budget** – something every person and small business needs.

## Choosing the right chart

| Chart type | Best for | Example |
|---|---|---|
| Column / Bar | Comparing amounts | Sales per branch: Kisumu, Nakuru, Thika |
| Line | Change over time | Monthly sales January to June |
| Pie | Parts of one whole (few slices) | How a KES 10,000 allowance is spent |

> **Tip:** Keep pie charts to about six slices or fewer. With too many slices, a column chart is easier to read.

## Making a chart

1. Select the data, **including the headings** (e.g. A1:B5).
2. Go to **Insert** and pick a chart type, or use **Recommended Charts**.
3. Click the chart to see the chart tools. Add a clear **chart title**, and **data labels** if the exact numbers matter.
4. Drag the chart to position it; drag its corners to resize.

If you change the numbers in the sheet, the chart updates automatically.

## Building a simple monthly budget

Meet Achieng. She earns about KES 18,000 a month from a part-time job at a salon in Kisumu plus typing work. She wants to see where her money goes.

| | A | B | C | D |
|---|---|---|---|---|
| **1** | Category | Planned (KES) | Actual (KES) | Difference |
| **2** | Rent | 5000 | 5000 | |
| **3** | Food | 4500 | 5200 | |
| **4** | Transport | 2000 | 1800 | |
| **5** | Airtime and data | 1000 | 1300 | |
| **6** | Savings (chama) | 3000 | 2000 | |
| **7** | Other | 2500 | 2400 | |
| **8** | **Total** | | | |

### Step 1 – Difference

In D2 type `=B2-C2` and fill down to D7. A positive number means she spent less than planned; a negative number means she overspent.

### Step 2 – Totals

In B8 type `=SUM(B2:B7)` and copy it across to C8 and D8. Planned total: 18,000. Actual total: 17,700.

### Step 3 – A status label

In E2 type the formula below and fill down:

```text
=IF(C2>B2,"Over budget","OK")
```

Food and airtime will show *Over budget*.

### Step 4 – Income and balance

Below the table add:

| | A | B |
|---|---|---|
| **10** | Income | 18000 |
| **11** | Balance | `=B10-C8` |

The balance shows 300 – what is left after actual spending.

### Step 5 – A chart

Select A1:A7 together with C1:C7 (hold **Ctrl** to select the second range) and insert a **pie chart** of actual spending. Achieng can now see at a glance that food took a bigger slice than rent – and that her savings fell short of the plan.

## Making it look professional

- Make the heading row bold with a fill colour.
- Format money columns with thousands separators (**Home** › **Number** › Comma Style).
- **Freeze** the heading row so it stays visible when scrolling (**View** › **Freeze Panes** › **Freeze Top Row**).
- Use **conditional formatting** (**Home** tab) to colour negative differences red automatically.

> **Note:** Small businesses in Kenya often need exactly this kind of sheet – daily sales, stock, expenses and M-Pesa receipts. Once you can build it, you can offer to set it up for a fee.

```exercise
Q: Complete the formula for Achieng's food difference (Planned in B3, Actual in C3).
=___-___
A: B3 | C3
```

```exercise
Q: Which chart type best shows sales changing month by month?
A ___ chart
A: line
```

## Practice task

Build your own budget for next month with at least six categories, a *Difference* column, totals, an IF status column and a pie chart. Track your real spending for one week and fill in the *Actual* column.
