---
title: HTML Lists
module: 3
module_title: Organising Content
minutes: 9
---

Lists are everywhere on the web: navigation menus, shopping lists, step-by-step instructions, glossaries. HTML has three kinds of list, each with its own meaning.

## Unordered lists

Use an **unordered list** when the order of items does not matter. It starts with `<ul>`, and each item goes in an `<li>` (list item) element. Browsers show a bullet before each item.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Unordered List</title>
</head>
<body>
  <h2>Market shopping list</h2>
  <ul>
    <li>Tomatoes</li>
    <li>Onions</li>
    <li>Sukuma wiki</li>
    <li>Maize flour</li>
  </ul>
</body>
</html>
```

## Ordered lists

Use an **ordered list** when the sequence matters, such as instructions or rankings. It starts with `<ol>`, and the browser numbers the items automatically.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Ordered List</title>
</head>
<body>
  <h2>How to send money with M-Pesa</h2>
  <ol>
    <li>Open the M-Pesa menu on your phone.</li>
    <li>Choose <strong>Send Money</strong>.</li>
    <li>Enter the phone number.</li>
    <li>Enter the amount.</li>
    <li>Enter your PIN and confirm.</li>
  </ol>
</body>
</html>
```

If you add or remove a step, the numbers update by themselves. That is a big advantage over typing numbers by hand.

## Ordered list attributes

| Attribute | Example | Effect |
|---|---|---|
| `type` | `type="A"` | Numbering style: `1`, `A`, `a`, `I` or `i` |
| `start` | `start="5"` | Begin counting from 5 |
| `reversed` | `reversed` | Count down instead of up |

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>List Attributes</title>
</head>
<body>
  <h3>Top 3 marathon finishers</h3>
  <ol reversed>
    <li>Third place</li>
    <li>Second place</li>
    <li>First place</li>
  </ol>

  <h3>Exam sections</h3>
  <ol type="A">
    <li>Multiple choice</li>
    <li>Short answers</li>
    <li>Essay</li>
  </ol>
</body>
</html>
```

## Nested lists

A list item can contain another complete list. Put the inner `<ul>` or `<ol>` *inside* the `<li>`, before its closing tag.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Nested List</title>
</head>
<body>
  <h2>East African Community members (some)</h2>
  <ul>
    <li>Kenya
      <ul>
        <li>Nairobi</li>
        <li>Mombasa</li>
      </ul>
    </li>
    <li>Uganda
      <ul>
        <li>Kampala</li>
        <li>Entebbe</li>
      </ul>
    </li>
    <li>Rwanda</li>
  </ul>
</body>
</html>
```

> **Warning:** The only element allowed as a direct child of `<ul>` or `<ol>` is `<li>`. Do not put a `<p>` or another `<ul>` directly inside a list; place it inside an `<li>` instead.

## Description lists

A **description list** pairs terms with their descriptions, like a glossary or a set of questions and answers. It uses three elements:

- `<dl>` wraps the whole list
- `<dt>` holds a term
- `<dd>` holds the description of that term

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Description List</title>
</head>
<body>
  <h2>Kiswahili words for visitors</h2>
  <dl>
    <dt>Jambo</dt>
    <dd>Hello</dd>
    <dt>Asante</dt>
    <dd>Thank you</dd>
    <dt>Pole pole</dt>
    <dd>Slowly, take it easy</dd>
  </dl>
</body>
</html>
```

## Choosing the right list

Ask one question about your content: *does the order matter?*

| Content | Best list | Why |
|---|---|---|
| Ingredients for pilau | `<ul>` | Any order is fine |
| Steps to register for KCSE | `<ol>` | Steps must happen in sequence |
| Top 10 songs this week | `<ol>` | The position is the point |
| Glossary of computer terms | `<dl>` | Each term is paired with a meaning |

Picking the correct list matters beyond looks. Screen readers announce "list, 5 items" and read the numbers in ordered lists, which helps listeners keep track of where they are.

## Lists for navigation

Website menus are usually built as a list of links, because a menu *is* a list of choices. CSS then removes the bullets and lines the items up side by side.

```html
<nav>
  <ul>
    <li><a href="index.html">Home</a></li>
    <li><a href="courses.html">Courses</a></li>
    <li><a href="contact.html">Contact</a></li>
  </ul>
</nav>
```

> **Tip:** To change bullets or remove them, use CSS: `list-style-type: square;` or `list-style: none;`. The old `type` attribute on `<ul>` is obsolete.

```exercise
Q: Complete the list so the items are numbered.
<___>
  <li>Boil water</li>
  <li>Add tea leaves</li>
</___>
A: ol | /ol
H: Use the "ordered" list.
```

```exercise
Q: Fill in the element for each list item.
<ul>
  <___>Nyeri</___>
</ul>
A: li | /li
H: Short for "list item".
```
