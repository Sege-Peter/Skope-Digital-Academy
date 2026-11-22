---
title: CSS Display and Visibility
module: 3
module_title: Layout
minutes: 11
---

The `display` property decides how an element takes part in the layout: whether it starts a new line, whether it accepts a width, and whether it shows at all. It is one of the most important properties in CSS.

## Block elements

A **block** element starts on a new line and stretches to fill the full width of its parent. You can give it a width, height, margin and padding on all sides. `<div>`, `<p>`, `<h1>` to `<h6>`, `<ul>`, `<section>` and `<form>` are block elements by default.

## Inline elements

An **inline** element flows along with the text. It does not start a new line and only takes as much width as its content needs. Inline elements **ignore** `width` and `height`, and vertical margins do not push other lines away. `<span>`, `<a>`, `<strong>` and `<em>` are inline by default.

## inline-block

`inline-block` is a mix: the element sits on the same line as its neighbours, like inline, but respects `width`, `height`, and all margins and padding, like block. It is useful for buttons, badges and simple rows of boxes.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; }
  .item {
    width: 120px;
    padding: 10px;
    margin: 5px;
    background-color: #c8e6c9;
    border: 1px solid #388e3c;
  }
  .block { display: block; }
  .inline { display: inline; }
  .inline-block { display: inline-block; }
</style>
</head>
<body>
  <h3>display: block</h3>
  <span class="item block">Kitale</span>
  <span class="item block">Bungoma</span>

  <h3>display: inline (width is ignored)</h3>
  <div class="item inline">Kitale</div>
  <div class="item inline">Bungoma</div>

  <h3>display: inline-block</h3>
  <div class="item inline-block">Kitale</div>
  <div class="item inline-block">Bungoma</div>
</body>
</html>
```

Notice that `display` overrides the default. A `<span>` with `display: block` behaves like a `<div>`, and a `<div>` with `display: inline` behaves like a `<span>`.

## Other display values

| Value | What it does |
|---|---|
| `block` | new line, full width |
| `inline` | flows in text, ignores width/height |
| `inline-block` | flows in text, but accepts width/height |
| `none` | removes the element from the page completely |
| `flex` | turns the element into a Flexbox container (see [Flexbox Container](flexbox-container)) |
| `grid` | turns the element into a Grid container (see [Grid](grid)) |

## Hiding elements: display vs visibility

There are two common ways to hide an element, and they behave differently:

- `display: none` removes the element entirely. The space it took up closes, and the content below moves up.
- `visibility: hidden` makes the element invisible but **keeps its space**. It leaves an empty gap.

A third option, `opacity: 0`, also keeps the space. The element is still there and can even be clicked, it is just fully transparent. Opacity is often used with transitions to fade things in and out.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; }
  .box {
    padding: 12px;
    margin: 6px 0;
    color: white;
  }
  .a { background-color: #6a1b9a; }
  .b { background-color: #c62828; }
  .c { background-color: #1565c0; }
  .gone { display: none; }
  .invisible { visibility: hidden; }
</style>
</head>
<body>
  <h3>Using display: none on box B</h3>
  <div class="box a">A</div>
  <div class="box b gone">B</div>
  <div class="box c">C</div>

  <h3>Using visibility: hidden on box B</h3>
  <div class="box a">A</div>
  <div class="box b invisible">B</div>
  <div class="box c">C</div>
</body>
</html>
```

In the first group C moves up next to A. In the second, a gap remains where B is hiding.

> **Note:** Content hidden with `display: none` or `visibility: hidden` is also hidden from screen readers. To hide something visually but keep it available to screen readers, developers use a special "visually hidden" class instead.

## When to use which

| Goal | Use |
|---|---|
| Hide a menu until a button is pressed | `display: none` |
| Keep the layout steady while something is hidden | `visibility: hidden` |
| Fade an element in or out smoothly | `opacity` with a transition |

```exercise
Q: Hide the banner completely so it takes no space.
.banner { display: ___; }
A: none
```

```exercise
Q: Hide the label but keep its space in the layout.
.label { ___: hidden; }
A: visibility
```
