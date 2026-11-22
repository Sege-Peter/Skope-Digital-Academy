---
title: CSS Introduction
module: 1
module_title: Getting Started
minutes: 8
---

CSS stands for **Cascading Style Sheets**. It is the language that controls how a web page looks: its colours, fonts, spacing, layout and even its animations.

HTML gives a page its structure. It says "this is a heading", "this is a paragraph", "this is a list of products". CSS takes that structure and decides how each part should appear on the screen. Without CSS, every website would look like a plain black-and-white document.

## Why learn CSS?

Almost every website you visit, from M-Pesa statements to news sites like the ones you read every morning, uses CSS. Learning it lets you:

- Give your pages a consistent look with colours and fonts that match a brand.
- Arrange content into columns, cards, menus and grids.
- Make one page work well on a phone, a tablet and a laptop.
- Add small touches like hover effects and smooth transitions.

Because CSS is kept separate from HTML, you can change the look of a whole website by editing one file, without touching the content.

## A first look

Here is a plain HTML page with a small `<style>` block. The CSS inside it changes the background, the heading colour and the font of the paragraph.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body {
    background-color: #f2f7f2;
    font-family: Arial, sans-serif;
  }
  h1 {
    color: #1b5e20;
    text-align: center;
  }
  p {
    color: #333333;
    font-size: 18px;
  }
</style>
</head>
<body>
  <h1>Karibu Skope Digital Academy</h1>
  <p>This page is styled with CSS. Try changing the colours and press Run.</p>
</body>
</html>
```

Press **Try it Yourself** and change `#1b5e20` to `crimson`. The heading turns red immediately. That is CSS at work.

## What "cascading" means

The word *cascading* describes how the browser decides which style wins when several rules target the same element. Styles can come from the browser's own defaults, from your stylesheet, and from inline styles on an element. The browser combines them in a fixed order, a bit like water flowing down steps, and the most specific or latest rule usually wins. You will learn the exact rules in the [Combinators & Specificity](combinators-specificity) lesson.

## One stylesheet, many elements

A single CSS rule can style every matching element on the page. In the example below, one rule styles all three list items at once.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  li {
    background-color: #e3f2fd;
    margin: 6px 0;
    padding: 8px;
    border-left: 5px solid #1565c0;
  }
</style>
</head>
<body>
  <h2>Towns on Lake Victoria</h2>
  <ul>
    <li>Kisumu</li>
    <li>Homa Bay</li>
    <li>Mwanza</li>
  </ul>
</body>
</html>
```

If you add a fourth `<li>`, it picks up the same style automatically. This is the main reason CSS saves so much time.

## What you need

You only need a web browser and a text editor. On this site, every example has a **Try it Yourself** button that opens an online editor, so you can experiment without installing anything.

> **Tip:** You should know basic HTML before starting CSS. If tags like `<p>`, `<div>` and `<a>` are new to you, work through the HTML Tutorial first.

## What you will learn

| Part | Topics |
|---|---|
| Getting Started | syntax, selectors, adding CSS to a page |
| Styling Basics | colours, backgrounds, borders, the box model, text, units |
| Layout | display, position, Flexbox, Grid |
| Advanced Selectors and Effects | pseudo-classes, specificity, transitions, animations |
| Responsive and Modern CSS | media queries, CSS variables |

```exercise
Q: Fill in the missing word of the name CSS.
Cascading ___ Sheets
A: Style
H: It describes what CSS controls: the style of the page.
```
