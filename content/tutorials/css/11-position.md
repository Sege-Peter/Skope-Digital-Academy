---
title: CSS Position and z-index
module: 3
minutes: 15
---

Normally the browser places elements one after another in the order they appear in the HTML. This is called **normal flow**. The `position` property lets you move an element away from where normal flow would put it, or pin it to the screen.

Once an element is positioned, you move it with the offset properties `top`, `right`, `bottom` and `left`.

## The five values

| Value | Moves relative to | Keeps its space in the flow? |
|---|---|---|
| `static` | nothing (default) | yes |
| `relative` | its own normal position | yes |
| `absolute` | nearest positioned ancestor | no |
| `fixed` | the browser window | no |
| `sticky` | normal until a scroll threshold, then sticks | yes |

## static

Every element is `static` by default. The offset properties have no effect on it.

## relative

A relatively positioned element can be nudged with offsets, but the space it originally occupied stays reserved, so nothing else moves. `top: 10px` pushes it 10px *down from its top edge*.

The more important use of `relative` is as an **anchor** for absolutely positioned children, as you will see next.

## absolute

An absolutely positioned element is taken out of normal flow. Other elements behave as if it is not there. It is placed relative to its nearest ancestor that has a position other than `static`. If there is none, it is placed relative to the page.

The common pattern is: parent `position: relative`, child `position: absolute`.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; }
  .product {
    position: relative;
    width: 240px;
    height: 160px;
    background-color: #fff8e1;
    border: 1px solid #ffcc80;
    border-radius: 8px;
    padding: 16px;
    box-sizing: border-box;
  }
  .badge {
    position: absolute;
    top: -10px;
    right: -10px;
    background-color: #d32f2f;
    color: white;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 13px;
  }
  .price {
    position: absolute;
    bottom: 12px;
    left: 16px;
    font-weight: bold;
    color: #e65100;
  }
</style>
</head>
<body>
  <div class="product">
    <span class="badge">-20%</span>
    <h3>Kiondo basket</h3>
    <p class="price">KSh 1,200</p>
  </div>
</body>
</html>
```

The badge sits on the top-right corner of the card, and the price is pinned to the bottom, because `.product` is the positioned ancestor. Remove `position: relative` from `.product` and the badge jumps to the corner of the page.

## fixed

A fixed element is positioned relative to the browser window and stays in place when the page scrolls. It is used for floating chat buttons, cookie banners and some headers.

## sticky

A sticky element behaves like `relative` until you scroll it to a set offset, then it sticks there like `fixed`, but only while its parent is still on screen. It needs at least one offset, usually `top: 0`.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { margin: 0; font-family: Arial, sans-serif; }
  header {
    position: sticky;
    top: 0;
    background-color: #263238;
    color: white;
    padding: 14px;
    z-index: 10;
  }
  section {
    padding: 20px;
    height: 300px;
    border-bottom: 1px solid #ddd;
  }
  .chat {
    position: fixed;
    bottom: 16px;
    right: 16px;
    background-color: #25d366;
    color: white;
    padding: 12px 18px;
    border-radius: 999px;
  }
</style>
</head>
<body>
  <header>Kakamega Forest Tours (sticky header)</header>
  <section>Scroll down. The header sticks to the top.</section>
  <section>The green button stays fixed in the corner.</section>
  <section>Keep scrolling...</section>
  <div class="chat">Chat with us</div>
</body>
</html>
```

## z-index: who is on top?

When positioned elements overlap, `z-index` decides the stacking order. A higher number appears in front of a lower one. It only works on positioned elements (not `static`) and on Flexbox and Grid items.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  .card {
    position: absolute;
    width: 140px;
    height: 100px;
    color: white;
    font: bold 16px Arial, sans-serif;
    padding: 10px;
  }
  .one { background-color: #1e88e5; top: 20px; left: 20px; z-index: 1; }
  .two { background-color: #43a047; top: 50px; left: 70px; z-index: 3; }
  .three { background-color: #fb8c00; top: 80px; left: 120px; z-index: 2; }
</style>
</head>
<body>
  <div class="card one">z-index 1</div>
  <div class="card two">z-index 3</div>
  <div class="card three">z-index 2</div>
</body>
</html>
```

> **Warning:** `z-index` compares elements only within the same *stacking context*. If a parent has its own `z-index` (or `opacity` below 1, or a `transform`), its children cannot climb above elements outside that parent, no matter how high their number is.

> **Tip:** Use small, planned z-index values (1, 10, 100) instead of 99999. It keeps your layers easy to understand.

```exercise
Q: Make the parent the anchor for its absolutely positioned child.
.card { position: ___; }
.card .tag { position: ___; top: 0; right: 0; }
A: relative | absolute
```

```exercise
Q: Keep the navigation bar at the top while scrolling, but only after it reaches the top.
nav { position: ___; top: 0; }
A: sticky
```
