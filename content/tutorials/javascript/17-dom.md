---
title: The DOM – Selecting & Changing Elements
module: 5
module_title: JavaScript in the Browser
minutes: 14
---

When a browser loads an HTML page, it builds a live model of it in memory called the **DOM** (Document Object Model). Every element – each heading, paragraph, button and image – becomes an object that JavaScript can find, read and change. Change the DOM, and the page on screen updates instantly.

## The document as a tree

The DOM is organised like a family tree. `<html>` is the root; it has children `<head>` and `<body>`; `<body>` has its own children, and so on.

```html
<body>
  <h1>Skope Shop</h1>
  <ul>
    <li>Maize flour</li>
    <li>Cooking oil</li>
  </ul>
</body>
```

Here `<ul>` is the *parent* of the two `<li>` elements, and the `<li>` elements are *siblings*. JavaScript reaches all of this through the global `document` object.

## Selecting elements

Before you can change an element, you need a reference to it.

| Method | Returns |
|---|---|
| `document.getElementById("id")` | The element with that id, or `null` |
| `document.querySelector("css")` | The **first** element matching a CSS selector, or `null` |
| `document.querySelectorAll("css")` | **All** matching elements (a NodeList) |
| `document.getElementsByClassName("c")` | All elements with that class (live collection) |

`querySelector` and `querySelectorAll` accept any CSS selector you already know – `#id`, `.class`, `tag`, `ul li`, `[type="email"]` – which makes them the most flexible choice.

```example html
<!DOCTYPE html>
<html>
<body>
  <h2 id="title">Today's prices</h2>
  <p class="price">Tomatoes: KES 80/kg</p>
  <p class="price">Onions: KES 120/kg</p>
  <p class="price">Potatoes: KES 70/kg</p>

  <script>
    const title = document.getElementById("title");
    title.style.color = "darkgreen";

    const first = document.querySelector(".price");
    first.style.fontWeight = "bold";

    const all = document.querySelectorAll(".price");
    all.forEach((p) => {
      p.textContent += " (Marikiti market)";
    });

    console.log(`Found ${all.length} prices`);
  </script>
</body>
</html>
```

> **Note:** If a selector matches nothing, `getElementById` and `querySelector` return `null`. Trying to change `null.textContent` throws an error, so check that the script runs after the elements exist (see [Where to Put JavaScript](where-to)).

## Changing content

- `textContent` gets or sets the plain text inside an element.
- `innerHTML` gets or sets the HTML inside an element – only use it with HTML you wrote yourself, never with user input.

```example html
<!DOCTYPE html>
<html>
<body>
  <p id="status">Checking...</p>
  <div id="box"></div>

  <script>
    document.getElementById("status").textContent = "Order confirmed";
    document.getElementById("box").innerHTML =
      "<em>Rider:</em> <strong>Kevin</strong> on a boda boda";
  </script>
</body>
</html>
```

## Changing attributes

Many attributes are available directly as properties, such as `src`, `href`, `alt`, `value` and `disabled`. For any attribute you can also use `getAttribute` and `setAttribute`.

```example html
<!DOCTYPE html>
<html>
<body>
  <a id="link" href="#">Visit us</a>
  <input id="phone" value="0700000000">
  <button id="pay">Pay</button>

  <script>
    const link = document.getElementById("link");
    link.href = "https://www.example.com";
    link.setAttribute("target", "_blank");

    document.getElementById("phone").value = "0722111222";
    document.getElementById("pay").disabled = true;

    console.log(link.getAttribute("target"));
  </script>
</body>
</html>
```

## Changing styles and classes

You can change inline styles with the `style` property. CSS property names become camelCase: `background-color` becomes `backgroundColor`.

A cleaner approach is to define styles in CSS and switch **classes** on and off with `classList`:

| Method | Effect |
|---|---|
| `el.classList.add("x")` | Adds the class |
| `el.classList.remove("x")` | Removes the class |
| `el.classList.toggle("x")` | Adds it if missing, removes it if present |
| `el.classList.contains("x")` | `true` or `false` |

```example html
<!DOCTYPE html>
<html>
<head>
  <style>
    .alert { padding: 10px; border-radius: 6px; }
    .success { background: #d4edda; color: #155724; }
    .hidden { display: none; }
  </style>
</head>
<body>
  <div id="msg" class="alert">Payment received</div>
  <p id="secret">You can't see me</p>

  <script>
    document.getElementById("msg").classList.add("success");
    document.getElementById("secret").classList.add("hidden");
  </script>
</body>
</html>
```

> **Tip:** Prefer `classList` over setting many `style` properties. It keeps your design in CSS, where it belongs, and makes changes easy to undo.

## Creating and removing elements

To add new content, create an element, fill it in, and attach it to the page.

```example html
<!DOCTYPE html>
<html>
<body>
  <h3>Delivery towns</h3>
  <ul id="towns"></ul>

  <script>
    const towns = ["Nairobi", "Machakos", "Kitui", "Embu"];
    const list = document.getElementById("towns");

    for (const town of towns) {
      const li = document.createElement("li");
      li.textContent = town;
      list.append(li);
    }

    // Remove the last one
    list.lastElementChild.remove();
    console.log(`${list.children.length} towns listed`);
  </script>
</body>
</html>
```

The main methods are:

- `document.createElement("tag")` – make a new element (not yet on the page).
- `parent.append(child)` – add it as the last child. `prepend` adds it first.
- `element.remove()` – take an element off the page.

## Moving around the tree

From any element you can move to its relatives: `parentElement`, `children`, `firstElementChild`, `lastElementChild`, `nextElementSibling` and `previousElementSibling`. There is also `element.closest("selector")`, which searches upwards for the nearest matching ancestor – very useful with [Events](events).

```example javascript
document.body.innerHTML = `
  <div class="card">
    <h4>Nyama choma platter</h4>
    <p>KES 1,200</p>
  </div>`;

const price = document.querySelector(".card p");
console.log(price.textContent);
console.log(price.parentElement.className);
console.log(price.previousElementSibling.textContent);
```

```output
KES 1,200
card
Nyama choma platter
```

```exercise
Q: Select the first element with the class "menu".
const menu = document.___(".menu");
A: querySelector
```

```exercise
Q: Add the class "active" to the button.
button.___.add("active");
A: classList
```
