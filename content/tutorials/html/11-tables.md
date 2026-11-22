---
title: HTML Tables
module: 3
minutes: 12
---

Tables show data in rows and columns: timetables, price lists, exam results, league standings. HTML tables are built from a small set of elements that fit together like a grid.

## A basic table

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Basic Table</title>
</head>
<body>
  <table>
    <tr>
      <th>Town</th>
      <th>County</th>
    </tr>
    <tr>
      <td>Thika</td>
      <td>Kiambu</td>
    </tr>
    <tr>
      <td>Malindi</td>
      <td>Kilifi</td>
    </tr>
  </table>
</body>
</html>
```

| Element | Meaning |
|---|---|
| `<table>` | Wraps the whole table |
| `<tr>` | A table row |
| `<th>` | A header cell (bold and centred by default) |
| `<td>` | A data cell |

Each row contains cells, and the browser lines cells up into columns automatically.

## Adding borders and spacing

Tables have no borders by default. Add them with CSS. The `border-collapse: collapse;` rule merges neighbouring borders into single lines.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Styled Table</title>
  <style>
    table { border-collapse: collapse; }
    th, td { border: 1px solid #999; padding: 6px 12px; text-align: left; }
    th { background-color: #e8f5e9; }
  </style>
</head>
<body>
  <table>
    <tr>
      <th>Item</th>
      <th>Price (KSh)</th>
    </tr>
    <tr>
      <td>Mandazi</td>
      <td>20</td>
    </tr>
    <tr>
      <td>Chai</td>
      <td>30</td>
    </tr>
    <tr>
      <td>Samosa</td>
      <td>50</td>
    </tr>
  </table>
</body>
</html>
```

> **Note:** The old `border`, `cellpadding` and `bgcolor` attributes on tables are obsolete. Use CSS instead.

## Caption, head, body and foot

Bigger tables benefit from extra structure:

- `<caption>` gives the table a title. It must be the first thing inside `<table>`.
- `<thead>` groups the header rows.
- `<tbody>` groups the main data rows.
- `<tfoot>` groups summary rows such as totals.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Table Sections</title>
  <style>
    table { border-collapse: collapse; }
    th, td { border: 1px solid #999; padding: 6px 12px; }
    caption { font-weight: bold; margin-bottom: 6px; }
    tfoot { background-color: #fff3cd; }
  </style>
</head>
<body>
  <table>
    <caption>Term 1 fees, Lakeview Secondary School</caption>
    <thead>
      <tr>
        <th scope="col">Item</th>
        <th scope="col">Amount (KSh)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Tuition</td>
        <td>15,000</td>
      </tr>
      <tr>
        <td>Boarding</td>
        <td>12,000</td>
      </tr>
    </tbody>
    <tfoot>
      <tr>
        <th scope="row">Total</th>
        <td>27,000</td>
      </tr>
    </tfoot>
  </table>
</body>
</html>
```

The `scope` attribute tells assistive technology whether a header cell labels a column (`col`) or a row (`row`). It is a small addition that makes tables much easier to understand with a screen reader.

## Spanning rows and columns

A cell can stretch across several columns with `colspan`, or several rows with `rowspan`.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Colspan and Rowspan</title>
  <style>
    table { border-collapse: collapse; }
    th, td { border: 1px solid #999; padding: 6px 12px; text-align: center; }
  </style>
</head>
<body>
  <table>
    <tr>
      <th>Day</th>
      <th colspan="2">Lessons</th>
    </tr>
    <tr>
      <td rowspan="2">Monday</td>
      <td>Maths</td>
      <td>English</td>
    </tr>
    <tr>
      <td>Kiswahili</td>
      <td>Science</td>
    </tr>
    <tr>
      <td>Tuesday</td>
      <td colspan="2">Sports day</td>
    </tr>
  </table>
</body>
</html>
```

When a cell spans, the rows it covers need fewer cells. In the example, the second Monday row has only two `<td>` elements because the "Monday" cell already fills the first column.

> **Tip:** Sketch complicated tables on paper first, as a grid. Count the columns in each row, remembering that a `colspan="2"` cell counts as two.

## Tables are for data, not layout

Years ago, people built whole page layouts out of tables. Do not do this. Layout tables confuse screen readers, are hard to maintain and do not adapt to phones. Use CSS (Flexbox and Grid) for layout, and keep tables for real tabular data.

## Wide tables on phones

A table with many columns can be wider than a phone screen. A simple fix is to wrap it in a `<div>` that scrolls sideways:

```html
<div style="overflow-x: auto;">
  <table>...</table>
</div>
```

```exercise
Q: Complete the table row with a header cell and a data cell.
<tr>
  <___>County</th>
  <___>Nakuru</td>
</tr>
A: th | td
H: Header cells and data cells have different two-letter names.
```

```exercise
Q: Make the cell stretch across three columns.
<td ___="3">Closed for Madaraka Day</td>
A: colspan
H: It is "column" shortened, plus "span".
```
