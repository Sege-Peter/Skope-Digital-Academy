---
title: Entities, Symbols and Emojis
module: 5
minutes: 8
---

Some characters have a special job in HTML, and others are hard to type on a normal keyboard. **Character entities** let you show them safely.

## Why entities are needed

The browser reads `<` as the start of a tag. So if you want to write "5 < 10" or show a tag like `<p>` as text, the browser gets confused. Entities solve this by spelling the character out in code.

An entity starts with `&` and ends with `;`. It can use a name or a number:

- By name: `&lt;`
- By decimal number: `&#60;`
- By hexadecimal number: `&#x3C;`

All three display the same `<` character.

## The essential entities

| Character | Entity name | Number | Description |
|---|---|---|---|
| `<` | `&lt;` | `&#60;` | Less than |
| `>` | `&gt;` | `&#62;` | Greater than |
| `&` | `&amp;` | `&#38;` | Ampersand |
| `"` | `&quot;` | `&#34;` | Double quote |
| `'` | `&apos;` | `&#39;` | Apostrophe |
| (space) | `&nbsp;` | `&#160;` | Non-breaking space |

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Entities</title>
</head>
<body>
  <p>To make a paragraph, write &lt;p&gt; at the start and &lt;/p&gt; at the end.</p>
  <p>Maths: 5 &lt; 10 and 20 &gt; 15.</p>
  <p>Company: Otieno &amp; Sons Hardware</p>
</body>
</html>
```

> **Tip:** The two you will use most are `&lt;` and `&amp;`. Always escape `<` and `&` when they appear in normal text, especially when showing code examples on a page.

## Non-breaking spaces

A normal space allows the browser to wrap the line there. A non-breaking space (`&nbsp;`) keeps the words on either side together on one line. Use it for things that look wrong when split, such as amounts and units.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Non-breaking Space</title>
  <style>
    p { width: 160px; border: 1px solid #ccc; padding: 4px; }
  </style>
</head>
<body>
  <p>The total price of the new phone is KSh&nbsp;24,999 only.</p>
  <p>The total price of the new phone is KSh 24,999 only.</p>
</body>
</html>
```

In the first box, "KSh" and the amount always stay together.

> **Warning:** Do not use rows of `&nbsp;` to push text sideways or indent it. Use CSS `margin` or `padding` for spacing.

## Symbols

Entities also exist for many symbols. With a UTF-8 page you can usually type these directly, but entities are handy when your keyboard cannot.

| Symbol | Entity | Meaning |
|---|---|---|
| © | `&copy;` | Copyright |
| ® | `&reg;` | Registered trademark |
| ™ | `&trade;` | Trademark |
| € | `&euro;` | Euro |
| £ | `&pound;` | Pound |
| ° | `&deg;` | Degree |
| × | `&times;` | Multiplication |
| ÷ | `&divide;` | Division |
| → | `&rarr;` | Right arrow |
| — | `&mdash;` | Em dash |

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Symbols</title>
</head>
<body>
  <p>Temperature in Lodwar today: 38&deg;C</p>
  <p>Room size: 4 &times; 5 metres</p>
  <p>Next step &rarr; pay the deposit</p>
  <footer>&copy; 2026 Skope Digital Academy</footer>
</body>
</html>
```

## Emojis

Emojis are ordinary characters in the Unicode standard. As long as your page has `<meta charset="UTF-8">`, you can paste them straight into your HTML. You can also write them with their number in hexadecimal.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Emojis</title>
  <style>
    .big { font-size: 40px; }
  </style>
</head>
<body>
  <p class="big">&#x1F981; &#x1F418; &#x1F992;</p>
  <p>Typed directly: 🇰🇪 ☀️ ☕</p>
  <p>Safari animals: lion, elephant and giraffe.</p>
</body>
</html>
```

Emojis look slightly different on Android, iPhone and Windows, because each system draws them with its own font.

> **Note:** If an emoji carries meaning, make sure the surrounding text also explains it. Screen readers announce emojis by their official names, which can be long or confusing.

```exercise
Q: Show the less-than and greater-than signs as text.
<p>Use ___h1___ for main headings.</p>
A: &lt; | &gt;
H: lt means "less than" and gt means "greater than". Do not forget the & and ;.
```

```exercise
Q: Write the company name with an escaped ampersand.
<p>Wekesa ___ Daughters</p>
A: &amp;
H: The entity name is short for "ampersand".
```
