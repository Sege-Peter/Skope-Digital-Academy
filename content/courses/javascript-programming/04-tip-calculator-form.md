---
title: "Project: Tip Calculator Form"
module: 2
minutes: 16
---

In Week 1 you wrote the logic for splitting a bill. Now you will give it a face: a form where the user types the bill, picks a tip and enters the number of people. This is the same skill used in loan calculators, delivery fee estimators and booking forms that clients pay developers to build.

## Reading what the user typed

Every `<input>` has a `.value`. The important catch: **`.value` is always a string**, even for `type="number"`. If you add two strings, JavaScript joins them instead of adding.

```example javascript
const a = "500";
const b = "200";

console.log(a + b);
console.log(Number(a) + Number(b));
console.log(Number("abc"));
console.log(Number.isNaN(Number("abc")));
```

```output
500200
700
NaN
true
```

`NaN` means "Not a Number". Always convert with `Number()` and check the result before using it.

## Handling a form submit

Wrap inputs in a `<form>` and listen for the `submit` event. This works when the user clicks the button **or** presses Enter. By default, a form reloads the page when submitted, so we stop that with `event.preventDefault()`.

```javascript
form.addEventListener("submit", function (event) {
  event.preventDefault();
  // read values and show the result here
});
```

## Building the tip calculator

```example html
<!DOCTYPE html>
<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; max-width: 360px; margin: 20px auto; padding: 0 16px; }
    label { display: block; margin-top: 12px; font-weight: bold; }
    input, select, button { width: 100%; padding: 10px; margin-top: 4px; font-size: 16px; box-sizing: border-box; }
    button { background: #2563eb; color: white; border: none; border-radius: 6px; margin-top: 16px; }
    #result { margin-top: 16px; padding: 12px; background: #f1f5f9; border-radius: 6px; }
    .error { color: #dc2626; }
  </style>
</head>
<body>
  <h2>Bill Splitter</h2>
  <form id="tipForm">
    <label for="bill">Bill (KES)</label>
    <input id="bill" type="number" placeholder="e.g. 3200">

    <label for="tip">Tip</label>
    <select id="tip">
      <option value="0">No tip</option>
      <option value="5">5%</option>
      <option value="10" selected>10%</option>
      <option value="15">15%</option>
    </select>

    <label for="people">Number of people</label>
    <input id="people" type="number" value="1" min="1">

    <button type="submit">Calculate</button>
  </form>
  <div id="result">Fill in the form and press Calculate.</div>

  <script>
    const form = document.querySelector("#tipForm");
    const result = document.querySelector("#result");

    function calculateTip(bill, percent) {
      return bill * percent / 100;
    }

    form.addEventListener("submit", function (event) {
      event.preventDefault();

      const bill = Number(document.querySelector("#bill").value);
      const percent = Number(document.querySelector("#tip").value);
      const people = Number(document.querySelector("#people").value);

      if (!bill || bill <= 0) {
        result.innerHTML = '<span class="error">Please enter a bill amount.</span>';
        return;
      }
      if (!people || people < 1) {
        result.innerHTML = '<span class="error">At least 1 person must pay.</span>';
        return;
      }

      const tip = calculateTip(bill, percent);
      const total = bill + tip;
      const each = Math.ceil(total / people);

      result.innerHTML =
        "Tip: KES " + tip.toLocaleString() + "<br>" +
        "Total: KES " + total.toLocaleString() + "<br>" +
        "<strong>Each pays: KES " + each.toLocaleString() + "</strong>";
    });
  </script>
</body>
</html>
```

Try it with an empty bill, then with `3200`, 10% and 4 people.

## What is new here

- **`select` values** work like inputs: `.value` gives the chosen option's `value`.
- **Validation first.** The code checks the inputs and uses `return` to stop early with a friendly message. Users make mistakes; your app should guide them, not show `NaN`.
- **`innerHTML` vs `textContent`.** `innerHTML` lets you insert tags like `<br>` and `<strong>`. Only use it with text *you* wrote. Never put something a user typed straight into `innerHTML`, because a user could type HTML or a script into it. For user text, use `textContent`.
- **`toLocaleString()`** formats `3520` as `3,520`, which looks professional.

> **Note:** `!bill` is true when `bill` is `0` or `NaN`, so one check catches both an empty box and nonsense input.

```exercise
Q: Convert the input value to a number before adding.
const price = ___(priceInput.value);
A: Number
```

```exercise
Q: Stop the form from reloading the page.
form.addEventListener("___", function (event) {
  event.___();
});
A: submit | preventDefault
```

## Practice task

Turn the calculator into a **delivery fee estimator** for a boda boda business in Kakamega: inputs for distance in km and number of parcels. Charge KES 100 as a base fee plus KES 30 per km, and KES 20 for each extra parcel after the first. Show a clear error if distance is empty or zero. These prices are just examples; a real client would give you theirs.
