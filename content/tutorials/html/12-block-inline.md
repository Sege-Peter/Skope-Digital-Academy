---
title: Block and Inline Elements, div and span
module: 3
minutes: 9
---

Every HTML element has a default way of taking up space on the page. Some stack on top of each other like bricks; others flow along inside a line of text like words. Knowing the difference explains a lot of layout behaviour.

## Block-level elements

A **block-level element** always starts on a new line and stretches to fill the full width available to it. Whatever comes after it is pushed onto the next line.

Common block elements: `<div>`, `<p>`, `<h1>` to `<h6>`, `<ul>`, `<ol>`, `<li>`, `<table>`, `<form>`, `<header>`, `<footer>`, `<section>`, `<article>`, `<nav>`, `<blockquote>`, `<pre>`, `<hr>`.

## Inline elements

An **inline element** does not start a new line. It only takes up as much width as its content and sits in the flow of text.

Common inline elements: `<span>`, `<a>`, `<strong>`, `<em>`, `<b>`, `<i>`, `<code>`, `<img>`, `<abbr>`, `<small>`, `<sub>`, `<sup>`, `<label>`, `<input>`, `<button>`.

## Seeing the difference

The outlines in this example show how much space each element takes.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Block vs Inline</title>
  <style>
    p, h2, div { outline: 2px dashed #2a9d8f; }
    strong, a, span { outline: 2px dashed #e76f51; }
  </style>
</head>
<body>
  <h2>Block: a heading</h2>
  <p>Block: a paragraph with <strong>inline bold text</strong> and <a href="#">an inline link</a>.</p>
  <div>Block: a div</div>
  <span>Inline: span one</span>
  <span>Inline: span two</span>
</body>
</html>
```

The green (block) boxes each fill the whole width. The orange (inline) boxes wrap tightly around their words, and the two spans sit side by side.

## The div element

`<div>` is a generic block container. It has **no meaning of its own**; it simply groups elements together so you can style or position them as one unit.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>div</title>
  <style>
    .card {
      border: 1px solid #ccc;
      border-radius: 8px;
      padding: 12px 16px;
      margin-bottom: 12px;
      max-width: 320px;
    }
  </style>
</head>
<body>
  <div class="card">
    <h3>Baraka Mwangi</h3>
    <p>Web developer, Nairobi</p>
  </div>
  <div class="card">
    <h3>Halima Abdi</h3>
    <p>Graphic designer, Garissa</p>
  </div>
</body>
</html>
```

Each `<div>` groups a heading and a paragraph into a "card" that CSS can give a border and spacing.

## The span element

`<span>` is the inline twin of `<div>`. It also has no meaning of its own. Use it to target a few words inside a line of text.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>span</title>
  <style>
    .price { color: #c1121f; font-weight: bold; }
    .county { background-color: #e0f2f1; padding: 0 4px; }
  </style>
</head>
<body>
  <p>Fresh avocados from <span class="county">Murang'a</span> now only <span class="price">KSh 20</span> each.</p>
</body>
</html>
```

## div vs span

| Feature | `<div>` | `<span>` |
|---|---|---|
| Display | Block | Inline |
| Starts a new line? | Yes | No |
| Typical use | Grouping sections or components | Styling a few words |
| Meaning | None | None |

## Prefer meaningful elements first

Because `<div>` and `<span>` carry no meaning, use them only when no better element exists. If something is a paragraph, use `<p>`; if it is navigation, use `<nav>`; if it is important text, use `<strong>`. You will meet many meaningful layout elements in the lesson on [semantic layout](semantic-elements).

> **Tip:** A page built entirely from `<div>` elements is sometimes called "div soup". It works visually, but it is harder to read, maintain and use with assistive technology.

## Nesting rules

- Block elements can usually contain inline elements and other block elements.
- Inline elements should contain only text and other inline elements.
- A `<p>` cannot contain block elements. If you put a `<div>` inside a `<p>`, the browser closes the paragraph early, which can break your styling.

> **Note:** CSS can change how any element displays, for example `display: block;` or `display: inline-block;`. The block/inline categories describe the *default* behaviour.

```exercise
Q: Use the generic block container to group this card.
<___ class="card">
  <p>Kericho Tea Tours</p>
</___>
A: div | /div
H: Short for "division".
```

```exercise
Q: Use the generic inline element to highlight the word.
<p>Delivery to <___ class="town">Kitale</___> is free.</p>
A: span | /span
H: It is the inline partner of div.
```
