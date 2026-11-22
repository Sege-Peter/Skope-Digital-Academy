---
title: CSS Flexbox Items
module: 3
minutes: 14
---

In the [Flexbox Container](flexbox-container) lesson you controlled the layout from the parent. This lesson covers properties you set on the **flex items** themselves, the direct children of a flex container. They decide how much each item grows, shrinks, and where it sits.

## flex-grow: sharing extra space

When the container is wider than the items need, there is leftover space. `flex-grow` says how much of it an item should take. The default is `0`, meaning "do not grow".

If one item has `flex-grow: 1` and the others have `0`, that item takes all the extra space. If three items each have `flex-grow: 1`, they share it equally. An item with `flex-grow: 2` gets twice the share of an item with `1`.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; }
  .row {
    display: flex;
    gap: 6px;
    margin-bottom: 12px;
  }
  .row div {
    background-color: #5e35b1;
    color: white;
    padding: 14px;
  }
  .grow1 { flex-grow: 1; }
  .grow2 { flex-grow: 2; }
</style>
</head>
<body>
  <p>Only the middle item grows:</p>
  <div class="row">
    <div>Logo</div>
    <div class="grow1">Search box takes the extra space</div>
    <div>Login</div>
  </div>

  <p>Extra space shared 1 : 2 : 1</p>
  <div class="row">
    <div class="grow1">1</div>
    <div class="grow2">2</div>
    <div class="grow1">1</div>
  </div>
</body>
</html>
```

## flex-shrink: giving up space

When the container is too narrow, items shrink. `flex-shrink` controls how much. The default is `1`, so all items shrink evenly. Set `flex-shrink: 0` on an item that must keep its size, such as a logo or an avatar.

## flex-basis: the starting size

`flex-basis` sets an item's size along the main axis *before* growing or shrinking happens. It works like `width` in a row (or `height` in a column). The default `auto` uses the item's width or content size.

## The flex shorthand

You will usually write all three together using `flex`, in the order grow, shrink, basis:

| Shorthand | Means | Use for |
|---|---|---|
| `flex: 1` | `1 1 0` | equal columns that share all space |
| `flex: auto` | `1 1 auto` | grow and shrink, starting from content size |
| `flex: none` | `0 0 auto` | fixed size, never grow or shrink |
| `flex: 0 0 200px` | exactly 200px | a fixed sidebar |
| `flex: 1 1 250px` | start at 250px, then flex | responsive cards with `flex-wrap` |

## align-self: one item, different alignment

`align-self` overrides the container's `align-items` for a single item. It takes the same values: `flex-start`, `flex-end`, `center`, `stretch`, `baseline`.

## order: changing visual order

`order` moves an item without changing the HTML. Items are sorted by their `order` value, which is `0` by default. A negative value moves an item to the front.

> **Warning:** `order` changes only what people see. Screen readers and keyboard tab order still follow the HTML. Do not use it to rearrange content in a way that changes its meaning.

## A responsive card layout

This combines container and item properties. Each card starts at 200px, grows to fill the row, and wraps when the screen is narrow. The footer link uses `margin-top: auto` to sit at the bottom of every card, even when the text lengths differ.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; background-color: #f5f5f5; }
  .cards {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
  }
  .card {
    flex: 1 1 200px;
    display: flex;
    flex-direction: column;
    background-color: white;
    border-radius: 10px;
    padding: 16px;
    box-shadow: 0 2px 6px rgb(0 0 0 / 0.1);
  }
  .card h3 { margin-top: 0; color: #2e7d32; }
  .card a {
    margin-top: auto;
    align-self: flex-start;
    background-color: #2e7d32;
    color: white;
    padding: 8px 14px;
    border-radius: 6px;
    text-decoration: none;
  }
  .featured { order: -1; border: 2px solid #f9a825; }
</style>
</head>
<body>
  <div class="cards">
    <div class="card">
      <h3>Maasai Mara</h3>
      <p>Watch the great migration.</p>
      <a href="#">Book</a>
    </div>
    <div class="card">
      <h3>Diani Beach</h3>
      <p>White sand, coral reefs and calm warm water, perfect for a long weekend away from the city.</p>
      <a href="#">Book</a>
    </div>
    <div class="card featured">
      <h3>Mount Kenya</h3>
      <p>Featured: moved first with order.</p>
      <a href="#">Book</a>
    </div>
  </div>
</body>
</html>
```

> **Tip:** `margin-left: auto` on one item in a row pushes it, and everything after it, to the far right. It is a neat way to place a "Log in" button at the end of a nav bar.

```exercise
Q: Make the main content take all the space left over by a fixed sidebar.
.sidebar { flex: 0 0 220px; }
.main { flex-___: 1; }
A: grow
```

```exercise
Q: Align only this one item to the bottom of its row container.
.special { ___: flex-end; }
A: align-self
```
