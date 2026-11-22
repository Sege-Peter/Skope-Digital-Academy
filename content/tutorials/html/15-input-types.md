---
title: Input Types and Validation
module: 4
minutes: 12
---

The `type` attribute turns a plain `<input>` into many different controls: email boxes, number pickers, date pickers, checkboxes and more. Choosing the right type gives users a better keyboard on their phone and lets the browser check their answers before the form is sent.

## Common input types

| Type | What it does |
|---|---|
| `text` | Single line of text (the default) |
| `email` | Email address; checks for a valid format |
| `password` | Hides the characters as they are typed |
| `tel` | Phone number; shows a number pad on phones |
| `number` | Numbers only, with up/down arrows |
| `url` | A web address |
| `search` | A search box |
| `date` | Date picker |
| `time` | Time picker |
| `range` | A slider |
| `color` | Colour picker |
| `checkbox` | Tick box; any number can be selected |
| `radio` | Option button; only one in a group can be selected |
| `file` | Choose a file to upload |
| `hidden` | Not shown; carries a value the user does not need to see |

## Trying the types

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Input Types</title>
  <style>
    label { display: block; margin-top: 10px; }
  </style>
</head>
<body>
  <form>
    <label for="em">Email</label>
    <input type="email" id="em" name="email">

    <label for="pw">Password</label>
    <input type="password" id="pw" name="password">

    <label for="qty">Number of bags (1 to 10)</label>
    <input type="number" id="qty" name="qty" min="1" max="10" value="1">

    <label for="dob">Date of birth</label>
    <input type="date" id="dob" name="dob">

    <label for="fav">Favourite colour</label>
    <input type="color" id="fav" name="fav" value="#2a9d8f">

    <label for="vol">Volume</label>
    <input type="range" id="vol" name="vol" min="0" max="100">
  </form>
</body>
</html>
```

> **Tip:** Open this example on a phone. The email field shows a keyboard with an `@` key, and the number field shows a number pad. Using the right type is a free usability win.

## Radio buttons and checkboxes

**Radio buttons** let the user choose exactly one option. All radios in a group must share the same `name`; that is what groups them. Each needs its own `value`.

**Checkboxes** let the user choose any number of options, including none.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Radio and Checkbox</title>
</head>
<body>
  <form>
    <fieldset>
      <legend>Payment method</legend>
      <label><input type="radio" name="pay" value="mpesa" checked> M-Pesa</label><br>
      <label><input type="radio" name="pay" value="airtel"> Airtel Money</label><br>
      <label><input type="radio" name="pay" value="card"> Card</label>
    </fieldset>

    <fieldset>
      <legend>Extras</legend>
      <label><input type="checkbox" name="extras" value="gift"> Gift wrap</label><br>
      <label><input type="checkbox" name="extras" value="sms"> SMS updates</label>
    </fieldset>
  </form>
</body>
</html>
```

Use the `checked` attribute to choose an option in advance.

## Validation attributes

Browsers can check answers before the form is submitted. If something is wrong, the browser stops the submission and shows a message next to the field. This is called **client-side validation**.

| Attribute | Example | Rule |
|---|---|---|
| `required` | `required` | The field cannot be empty |
| `minlength` / `maxlength` | `minlength="8"` | Minimum / maximum number of characters |
| `min` / `max` | `min="18"` | Smallest / largest number or date |
| `step` | `step="0.5"` | Allowed increments for numbers |
| `pattern` | `pattern="0[17][0-9]{8}"` | Text must match a regular expression |
| `type` | `type="email"` | Value must fit the type's format |

Try submitting this form with empty or wrong answers:

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Validation</title>
  <style>
    label { display: block; margin-top: 10px; }
    input:invalid { border-color: #c1121f; }
  </style>
</head>
<body>
  <h2>Create an account</h2>
  <form action="#" method="post">
    <label for="user">Username (4 to 15 characters)</label>
    <input type="text" id="user" name="user" required minlength="4" maxlength="15">

    <label for="mail">Email</label>
    <input type="email" id="mail" name="mail" required>

    <label for="age">Age</label>
    <input type="number" id="age" name="age" min="18" max="99" required>

    <label for="mobile">Mobile number, e.g. 0712345678</label>
    <input type="tel" id="mobile" name="mobile" pattern="0[17][0-9]{8}" title="10 digits starting with 07 or 01" required>

    <label for="pass">Password (at least 8 characters)</label>
    <input type="password" id="pass" name="pass" required minlength="8" autocomplete="new-password">

    <p><button type="submit">Sign up</button></p>
  </form>
</body>
</html>
```

When `pattern` fails, browsers show the `title` text to explain what format is expected.

> **Warning:** Browser validation is for convenience only. Anyone can switch it off or send data without using your form. Your server code must **always** check the data again before trusting it.

## Other helpful attributes

- `placeholder` shows a faint hint inside an empty field, e.g. `placeholder="e.g. Kisumu"`. Use it in addition to a label, never instead of one.
- `value` sets the starting value.
- `readonly` shows a value that cannot be edited but is still submitted.
- `disabled` greys out a field; disabled fields are **not** submitted.
- `autocomplete` helps browsers fill in saved details, e.g. `autocomplete="email"` or `autocomplete="tel"`.
- `autofocus` puts the cursor in the field when the page loads. Use it on at most one field.

## Suggestions with datalist

A `<datalist>` gives a text box a list of suggestions while still allowing free typing.

```html
<label for="city">City</label>
<input id="city" name="city" list="cities">
<datalist id="cities">
  <option value="Nairobi">
  <option value="Mombasa">
  <option value="Kisumu">
  <option value="Nakuru">
</datalist>
```

```exercise
Q: Make the field accept email addresses and refuse to be left empty.
<input type="___" name="email" ___>
A: email | required
H: Use the email type and the boolean attribute that forbids empty fields.
```

```exercise
Q: Group these radio buttons so only one can be chosen.
<input type="radio" name="___" value="small"> Small
<input type="radio" name="size" value="large"> Large
A: size
H: Radios in the same group share the same name.
```
