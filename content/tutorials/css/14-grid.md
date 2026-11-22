---
title: CSS Grid
module: 3
minutes: 16
---

**CSS Grid** is a two-dimensional layout system. Flexbox arranges items along one line at a time; Grid arranges them in **rows and columns at the same time**. It is ideal for page layouts, photo galleries and dashboards.

Like Flexbox, Grid has a container and items. You create a grid container with `display: grid`, and its direct children become grid items.

## Defining columns

`grid-template-columns` lists the width of each column. The number of values is the number of columns.

```css
.grid {
  display: grid;
  grid-template-columns: 200px 200px 200px;  /* three fixed columns */
}
```

## The fr unit

Fixed widths do not adapt to the screen. Grid adds a special unit, `fr` (fraction), which means "a share of the free space".

- `1fr 1fr 1fr` makes three equal columns.
- `2fr 1fr` makes the first column twice as wide as the second.
- `250px 1fr` gives a fixed sidebar and a main area that takes the rest.

## repeat() and gap

`repeat(4, 1fr)` is a short way to write `1fr 1fr 1fr 1fr`. `gap` sets the space between rows and columns (or use `row-gap` and `column-gap` separately).

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; }
  .grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
  }
  .grid div {
    background-color: #ffcc80;
    padding: 24px;
    text-align: center;
    border-radius: 6px;
  }
</style>
</head>
<body>
  <div class="grid">
    <div>Mombasa</div>
    <div>Nairobi</div>
    <div>Kisumu</div>
    <div>Nakuru</div>
    <div>Eldoret</div>
    <div>Thika</div>
  </div>
</body>
</html>
```

Six items fill a 3-column grid, so the browser automatically creates two rows.

## Responsive grids without media queries

This one line is one of the most useful in all of CSS:

```css
grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
```

It means: "fit as many columns as possible, each at least 180px wide, and stretch them to share any leftover space." On a phone you get one column; on a laptop you might get five. No media queries needed.

## Placing items across tracks

Grid lines are numbered starting from 1 at the left (and top) edge. An item can span several columns or rows:

- `grid-column: 1 / 3;` runs from line 1 to line 3, covering two columns.
- `grid-column: span 2;` covers two columns from wherever the item lands.
- `grid-column: 1 / -1;` stretches across the full width (`-1` is the last line).
- `grid-row` works the same way for rows.

## Named areas

`grid-template-areas` lets you draw the layout with words. Each string is a row, and each word is a cell. Then you assign each item to an area with `grid-area`.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { margin: 0; font-family: Arial, sans-serif; }
  .page {
    display: grid;
    grid-template-columns: 200px 1fr;
    grid-template-rows: auto 1fr auto;
    grid-template-areas:
      "header header"
      "sidebar main"
      "footer footer";
    min-height: 100vh;
    gap: 8px;
  }
  header  { grid-area: header;  background-color: #37474f; color: white; }
  aside   { grid-area: sidebar; background-color: #cfd8dc; }
  main    { grid-area: main;    background-color: #eceff1; }
  footer  { grid-area: footer;  background-color: #37474f; color: white; }
  header, aside, main, footer { padding: 16px; }

  .gallery {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 8px;
  }
  .gallery div {
    background-color: #80cbc4;
    height: 80px;
    border-radius: 6px;
  }
  .gallery .wide { grid-column: span 2; background-color: #00897b; }
</style>
</head>
<body>
  <div class="page">
    <header>Skope Digital Academy</header>
    <aside>Lessons menu</aside>
    <main>
      <h2>Main content</h2>
      <div class="gallery">
        <div class="wide"></div><div></div><div></div>
        <div></div><div></div><div></div>
      </div>
    </main>
    <footer>Made in Kenya</footer>
  </div>
</body>
</html>
```

The page layout is defined in one readable block. Inside the main area, a gallery uses `auto-fit` and one dark tile spans two columns.

## Aligning items in grid cells

Grid uses the same alignment ideas as Flexbox:

| Property | Set on | Aligns |
|---|---|---|
| `justify-items` | container | all items horizontally inside their cells |
| `align-items` | container | all items vertically inside their cells |
| `place-items` | container | both at once, e.g. `place-items: center` |
| `justify-self` / `align-self` | item | one item only |

## Grid or Flexbox?

| Use Grid when | Use Flexbox when |
|---|---|
| you need rows **and** columns to line up | items flow along a single row or column |
| you are laying out the whole page | you are laying out a nav bar, toolbar or button group |
| you want to place items in specific cells | item sizes should depend on their content |

They work well together. A Grid page layout with Flexbox inside each component is very common.

```exercise
Q: Create four equal columns using repeat and the fraction unit.
.grid { display: grid; grid-template-columns: repeat(4, 1___); }
A: fr
```

```exercise
Q: Make the header stretch from the first to the last grid line.
header { grid-column: 1 / ___; }
A: -1
H: Negative numbers count from the end.
```
