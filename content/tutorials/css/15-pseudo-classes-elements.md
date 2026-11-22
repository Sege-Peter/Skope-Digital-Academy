---
title: CSS Pseudo-classes and Pseudo-elements
module: 4
module_title: Selectors and Effects
minutes: 14
---

Ordinary selectors pick elements by their name, class or id. **Pseudo-classes** pick elements by their *state* or *position*, such as "the link the mouse is over" or "the first item in a list". **Pseudo-elements** style a *part* of an element, or add decorative content that is not in the HTML.

## Pseudo-classes use one colon

A pseudo-class is written after a selector with a single colon: `button:hover`.

### User-action states

| Pseudo-class | Matches when |
|---|---|
| `:hover` | the mouse pointer is over the element |
| `:active` | the element is being pressed |
| `:focus` | the element has keyboard focus (e.g. an input being typed in) |
| `:focus-visible` | it has focus *and* the browser thinks a focus ring should show (usually keyboard use) |
| `:visited` | a link the user has already opened |

For links, write the states in the order `:link`, `:visited`, `:hover`, `:active`, otherwise later rules can cancel earlier ones.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; padding: 20px; }
  .btn {
    background-color: #1565c0;
    color: white;
    border: none;
    padding: 12px 22px;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
  }
  .btn:hover { background-color: #0d47a1; }
  .btn:active { transform: scale(0.96); }
  .btn:focus-visible { outline: 3px solid #ffb300; outline-offset: 2px; }

  input {
    padding: 10px;
    border: 2px solid #bbb;
    border-radius: 6px;
  }
  input:focus {
    border-color: #2e7d32;
    outline: none;
    background-color: #f1f8e9;
  }
</style>
</head>
<body>
  <button class="btn">Pay with M-Pesa</button>
  <p><input type="text" placeholder="Phone number, e.g. 0712..."></p>
  <p>Hover and press the button. Click in the box to see its focus style. Press Tab to see the keyboard focus ring.</p>
</body>
</html>
```

### Structural pseudo-classes

These select elements by their position among siblings.

| Pseudo-class | Selects |
|---|---|
| `:first-child` | an element that is the first child of its parent |
| `:last-child` | the last child |
| `:nth-child(2)` | the second child |
| `:nth-child(odd)` / `:nth-child(even)` | every odd or even child |
| `:nth-child(3n)` | every third child |
| `:not(.done)` | elements that do *not* match `.done` |

### Form states

`:checked`, `:disabled`, `:required`, `:valid` and `:invalid` let you style form controls based on their current state.

## Pseudo-elements use two colons

A pseudo-element is written with a double colon: `p::first-line`.

| Pseudo-element | Styles |
|---|---|
| `::before` | inserts content at the start of the element |
| `::after` | inserts content at the end of the element |
| `::first-letter` | the first letter of a block of text |
| `::first-line` | the first line of a block of text |
| `::placeholder` | the placeholder text of an input |
| `::selection` | text the user has highlighted |

`::before` and `::after` need the `content` property, even if it is empty (`content: "";`), or they will not appear. They are often used for icons, quotation marks and decorative shapes.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Georgia, serif; max-width: 560px; margin: 20px auto; }

  .story::first-letter {
    font-size: 3em;
    float: left;
    line-height: 1;
    margin-right: 6px;
    color: #8d6e63;
  }

  table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
  td, th { padding: 8px; border-bottom: 1px solid #ddd; text-align: left; }
  tr:nth-child(even) { background-color: #f3e5f5; }
  tr:first-child { background-color: #6a1b9a; color: white; }

  .tasks li.done::before { content: "\2714  "; color: green; }
  .tasks li:not(.done) { color: #c62828; }

  .required::after { content: " *"; color: red; }

  ::selection { background-color: #ffeb3b; }
</style>
</head>
<body>
  <p class="story">Long ago on the shores of Lake Turkana, people lived by fishing and herding. Select some of this text to see the custom highlight colour.</p>

  <table>
    <tr><th>County</th><th>Capital</th></tr>
    <tr><td>Kisumu</td><td>Kisumu</td></tr>
    <tr><td>Uasin Gishu</td><td>Eldoret</td></tr>
    <tr><td>Kilifi</td><td>Kilifi</td></tr>
    <tr><td>Nyeri</td><td>Nyeri</td></tr>
  </table>

  <ul class="tasks">
    <li class="done">Register for the course</li>
    <li>Finish the CSS lesson</li>
    <li class="done">Pay the fee</li>
  </ul>

  <label class="required">Full name</label>
</body>
</html>
```

> **Note:** Content added with `::before` and `::after` is decorative. Do not use it for important information, because it cannot be selected or copied and some assistive technologies skip it.

> **Tip:** `:hover` does not exist on touch screens in the same way. Never hide essential actions behind a hover effect; make sure everything also works with a tap.

```exercise
Q: Change the link colour when the mouse is over it.
a:___ { color: orange; }
A: hover
```

```exercise
Q: Add an arrow after every .next link using a pseudo-element.
.next::___ { ___: " \2192"; }
A: after | content
```

```exercise
Q: Stripe every even row of the table.
tr:nth-child(___) { background: #eee; }
A: even / 2n
```
