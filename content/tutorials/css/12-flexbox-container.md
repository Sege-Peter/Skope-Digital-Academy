---
title: CSS Flexbox Container
module: 3
minutes: 15
---

**Flexbox** is a layout system for arranging items in a row or a column. It makes jobs that used to be painful, like centring something vertically or spacing out menu links evenly, take just a few lines.

Flexbox works with two roles:

- The **flex container** is the parent. You create it with `display: flex`.
- The **flex items** are its direct children. They are laid out by the container.

This lesson covers the properties you set on the container. The next lesson, [Flexbox Items](flexbox-items), covers properties set on the children.

## Turning on Flexbox

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  .row {
    display: flex;
    background-color: #eceff1;
    padding: 10px;
  }
  .row div {
    background-color: #00897b;
    color: white;
    padding: 20px;
    margin: 5px;
    font-family: Arial, sans-serif;
  }
</style>
</head>
<body>
  <div class="row">
    <div>Nairobi</div>
    <div>Kampala</div>
    <div>Kigali</div>
  </div>
</body>
</html>
```

The three `<div>`s are block elements, yet they now sit side by side. That is the container at work.

## The two axes

Flexbox thinks in two directions:

- The **main axis** runs in the direction items are placed. With the default `flex-direction: row`, it is horizontal.
- The **cross axis** runs at right angles to it. With a row, it is vertical.

Remembering this makes the alignment properties easy: `justify-content` works along the main axis, and `align-items` works along the cross axis.

## flex-direction

| Value | Items flow |
|---|---|
| `row` (default) | left to right |
| `row-reverse` | right to left |
| `column` | top to bottom |
| `column-reverse` | bottom to top |

When you change to `column`, the axes swap: `justify-content` now works vertically and `align-items` horizontally.

## justify-content: spacing on the main axis

| Value | Effect |
|---|---|
| `flex-start` | items packed at the start (default) |
| `flex-end` | items packed at the end |
| `center` | items in the middle |
| `space-between` | first and last at the edges, equal gaps between |
| `space-around` | equal space around each item |
| `space-evenly` | exactly equal gaps everywhere, including the edges |

## align-items: alignment on the cross axis

| Value | Effect |
|---|---|
| `stretch` | items stretch to fill the container's height (default) |
| `flex-start` | items at the top |
| `flex-end` | items at the bottom |
| `center` | items centred vertically |
| `baseline` | the first lines of text line up |

## gap and flex-wrap

`gap` sets the space between items, without adding space at the outer edges. It is cleaner than using margins.

By default, flex items all squeeze onto one line. `flex-wrap: wrap` lets them move onto new lines when there is not enough room, which is essential for responsive card layouts.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { margin: 0; font-family: Arial, sans-serif; }
  nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #1a237e;
    color: white;
    padding: 12px 20px;
  }
  nav ul {
    display: flex;
    gap: 20px;
    list-style: none;
    margin: 0;
    padding: 0;
  }
  .logo { font-size: 22px; font-weight: bold; }

  .hero {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 180px;
    background-color: #e8eaf6;
    font-size: 24px;
  }

  .tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 16px;
  }
  .tags span {
    background-color: #c5cae9;
    padding: 6px 12px;
    border-radius: 999px;
  }
</style>
</head>
<body>
  <nav>
    <div class="logo">Skope</div>
    <ul>
      <li>Home</li>
      <li>Courses</li>
      <li>Contact</li>
    </ul>
  </nav>
  <div class="hero">Perfectly centred</div>
  <div class="tags">
    <span>HTML</span><span>CSS</span><span>JavaScript</span><span>Python</span>
    <span>SQL</span><span>PHP</span><span>Git</span><span>Design</span><span>Excel</span>
  </div>
</body>
</html>
```

Three common patterns in one page: a navigation bar with the logo on the left and links on the right, content centred both ways, and a row of tags that wraps on narrow screens. Make the window narrow to watch the tags wrap.

> **Tip:** `display: flex; justify-content: center; align-items: center;` is the classic recipe for centring anything inside a box. Memorise it.

## align-content

When items wrap onto several lines, `align-content` controls how those *lines* are spaced on the cross axis. It accepts the same values as `justify-content`. It has no effect when everything fits on one line.

```exercise
Q: Turn the element into a flex container.
.toolbar { display: ___; }
A: flex
```

```exercise
Q: Centre the child both horizontally and vertically in a row container.
.box { display: flex; justify-content: ___; align-items: ___; }
A: center | center
```
