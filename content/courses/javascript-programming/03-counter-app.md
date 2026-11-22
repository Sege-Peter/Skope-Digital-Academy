---
title: "Project: A Counter App"
module: 2
module_title: Pages That React
minutes: 15
---

This week your code leaves the console and starts changing real web pages. Your first project is a **counter app**: a number with buttons to add and subtract. A shop attendant could use it to count customers at the door, or a matatu tout to count passengers.

## The DOM: the page as objects

When a browser loads HTML, it builds a family tree of every element on the page. This tree is called the **DOM** (Document Object Model). JavaScript can reach into the DOM, read elements and change them.

The three things you will do again and again:

| Step | Code |
|---|---|
| 1. Find an element | `document.getElementById("count")` or `document.querySelector("#count")` |
| 2. Change it | `el.textContent = "5"` or `el.style.color = "green"` |
| 3. Listen for actions | `button.addEventListener("click", function () { ... })` |

`querySelector` uses CSS selectors: `#id` for an id, `.name` for a class, `p` for a tag.

## Events: reacting to the user

An **event** is something that happens on the page: a click, a key press, typing in a box. `addEventListener` says "when this event happens on this element, run this function".

```example html
<!DOCTYPE html>
<html>
<body>
  <button id="greet">Say hello</button>
  <p id="out"></p>
  <script>
    const button = document.querySelector("#greet");
    const out = document.querySelector("#out");

    button.addEventListener("click", function () {
      out.textContent = "Habari yako, Wanjiku!";
    });
  </script>
</body>
</html>
```

Nothing happens until you click. That is the core idea of interactive pages: set things up, then wait for the user.

## Building the counter

Plan before you code:

1. A variable `count` that starts at 0.
2. Three buttons: minus, reset, plus.
3. Each button changes `count`, then a function `show()` updates the page.

```example html
<!DOCTYPE html>
<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; text-align: center; padding: 30px; }
    #count { font-size: 64px; margin: 20px 0; }
    button { font-size: 20px; padding: 10px 18px; margin: 4px; border: none; border-radius: 8px; cursor: pointer; }
    .plus { background: #16a34a; color: white; }
    .minus { background: #dc2626; color: white; }
  </style>
</head>
<body>
  <h2>Customers today</h2>
  <div id="count">0</div>
  <button class="minus" id="minus">-</button>
  <button id="reset">Reset</button>
  <button class="plus" id="plus">+</button>

  <script>
    let count = 0;
    const display = document.querySelector("#count");

    function show() {
      display.textContent = count;
      display.style.color = count === 0 ? "black" : "#16a34a";
    }

    document.querySelector("#plus").addEventListener("click", function () {
      count = count + 1;
      show();
    });

    document.querySelector("#minus").addEventListener("click", function () {
      if (count > 0) {
        count = count - 1;
      }
      show();
    });

    document.querySelector("#reset").addEventListener("click", function () {
      count = 0;
      show();
    });
  </script>
</body>
</html>
```

Two details worth noticing:

- The minus button checks `count > 0`, so the counter never goes negative. You cannot have minus three customers!
- `condition ? a : b` is a short if/else called the **ternary operator**. Here it makes the number green once it is above zero.

> **Tip:** Keep one variable as the "truth" (`count`) and one function that draws it on the page (`show()`). Every button only changes the variable and then calls `show()`. This pattern will carry you through every project in this course.

## Common mistakes

- **Script before the HTML.** If your `<script>` runs before the button exists, `querySelector` returns `null` and you get an error. Keep scripts at the end of `<body>`.
- **Typos in ids.** `"#Plus"` and `"#plus"` are different.
- **Forgetting to call `show()`.** The variable changes but the screen does not.

When something does not work, add `console.log(count)` inside the click function to see what is happening.

```exercise
Q: Complete the code so clicking the button runs the function.
btn.___("click", function () {
  count = count + 1;
});
A: addEventListener
```

```exercise
Q: Select the element with id "total" and set its text to 0.
document.querySelector("___total").___ = 0;
A: # | textContent
H: Ids in CSS selectors start with a special character.
```

## Practice task

Change the counter into a **water bottle tracker** for a small shop in Kisii: a "+" button for bottles sold, and a line under the number that shows the money made at KES 50 per bottle (for example `Sales: KES 350`). Bonus: add a "+5" button for crate sales.
