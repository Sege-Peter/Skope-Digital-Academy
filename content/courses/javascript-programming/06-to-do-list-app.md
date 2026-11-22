---
title: "Project: A To-Do List"
module: 3
minutes: 18
---

Now you combine the DOM (Week 2) with arrays and objects (last lesson) to build a **to-do list**. You can add tasks, tick them off and delete them. The same pattern powers shopping carts, order lists and booking tables.

## The pattern: data first, then draw

Beginners often add and remove HTML elements directly, and the page soon gets out of sync. Professionals keep the **data in an array** and write one function that **draws the whole list** from that array:

1. `tasks` is an array of objects: `{ text: "Buy airtime", done: false }`.
2. `render()` clears the list on the page and rebuilds it from `tasks`.
3. Every button changes `tasks`, then calls `render()`.

It is the same idea as `count` and `show()` in the counter app, just with a list.

## Creating elements with JavaScript

To build list items in code, you create elements and attach them to the page:

```javascript
const li = document.createElement("li");   // make a new <li>
li.textContent = "Revise for interview";    // give it text
list.appendChild(li);                       // put it inside the <ul>
```

Using `textContent` for task text keeps you safe: whatever the user types is shown as plain text, never run as code.

## Building the app

```example html
<!DOCTYPE html>
<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; max-width: 420px; margin: 20px auto; padding: 0 16px; }
    form { display: flex; gap: 6px; }
    input { flex: 1; padding: 10px; font-size: 16px; }
    button { padding: 8px 12px; cursor: pointer; }
    ul { list-style: none; padding: 0; }
    li { display: flex; align-items: center; gap: 8px; padding: 8px; border-bottom: 1px solid #e2e8f0; }
    li span { flex: 1; }
    li.done span { text-decoration: line-through; color: #94a3b8; }
  </style>
</head>
<body>
  <h2>My Tasks</h2>
  <form id="addForm">
    <input id="taskInput" placeholder="e.g. Send CV to Achieng">
    <button type="submit">Add</button>
  </form>
  <ul id="list"></ul>
  <p id="summary"></p>

  <script>
    const tasks = [
      { text: "Buy airtime", done: true },
      { text: "Practise JavaScript for 30 minutes", done: false }
    ];

    const list = document.querySelector("#list");
    const input = document.querySelector("#taskInput");

    function render() {
      list.innerHTML = "";
      tasks.forEach(function (task, index) {
        const li = document.createElement("li");
        if (task.done) li.classList.add("done");

        const box = document.createElement("input");
        box.type = "checkbox";
        box.checked = task.done;
        box.addEventListener("change", function () {
          tasks[index].done = box.checked;
          render();
        });

        const span = document.createElement("span");
        span.textContent = task.text;

        const del = document.createElement("button");
        del.textContent = "Delete";
        del.addEventListener("click", function () {
          tasks.splice(index, 1);
          render();
        });

        li.append(box, span, del);
        list.appendChild(li);
      });

      const left = tasks.filter(t => !t.done).length;
      document.querySelector("#summary").textContent = `${left} of ${tasks.length} tasks left`;
    }

    document.querySelector("#addForm").addEventListener("submit", function (event) {
      event.preventDefault();
      const text = input.value.trim();
      if (text === "") return;
      tasks.push({ text: text, done: false });
      input.value = "";
      render();
    });

    render();
  </script>
</body>
</html>
```

## How it works

- **`forEach(function (task, index) {...})`** loops over the array and also gives the position of each task. The delete button uses that position.
- **`splice(index, 1)`** removes one item at that position.
- **`trim()`** removes spaces at the start and end, so a task of only spaces is ignored.
- **`classList.add("done")`** adds a CSS class, and the CSS crosses out the text. Let CSS handle the look; let JavaScript decide which class applies.
- **`render()` at the bottom** draws the starting tasks when the page first loads.

> **Warning:** Refresh the page and your new tasks disappear. The `tasks` array lives in memory and is rebuilt from the code each time. In the next lesson you fix this with `localStorage`.

## Test like a developer

Before calling a project "done", try to break it:

- Add an empty task. Add one with only spaces.
- Delete the first task, then the last one.
- Tick a task, untick it. Does the summary count change correctly?

Clients notice bugs fast. Testing your own work is part of the job.

```exercise
Q: Create a new list item element.
const li = document.___("li");
A: createElement
```

```exercise
Q: Remove one task at position index from the array.
tasks.___(index, ___);
A: splice | 1
```

## Practice task

Turn the to-do list into a **market shopping list** for a trip to Gikomba or your local market. Each item should have a name and a quantity (two inputs). Show items as `Tomatoes x3`, and add a "Clear bought items" button that uses `filter` to remove all ticked items at once. (Hint: `filter` returns a new array, so declare your list with `let` and write `items = items.filter(...)`.)
