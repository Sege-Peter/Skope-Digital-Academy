---
title: Saving Data with localStorage
module: 3
minutes: 15
---

Your to-do list forgets everything when the page reloads. Real apps remember. Big apps save data on a server, but browsers also give every website a small private notebook called **localStorage**. It is perfect for settings, drafts, a shopping cart or a personal to-do list.

## How localStorage works

localStorage stores **text only**, as `key` and `value` pairs, on the user's own device. It stays there after the browser is closed.

| Code | What it does |
|---|---|
| `localStorage.setItem("name", "Fatuma")` | save a value under a key |
| `localStorage.getItem("name")` | read it back (`null` if missing) |
| `localStorage.removeItem("name")` | delete one key |

Because each visitor's data stays on their own device, Achieng's tasks never mix with Brian's, even on the same website.

> **Warning:** localStorage is not secure. Anyone using that browser can read it, so never store passwords, M-Pesa PINs or ID numbers there.

## Saving arrays and objects with JSON

Since localStorage only stores text, you convert your data to text first with **JSON** (a text format for data):

- `JSON.stringify(data)` turns an array or object into a string.
- `JSON.parse(text)` turns that string back into real data.

```example javascript
const tasks = [
  { text: "Buy airtime", done: true },
  { text: "Call Kiprono", done: false }
];

const saved = JSON.stringify(tasks);
console.log(saved);
console.log(typeof saved);

const loaded = JSON.parse(saved);
console.log(loaded[1].text);
console.log(loaded.length);
```

```output
[{"text":"Buy airtime","done":true},{"text":"Call Kiprono","done":false}]
string
Call Kiprono
2
```

## Load safely with try...catch

Saved data can be missing (first visit), damaged, or storage can be blocked (some private browsing modes). Code that crashes in those cases leaves the user with a blank page. `try...catch` runs risky code and, if it fails, lets you recover:

```example javascript
function loadTasks(text) {
  try {
    const data = JSON.parse(text);
    return Array.isArray(data) ? data : [];
  } catch (error) {
    return [];
  }
}

console.log(loadTasks('[{"text":"Revise","done":false}]').length);
console.log(loadTasks("this is broken").length);
console.log(loadTasks(null).length);
```

```output
1
0
0
```

Whatever happens, `loadTasks` returns an array, so the rest of the app keeps working.

## A note counter that remembers

This page counts how many times you have visited it and remembers a short note.

> **Note:** In this site's editor, the preview gives your code a **temporary in-memory storage** that resets when you run the code again or close the editor. That is normal. Save the code as an `.html` file on your computer and open it in Chrome to see data survive a real page refresh.

```example html
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; padding: 16px;">
  <h2>My Notes</h2>
  <p id="visits"></p>
  <textarea id="note" rows="4" style="width: 100%;" placeholder="Type a note..."></textarea>
  <button id="save">Save note</button>
  <p id="status"></p>

  <script>
    function readStore(key) {
      try { return localStorage.getItem(key); } catch (e) { return null; }
    }
    function writeStore(key, value) {
      try { localStorage.setItem(key, value); return true; } catch (e) { return false; }
    }

    const visits = Number(readStore("visits") || 0) + 1;
    writeStore("visits", String(visits));
    document.querySelector("#visits").textContent = `You have opened this page ${visits} time(s).`;

    const note = document.querySelector("#note");
    note.value = readStore("note") || "";

    document.querySelector("#save").addEventListener("click", function () {
      const ok = writeStore("note", note.value);
      document.querySelector("#status").textContent = ok ? "Saved!" : "Could not save on this browser.";
    });
  </script>
</body>
</html>
```

`readStore` and `writeStore` are small **wrapper functions**. They hide the `try...catch` so the rest of the code stays clean, and the page still works when storage is blocked.

## Adding it to your to-do list

Two changes are enough:

```javascript
// when the app starts
let tasks = loadTasks(readStore("tasks"));

// at the end of render()
writeStore("tasks", JSON.stringify(tasks));
```

Because every change already calls `render()`, every change is now saved too. This is the payoff of the "data first, then draw" pattern.

```exercise
Q: Turn the tasks array into text, then save it.
localStorage.setItem("tasks", JSON.___(tasks));
A: stringify
```

```exercise
Q: Read the saved text and turn it back into an array.
const tasks = JSON.___(localStorage.___("tasks"));
A: parse | getItem
```

## Practice task

Add saving to the to-do list from [the to-do list project](to-do-list-app): add `readStore`, `writeStore` and `loadTasks`, load tasks at start-up, and save at the end of `render()`. Save the file as `todo.html`, open it in your browser, add three tasks and refresh. They should still be there.
