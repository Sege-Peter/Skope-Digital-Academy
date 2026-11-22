---
title: HTML Classes and IDs
module: 3
minutes: 10
---

The `class` and `id` attributes give names to elements. Those names let CSS style specific elements, let JavaScript find them, and (for ids) let links jump straight to them.

## The class attribute

A **class** is a label that you can put on as many elements as you like. Every element with the same class can then be styled with one CSS rule. In CSS, a class is selected with a dot (`.`) before its name.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Classes</title>
  <style>
    .city {
      background-color: #264653;
      color: white;
      padding: 10px;
      margin-bottom: 8px;
    }
  </style>
</head>
<body>
  <div class="city">
    <h2>Nairobi</h2>
    <p>Capital of Kenya.</p>
  </div>
  <div class="city">
    <h2>Kampala</h2>
    <p>Capital of Uganda.</p>
  </div>
  <div class="city">
    <h2>Kigali</h2>
    <p>Capital of Rwanda.</p>
  </div>
</body>
</html>
```

Change the colour in the `.city` rule once, and all three boxes update. That is the power of classes.

## Multiple classes on one element

An element can have several classes, separated by spaces. This lets you combine small, reusable styles.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Multiple Classes</title>
  <style>
    .btn { padding: 8px 16px; border: none; border-radius: 4px; color: white; }
    .btn-green { background-color: #2a9d8f; }
    .btn-red { background-color: #c1121f; }
    .btn-large { font-size: 20px; }
  </style>
</head>
<body>
  <button class="btn btn-green">Pay now</button>
  <button class="btn btn-red">Cancel</button>
  <button class="btn btn-green btn-large">Enrol today</button>
</body>
</html>
```

## Classes work on any element

The same class can be used on different kinds of elements. Here, a `highlight` class is used on a paragraph and on a span.

```html
<p class="highlight">Registration closes on Friday.</p>
<p>Classes start at <span class="highlight">8:00 am</span>.</p>
```

## The id attribute

An **id** is a unique name. Only **one** element on a page may have a given id. In CSS, an id is selected with a hash (`#`).

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>IDs</title>
  <style>
    #main-banner {
      background-color: #f4a261;
      padding: 20px;
      text-align: center;
    }
  </style>
</head>
<body>
  <div id="main-banner">
    <h1>Kerio Valley Hotel</h1>
    <p>Rooms from KSh 4,500 per night</p>
  </div>
  <p><a href="#booking">Go to booking information</a></p>
  <p style="height: 300px;">(More content...)</p>
  <h2 id="booking">Booking</h2>
  <p>Call reception to book your stay.</p>
</body>
</html>
```

Ids have three main uses:

1. Linking to a section of a page with `href="#booking"` (see [links](links)).
2. Connecting a form label to its input (see [forms](forms)).
3. Letting JavaScript find one specific element.

## Class vs id

| Feature | `class` | `id` |
|---|---|---|
| How many elements can share it? | Many | Exactly one |
| How many per element? | Several, space-separated | One |
| CSS selector | `.name` | `#name` |
| Usable as a link target? | No | Yes (`#name`) |
| Typical use | Styling groups of elements | Unique landmarks, labels, link targets |

> **Tip:** For styling, prefer classes. They are reusable and easier to override. Keep ids for things that genuinely appear once and need to be targeted, like link anchors and form fields.

## Using a class from JavaScript

JavaScript can find elements by id or class and change them. A tiny preview:

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>ID and JavaScript</title>
</head>
<body>
  <p id="greeting">Hello!</p>
  <button onclick="document.getElementById('greeting').textContent = 'Habari yako, Otieno?';">Greet in Kiswahili</button>
</body>
</html>
```

## Naming rules and tips

- Names are **case-sensitive**: `Menu` and `menu` are different.
- An id must not contain spaces; a class attribute uses spaces to separate several classes.
- Start names with a letter, and use lowercase words joined by hyphens: `main-menu`, `price-tag`.
- Describe the **purpose**, not the look. `class="warning"` stays meaningful if you change the colour; `class="red-text"` does not.

> **Warning:** Using the same id twice is a common mistake. The page still displays, but links, labels and JavaScript may grab the wrong element. Validators report it as an error.

```exercise
Q: Give the element the class "menu".
<ul ___="menu">
A: class
H: The attribute that can be shared by many elements.
```

```exercise
Q: Complete the CSS selector for the element with id "header" and the one for class "note".
___header { color: green; }
___note { color: gray; }
A: # | .
H: Ids use a hash; classes use a dot.
```
