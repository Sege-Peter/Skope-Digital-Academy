---
title: Layout with Flexbox
module: 2
minutes: 13
---

So far our sections stack one under another. Real websites place things side by side: a logo on the left and a menu on the right, or three product cards in a row. The easiest modern tool for this is **Flexbox**.

## Turning Flexbox on

You add `display: flex;` to a **container** (the parent). Its direct children become **flex items** and line up in a row.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Flex row</title>
  <style>
    .row { display: flex; gap: 12px; background: #fde7c9; padding: 12px; }
    .box { background: #7a3e1d; color: #fff; padding: 16px; border-radius: 6px; }
  </style>
</head>
<body>
  <div class="row">
    <div class="box">Bread</div>
    <div class="box">Mandazi</div>
    <div class="box">Cakes</div>
  </div>
</body>
</html>
```

`gap` adds space between items, so you don't have to fiddle with margins.

## The most useful Flexbox properties

These go on the **container**:

| Property | Common values | What it does |
|---|---|---|
| `flex-direction` | `row`, `column` | Line items up horizontally or vertically |
| `justify-content` | `flex-start`, `center`, `space-between` | Spreads items along the main direction |
| `align-items` | `stretch`, `center`, `flex-start` | Aligns items across the other direction |
| `flex-wrap` | `nowrap`, `wrap` | Lets items move to a new line when there is no room |
| `gap` | `12px`, `1rem` | Space between items |

This one goes on an **item**:

| Property | Example | What it does |
|---|---|---|
| `flex` | `flex: 1;` | The item grows to share the free space equally |
| `flex` | `flex: 1 1 220px;` | Grow, shrink, and start from a width of 220px |

## Pattern 1: header with logo and menu

`justify-content: space-between` pushes the first item to the left and the last to the right. `align-items: center` lines them up vertically.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Header</title>
  <style>
    body { margin: 0; font-family: Arial, sans-serif; }
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #7a3e1d;
      color: #fff;
      padding: 12px 20px;
    }
    .logo { font-size: 22px; font-weight: bold; }
    nav { display: flex; gap: 16px; }
    nav a { color: #ffe0b2; text-decoration: none; }
  </style>
</head>
<body>
  <header>
    <div class="logo">Baraka Bakery</div>
    <nav>
      <a href="#menu">Menu</a>
      <a href="#about">About</a>
      <a href="#contact">Contact</a>
    </nav>
  </header>
</body>
</html>
```

## Pattern 2: a row of cards that wraps

With `flex-wrap: wrap` and `flex: 1 1 220px`, each card tries to be at least about 220px wide. On a wide laptop, three cards fit in a row; on a phone, they wrap into a single column. Resize the result window to see it.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cards</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #fffaf3; margin: 0; padding: 20px; }
    .cards { display: flex; flex-wrap: wrap; gap: 16px; }
    .card {
      flex: 1 1 220px;
      background: #fff;
      border: 1px solid #ecd9c3;
      border-radius: 8px;
      padding: 16px;
    }
    .card h3 { margin-top: 0; color: #7a3e1d; }
    .price { font-weight: bold; color: #1e7b34; }
  </style>
</head>
<body>
  <h2>Our favourites</h2>
  <div class="cards">
    <article class="card"><h3>White bread</h3><p>Fresh daily from 6 a.m.</p><p class="price">KES 65</p></article>
    <article class="card"><h3>Mandazi (5)</h3><p>Perfect with chai.</p><p class="price">KES 50</p></article>
    <article class="card"><h3>Birthday cake</h3><p>Made to order.</p><p class="price">From KES 1,500</p></article>
  </div>
</body>
</html>
```

## Pattern 3: centring something

Centring used to be famously hard. With Flexbox it takes three lines on the container:

```css
.hero {
  display: flex;
  justify-content: center;  /* centre horizontally */
  align-items: center;      /* centre vertically */
  min-height: 300px;
}
```

> **Tip:** If Flexbox seems to do nothing, check that `display: flex` is on the **parent** of the items you want to move, not on the items themselves.

> **Note:** CSS also has **Grid**, which is excellent for two-dimensional layouts (rows *and* columns at once). Flexbox is enough for most small business sites, so learn it well first.

```exercise
Q: Complete the CSS that turns a container into a flex container.
.cards { display: ___; }
A: flex
H: It is the name of the layout method.
```

```exercise
Q: Fill in the value that pushes the logo left and the menu right.
header { display: flex; justify-content: ___; }
A: space-between
H: The space goes "between" the items.
```

## Practice task

Use Flexbox on your own site: make the header show the business name on the left and the menu on the right, and turn your products or services into a row of cards that wraps. Drag the edge of your browser window narrower and wider to check that the cards wrap neatly.
