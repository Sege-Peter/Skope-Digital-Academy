---
title: HTML Forms Basics
module: 4
module_title: Forms
minutes: 12
---

Forms are how websites collect information: sign-up pages, search boxes, contact forms, checkout pages. In this lesson you will learn the building blocks of every form.

## The form element

All form controls live inside a `<form>` element. Two attributes tell the browser what to do when the form is submitted:

| Attribute | Example | Meaning |
|---|---|---|
| `action` | `action="/register"` | The address the data is sent to |
| `method` | `method="post"` | How it is sent: `get` or `post` |

HTML only builds the form. Processing the data (saving it, sending an email) is done by a program on the server, written in a language such as PHP, Python or JavaScript (Node.js).

## A first form

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>First Form</title>
</head>
<body>
  <h2>Join our newsletter</h2>
  <form action="/subscribe" method="post">
    <label for="name">Full name</label><br>
    <input type="text" id="name" name="name"><br><br>

    <label for="email">Email address</label><br>
    <input type="email" id="email" name="email"><br><br>

    <button type="submit">Subscribe</button>
  </form>
</body>
</html>
```

> **Note:** There is no real server behind the examples on this site, so submitting may show a blank or error page. That is expected; focus on how the form looks and behaves.

## The input element

`<input>` is the most common form control. Its `type` attribute decides what kind of field it is. `type="text"` gives a single-line text box. You will meet many other types in the next lesson, [input types](input-types).

Every input that should be sent to the server needs a `name`. The name is the label the server sees, paired with whatever the user typed. For example, typing "Wanjiru" into the field above sends `name=Wanjiru`.

## Labels

A `<label>` describes a form control. Connect them by giving the input an `id` and the label a matching `for` attribute.

Labels are important because:

- Screen readers announce the label when the user reaches the field.
- Clicking the label puts the cursor in the field, which gives a bigger target on phones.

You can also wrap the input inside the label, which connects them without needing `for` and `id`:

```html
<label>
  <input type="checkbox" name="terms"> I agree to the terms
</label>
```

> **Warning:** Do not rely on `placeholder` text instead of a label. Placeholder text disappears as soon as someone starts typing and is often too faint to read.

## Text areas

For longer text such as a message, use `<textarea>`. Unlike `<input>`, it has a closing tag. The `rows` attribute sets its visible height.

## Drop-down lists

`<select>` creates a drop-down. Each choice is an `<option>`, and the `value` attribute is what gets sent to the server.

## Buttons

A `<button type="submit">` sends the form. `type="reset"` clears it back to its starting values, and `type="button"` does nothing by itself (it is used with JavaScript).

## A complete contact form

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact Form</title>
  <style>
    form { max-width: 360px; font-family: sans-serif; }
    label { display: block; margin-top: 12px; font-weight: bold; }
    input, select, textarea { width: 100%; padding: 6px; box-sizing: border-box; }
    button { margin-top: 16px; padding: 8px 20px; }
  </style>
</head>
<body>
  <h2>Contact Kisumu Cyber Hub</h2>
  <form action="/contact" method="post">
    <label for="fullname">Your name</label>
    <input type="text" id="fullname" name="fullname">

    <label for="phone">Phone number</label>
    <input type="tel" id="phone" name="phone" placeholder="0712 345 678">

    <label for="service">Service needed</label>
    <select id="service" name="service">
      <option value="printing">Printing</option>
      <option value="typing">Typing</option>
      <option value="kra">KRA returns</option>
      <option value="other">Other</option>
    </select>

    <label for="message">Message</label>
    <textarea id="message" name="message" rows="4"></textarea>

    <button type="submit">Send message</button>
  </form>
</body>
</html>
```

## Grouping fields

`<fieldset>` draws a box around related controls, and `<legend>` gives that group a caption. This is especially helpful for groups of radio buttons or checkboxes.

```html
<fieldset>
  <legend>Delivery details</legend>
  <label for="town">Town</label>
  <input type="text" id="town" name="town">
</fieldset>
```

## GET or POST?

| Method | How data travels | Use for |
|---|---|---|
| `get` | Added to the URL, e.g. `search?q=chapati` | Searches and filters that are safe to bookmark or share |
| `post` | Sent in the body of the request, not shown in the URL | Sign-ups, logins, payments and anything that changes data |

> **Warning:** Never use `get` for passwords or personal data. It ends up in the address bar, browser history and server logs.

```exercise
Q: Connect the label to the input.
<label ___="county">County</label>
<input type="text" ___="county" name="county">
A: for | id
H: The label uses one attribute and the input uses a matching one.
```

```exercise
Q: Complete the multi-line text box for a message.
<___ name="message" rows="5"></___>
A: textarea | /textarea
H: It is "text" plus "area".
```
