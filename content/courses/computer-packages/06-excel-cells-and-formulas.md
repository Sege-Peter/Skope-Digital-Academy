---
title: Excel – Cells, Formulas and SUM
module: 3
module_title: Microsoft Excel
minutes: 15
---

Excel is where numbers live: shop sales, school fees records, chama contributions, stock lists. Employers value Excel skills highly because a person who can use formulas saves the business hours of calculator work and avoids costly mistakes.

## Workbooks, sheets and cells

- A **workbook** is the Excel file (`.xlsx`).
- Each workbook has one or more **worksheets** (tabs at the bottom: *Sheet1*, *Sheet2*…).
- A sheet is a grid of **columns** (letters A, B, C…) and **rows** (numbers 1, 2, 3…).
- Each box is a **cell**, named by its column then row: **B3** is column B, row 3.
- A group of cells is a **range**, written with a colon: **B2:B6** means B2 down to B6.

## Entering data

Click a cell, type, then press **Enter** (moves down) or **Tab** (moves right). To edit a cell, double-click it or press **F2**.

| | A | B | C |
|---|---|---|---|
| **1** | Item | Quantity | Price (KES) |
| **2** | Bread | 20 | 65 |
| **3** | Milk 500 ml | 30 | 60 |
| **4** | Sugar 1 kg | 10 | 180 |

> **Tip:** Type numbers as plain numbers (`1500`, not `KES 1,500`). Add the currency and commas with formatting (**Home** › **Number** format), so Excel can still calculate with them.

## Writing a formula

Every formula starts with an equals sign `=`. Excel shows the **result** in the cell and the **formula** in the **formula bar** above the grid.

| Operation | Symbol | Example | Meaning |
|---|---|---|---|
| Add | `+` | `=B2+B3` | B2 plus B3 |
| Subtract | `-` | `=C2-B2` | C2 minus B2 |
| Multiply | `*` | `=B2*C2` | B2 times C2 |
| Divide | `/` | `=C2/B2` | C2 divided by B2 |
| Power | `^` | `=A2^2` | A2 squared |

To find the value of bread in stock, type in D2:

```text
=B2*C2
```

Excel shows **1300** (20 × 65). If you change the quantity in B2, the answer updates automatically – that is the magic of spreadsheets.

Excel follows the normal maths order (BODMAS): `=2+3*4` gives 14, while `=(2+3)*4` gives 20.

## Copying formulas with the fill handle

The small square at the bottom-right corner of a selected cell is the **fill handle**. Drag it down from D2 to D4 and Excel copies the formula, adjusting it for each row: D3 becomes `=B3*C3`, D4 becomes `=B4*C4`. This is called a **relative reference**.

Sometimes one cell must stay fixed, such as a VAT or commission rate in cell F1. Add dollar signs to lock it: `$F$1`. This is an **absolute reference**. Pressing **F4** while typing a cell reference cycles through the dollar-sign options.

## The SUM function

A **function** is a ready-made formula. The most used one is **SUM**:

```text
=SUM(D2:D4)
```

This adds every cell from D2 to D4. The quick way: click the cell under a column of numbers and press **Alt + =** (AutoSum), then **Enter**.

## Common error messages

| Error | Usually means |
|---|---|
| `#####` | Column too narrow – widen it |
| `#DIV/0!` | Dividing by zero or an empty cell |
| `#NAME?` | A misspelt function name, e.g. `=SUMM()` |
| `#VALUE!` | Text where a number was expected |

```exercise
Q: Write the formula for the total value of milk (quantity in B3, price in C3).
___
A: =B3*C3 / =C3*B3
H: Start with an equals sign and use * to multiply.
```

```exercise
Q: Complete the formula that adds cells D2 to D10.
=___(D2:D10)
A: SUM
```

## Practice task

Make a sheet listing 5 items sold at a local kiosk with *Quantity* and *Price* columns. Add a *Value* column using a multiplication formula copied with the fill handle, then use **AutoSum** to get the total stock value. Change one price and watch the totals update.
