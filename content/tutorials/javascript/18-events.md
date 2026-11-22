---
title: JavaScript Events
module: 5
minutes: 13
---

An **event** is something that happens on the page: a click, a key press, a form being submitted, the mouse moving over an image, the page finishing loading. JavaScript can *listen* for events and run a function when they happen. This is what makes web pages interactive.

## addEventListener

The standard way to react to an event is `addEventListener`. You give it the event name and a function to call – the **event handler**.

```example html
<!DOCTYPE html>
<html>
<body>
  <button id="likeBtn">Like</button>
  <p id="count">0 likes</p>

  <script>
    let likes = 0;
    const button = document.getElementById("likeBtn");
    const count = document.getElementById("count");

    button.addEventListener("click", () => {
      likes++;
      count.textContent = `${likes} ${likes === 1 ? "like" : "likes"}`;
    });
  </script>
</body>
</html>
```

Click the button a few times. Each click runs the arrow function, which updates the counter and the text.

> **Note:** Notice we pass the function itself, not the result of calling it. Writing `addEventListener("click", update())` would run `update` immediately, once, instead of on each click.

## Common events

| Event | Fires when… |
|---|---|
| `click` | An element is clicked or tapped |
| `dblclick` | An element is double-clicked |
| `input` | The value of an input changes (every keystroke) |
| `change` | A select, checkbox or input value is committed |
| `submit` | A form is submitted |
| `keydown` / `keyup` | A key is pressed / released |
| `mouseover` / `mouseout` | The pointer enters / leaves an element |
| `focus` / `blur` | An input gains / loses focus |
| `DOMContentLoaded` | The HTML has been fully read |

## The event object

Your handler automatically receives an **event object** describing what happened. Common properties include `event.target` (the element the event happened on), `event.type`, and `event.key` for keyboard events.

```example html
<!DOCTYPE html>
<html>
<body>
  <label>Your name: <input id="nameInput"></label>
  <p id="preview">Habari!</p>

  <script>
    const input = document.getElementById("nameInput");
    const preview = document.getElementById("preview");

    input.addEventListener("input", (event) => {
      const value = event.target.value.trim();
      preview.textContent = value ? `Habari, ${value}!` : "Habari!";
    });
  </script>
</body>
</html>
```

Type in the box and watch the greeting update live.

## Handling forms

When a form is submitted, the browser normally reloads the page. Call `event.preventDefault()` to stop that and handle the data with JavaScript instead.

```example html
<!DOCTYPE html>
<html>
<body>
  <form id="topup">
    <label>Phone <input name="phone" required></label><br>
    <label>Amount (KES) <input name="amount" type="number" min="10" required></label><br>
    <button>Buy airtime</button>
  </form>
  <p id="result"></p>

  <script>
    const form = document.getElementById("topup");
    const result = document.getElementById("result");

    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const phone = form.elements.phone.value;
      const amount = Number(form.elements.amount.value);
      result.textContent = `Sending KES ${amount} airtime to ${phone}...`;
      form.reset();
    });
  </script>
</body>
</html>
```

> **Tip:** Remember that input values are always strings. Convert them with `Number()` before doing maths, as you learned in [Numbers & Math](numbers).

## Keyboard events

```example html
<!DOCTYPE html>
<html>
<body>
  <p>Click here, then press any key.</p>
  <p id="key">Last key: none</p>

  <script>
    document.addEventListener("keydown", (event) => {
      document.getElementById("key").textContent = `Last key: ${event.key}`;
    });
  </script>
</body>
</html>
```

## Event bubbling and delegation

When you click an element, the event also travels up through its parents – this is called **bubbling**. A click on a `<li>` is also a click on its `<ul>`, on the `<body>`, and so on.

Bubbling allows a powerful technique called **event delegation**: put *one* listener on a parent and use `event.target` to see which child was clicked. It also works for children added later.

```example html
<!DOCTYPE html>
<html>
<head>
  <style>
    li { cursor: pointer; padding: 4px; }
    .done { text-decoration: line-through; color: gray; }
  </style>
</head>
<body>
  <h3>Shopping list (click to tick)</h3>
  <ul id="list">
    <li>Unga</li>
    <li>Sukari</li>
    <li>Chai leaves</li>
  </ul>
  <button id="add">Add "Maziwa"</button>

  <script>
    const list = document.getElementById("list");

    list.addEventListener("click", (event) => {
      const item = event.target.closest("li");
      if (item) {
        item.classList.toggle("done");
      }
    });

    document.getElementById("add").addEventListener("click", () => {
      const li = document.createElement("li");
      li.textContent = "Maziwa";
      list.append(li);
    });
  </script>
</body>
</html>
```

Add "Maziwa", then click it – it can be ticked even though it did not exist when the listener was set up.

## Removing a listener

To remove a listener, pass the *same* function to `removeEventListener`. That means the handler must have a name. If you only need a listener to run once, use the `{ once: true }` option instead.

```example html
<!DOCTYPE html>
<html>
<body>
  <button id="claim">Claim free bonus</button>
  <p id="out"></p>

  <script>
    document.getElementById("claim").addEventListener("click", () => {
      document.getElementById("out").textContent = "Bonus of KES 50 claimed!";
    }, { once: true });
  </script>
</body>
</html>
```

## Inline handlers vs addEventListener

You may see `onclick="..."` attributes in HTML (introduced in [Where to Put JavaScript](where-to)). `addEventListener` is preferred because it keeps JavaScript out of your HTML and allows several listeners on the same element.

```exercise
Q: Run the handler when the button is clicked.
btn.___("___", handleClick);
A: addEventListener | click
```

```exercise
Q: Stop the form from reloading the page.
form.addEventListener("submit", (e) => {
  e.___();
});
A: preventDefault
```
