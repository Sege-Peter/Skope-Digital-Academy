---
title: Forms and Accessibility
module: 3
minutes: 14
---

A form turns a visitor into a customer: a cake order, a booking or a "call me back" request. And **accessibility** makes sure every visitor can use your site, including people who are blind, have low vision, cannot use a mouse, or are on a small phone in bright sunlight. The two topics belong together, because forms are where accessibility mistakes hurt most.

## Building an order form

The most important form elements:

| Element | Use |
|---|---|
| `<form>` | Wraps the whole form |
| `<label>` | The visible name of a field, connected to it with `for` and `id` |
| `<input type="text">` | Short text, such as a name |
| `<input type="tel">` | Phone numbers; opens the number keypad on phones |
| `<input type="email">` | Email addresses; checks for an @ sign |
| `<input type="date">` | A date picker |
| `<select>` | A drop-down list of options |
| `<textarea>` | Longer messages |
| `<button>` | Sends the form |

The `required` attribute stops the form from being sent until the field is filled in.

## A working cake order form

A normal form needs a server (for example a PHP script, or a form service) to receive and store the data. To keep things simple, this form instead creates a WhatsApp message for the bakery, a popular approach for small Kenyan businesses. Fill it in and press the button: a short script builds the message and shows the WhatsApp link.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Order a Cake</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #fffaf3; color: #333; padding: 16px; line-height: 1.5; }
    form { max-width: 420px; background: #fff; border: 1px solid #ecd9c3; border-radius: 8px; padding: 20px; }
    label { display: block; font-weight: bold; margin-top: 14px; }
    input, select, textarea { width: 100%; padding: 10px; font-size: 16px; border: 1px solid #999; border-radius: 6px; margin-top: 4px; }
    input:focus, select:focus, textarea:focus, button:focus { outline: 3px solid #f0a33a; outline-offset: 1px; }
    button { margin-top: 18px; width: 100%; padding: 14px; font-size: 16px; font-weight: bold;
             background: #1e7b34; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
    .hint { font-size: 14px; color: #555; }
    #result { margin-top: 16px; word-break: break-all; }
  </style>
</head>
<body>
  <h1>Order a cake</h1>
  <form id="order">
    <label for="name">Your name</label>
    <input type="text" id="name" required autocomplete="name">

    <label for="phone">Phone number</label>
    <input type="tel" id="phone" required autocomplete="tel" aria-describedby="phone-hint">
    <span class="hint" id="phone-hint">For example 0712 345 678</span>

    <label for="cake">Cake</label>
    <select id="cake">
      <option>Vanilla</option>
      <option>Chocolate</option>
      <option>Red velvet</option>
    </select>

    <label for="date">Collection date</label>
    <input type="date" id="date" required>

    <label for="notes">Message on the cake (optional)</label>
    <textarea id="notes" rows="3"></textarea>

    <button type="submit">Create WhatsApp order</button>
  </form>
  <p id="result" role="status"></p>

  <script>
    document.getElementById("order").addEventListener("submit", function (event) {
      event.preventDefault();
      const text = "Hello Baraka Bakery, I would like to order a " +
        document.getElementById("cake").value + " cake for " +
        document.getElementById("date").value + ". Name: " +
        document.getElementById("name").value + ", phone: " +
        document.getElementById("phone").value + ". Message on cake: " +
        (document.getElementById("notes").value || "none");
      const link = "https://wa.me/254700000000?text=" + encodeURIComponent(text);
      document.getElementById("result").innerHTML =
        'Ready! <a href="' + link + '" target="_blank">Open WhatsApp to send your order</a>';
    });
  </script>
</body>
</html>
```

> **Warning:** Never ask for passwords, M-Pesa PINs or ID numbers on a simple order form. Collect only what the business needs, and tell visitors how their details will be used.

## Accessibility basics

Accessibility (often written **a11y**) is about removing barriers. Many fixes are small:

| Do this | Because |
|---|---|
| Give every field a `<label>` | Screen readers read the label; tapping it also focuses the field |
| Write useful `alt` text on images | Blind users hear what the image shows |
| Use headings in order (`h1`, `h2`, `h3`) | Screen reader users jump between headings to navigate |
| Keep strong colour contrast | People with low vision, and everyone in sunlight, can read it |
| Never rely on colour alone | "Fields in red are required" fails for colour-blind users; add text or an asterisk with an explanation |
| Make everything work with the keyboard | Some people cannot use a mouse or touch screen |
| Keep a visible focus outline | Keyboard users need to see where they are |
| Use clear link text | "Download our price list" beats "click here" |
| Set `lang` on `<html>` | Screen readers pronounce the words correctly |

## Test it yourself

- Press **Tab** repeatedly on your page. Can you reach every link, field and button, in a sensible order, and see where you are?
- Zoom the browser to 200%. Does everything still fit and work?
- Run **Lighthouse** in Chrome DevTools (F12) and check the Accessibility score and suggestions.

> **Note:** An accessible site is usually also easier for *everyone*: bigger buttons, clearer text and simpler forms help an elderly customer just as much as a screen reader user.

```exercise
Q: Complete the label so it is connected to the input with id "phone".
<label ___="phone">Phone number</label>
A: for
H: The label is "for" that field.
```

```exercise
Q: Fill in the input type that opens the number keypad on phones for phone numbers.
<input type="___" id="phone">
A: tel
H: Short for telephone.
```

## Practice task

Add a contact or order form to your business site with at least four labelled fields and one `required` field. Then do the keyboard test: using only the Tab, Enter and arrow keys, fill in the form and press the button. Fix anything you cannot reach or cannot see.
