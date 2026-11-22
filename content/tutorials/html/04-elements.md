---
title: HTML Elements and Nesting
module: 2
module_title: HTML Basics
minutes: 10
---

An HTML page is built from **elements**. Understanding what an element is, and how elements sit inside one another, is the single most important idea in HTML.

## What is an element?

An element is usually made of three parts:

```html
<p>Nyama choma is popular in Kenya.</p>
```

| Part | In the example | Meaning |
|---|---|---|
| Opening tag | `<p>` | Where the element starts |
| Content | `Nyama choma is popular in Kenya.` | What the element contains |
| Closing tag | `</p>` | Where the element ends (note the `/`) |

People often say "tag" and "element" as if they mean the same thing. Strictly, the *tags* are the labels in angle brackets, and the *element* is the whole thing: opening tag, content and closing tag together.

## A page full of elements

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Elements</title>
</head>
<body>
  <h1>Lake Naivasha</h1>
  <p>Lake Naivasha is a freshwater lake in the Rift Valley.</p>
  <p>Visitors come to see hippos, fish eagles and flamingos.</p>
</body>
</html>
```

This page contains `html`, `head`, `meta`, `title`, `body`, `h1` and two `p` elements.

## Empty (void) elements

Some elements have no content and therefore no closing tag. They are called **void elements**. Common ones are:

| Element | Purpose |
|---|---|
| `<br>` | Line break |
| `<hr>` | Thematic break (a horizontal line) |
| `<img>` | Image |
| `<input>` | Form field |
| `<meta>` | Page information |
| `<link>` | Link to an external resource such as CSS |

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Void Elements</title>
</head>
<body>
  <p>Jambo Safaris<br>P.O. Box 123<br>Arusha, Tanzania</p>
  <hr>
  <p>Open Monday to Saturday.</p>
</body>
</html>
```

> **Note:** You may see void elements written as `<br />` with a slash at the end. That style comes from XHTML. In HTML5 both `<br>` and `<br />` are allowed; this tutorial uses the shorter form.

## Nesting

Elements can contain other elements. This is called **nesting**. In the example below, a `<strong>` element is nested inside a `<p>` element, and both are nested inside `<body>`.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Nesting</title>
</head>
<body>
  <p>The matatu to Thika leaves at <strong>7:30 am</strong> sharp.</p>
  <ul>
    <li>Fare: <em>KSh 150</em></li>
    <li>Stage: Kencom</li>
  </ul>
</body>
</html>
```

We describe nested elements with family words:

- The element on the outside is the **parent**.
- The element inside is the **child**.
- Children of the same parent are **siblings**.

In the example, `ul` is the parent of both `li` elements, and the two `li` elements are siblings.

## The golden rule: close in reverse order

When elements are nested, the one opened last must be closed first. Think of it like boxes inside boxes: you must close the small box before you can close the big one.

Correct:

```html
<p>This is <strong>very important</strong> news.</p>
```

Wrong (the tags overlap):

```html
<p>This is <strong>very important</p></strong> news.
```

> **Warning:** Browsers try to repair overlapping tags, but each browser may repair them differently. The result can be strange spacing, broken styles or text that ends up in the wrong place. Always close tags in the correct order.

## Do not forget closing tags

Some elements such as `<p>` and `<li>` are allowed to have their closing tag left out in certain situations, and the page still works. It is still good practice to always write the closing tag. Your code is clearer and you avoid surprises when you add more content later.

## Case does not matter, but be consistent

`<P>` and `<p>` mean the same to the browser. The modern convention is lowercase for all tag and attribute names, and that is what you will see in this tutorial.

## Whitespace

Browsers collapse extra spaces and line breaks in your HTML into a single space. That is why you can indent your code freely without changing how the page looks.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Whitespace</title>
</head>
<body>
  <p>Kampala          is the capital
     of
     Uganda.</p>
</body>
</html>
```

The output shows one ordinary sentence. To create a new line on purpose, use a new paragraph or a `<br>` element, as you will see in the lesson on [paragraphs](paragraphs).

```exercise
Q: Close the tags in the correct order.
<p>Meet us at <strong>Uhuru Park</___></___>
A: strong | p
H: The element opened last is closed first.
```

```exercise
Q: Which void element creates a line break? Fill it in.
<p>Line one<___>Line two</p>
A: br
H: It is two letters long and short for "break".
```
