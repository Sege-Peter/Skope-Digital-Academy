---
title: Your First Web Page
module: 3
minutes: 15
---

Every website you visit – from a news site to an online shop – is built on the same three tools: **HTML** for structure, **CSS** for style and **JavaScript** for behaviour. Today you will build a small business page using all three.

## HTML: the skeleton

HTML uses **tags** in angle brackets to mark up content. Most tags come in pairs: an opening tag `<h1>` and a closing tag `</h1>`.

```example html
<!DOCTYPE html>
<html>
<head>
  <title>Mama Wanjiku's Kitchen</title>
</head>
<body>
  <h1>Mama Wanjiku's Kitchen</h1>
  <p>Fresh chapati, githeri and chai in Nyeri town.</p>
  <h2>Today's menu</h2>
  <ul>
    <li>Chapati – KES 20</li>
    <li>Githeri – KES 80</li>
    <li>Chai – KES 30</li>
  </ul>
</body>
</html>
```

| Tag | What it does |
|---|---|
| `<h1>` to `<h6>` | headings, from biggest to smallest |
| `<p>` | a paragraph |
| `<ul>` and `<li>` | a bulleted list and its items |
| `<a href="...">` | a link |
| `<img src="..." alt="...">` | an image (no closing tag) |

## Exercise

```exercise
Q: Close the paragraph tag correctly.
<p>Open every day from 7am</___>
A: p
H: A closing tag has a slash and the same name as the opening tag.
```

## CSS: the clothes

CSS changes how things look: colours, sizes, spacing. Put it inside a `<style>` tag in the `<head>`.

```example html
<!DOCTYPE html>
<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; background: #fff8ee; padding: 16px; }
    h1 { color: #b34700; }
    .price { font-weight: bold; color: #2e7d32; }
  </style>
</head>
<body>
  <h1>Mama Wanjiku's Kitchen</h1>
  <p>Chapati <span class="price">KES 20</span></p>
  <p>Githeri <span class="price">KES 80</span></p>
</body>
</html>
```

Each CSS rule has a **selector** (`h1`, `.price`) and **declarations** inside curly braces (`color: #b34700;`). A dot before a name means "every element with this class".

## JavaScript: the brain

JavaScript makes the page respond to people. This button adds up an order when clicked – using the same variables, conditions and functions you already know, just with different punctuation.

```example html
<!DOCTYPE html>
<html>
<body>
  <h1>Quick order</h1>
  <p>Chapati KES 20 each</p>
  <button onclick="addChapati()">Add a chapati</button>
  <p id="total">Total: KES 0</p>

  <script>
    let total = 0;
    function addChapati() {
      total = total + 20;
      document.getElementById("total").textContent = "Total: KES " + total;
    }
  </script>
</body>
</html>
```

Click the button several times. `let total = 0;` is a variable, `function addChapati()` is a function, and `document.getElementById("total")` finds the paragraph so we can change its text.

## Exercise

```exercise
Q: Complete the CSS so all h1 headings turn green.
h1 { ___: green; }
A: color
H: The American spelling of the word for "colour".
```

> **Tip:** On a phone or laptop, you can practise anywhere with the online editor. Professionals use free editors such as VS Code, but you do not need one to begin.

## Practice task

Build a one-page site for a real or imaginary small business near you – a salon, a cyber café, a boda boda stage. Include a heading, a short description, a list of at least three services with prices in KES, one CSS colour change and a contact line. Screenshot it: this is the first item in your coding portfolio.
