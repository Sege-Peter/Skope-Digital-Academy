---
title: Planning and Design: Wireframes and Flowcharts
module: 2
module_title: Planning and Version Control
minutes: 12
---

Once you know *what* to build, you decide *how* it will look and work. Ten minutes with a pencil can save ten hours of rewriting code. In this lesson you will use two simple planning tools: **wireframes** for screens and **flowcharts** for logic.

## Wireframes: sketching screens

A **wireframe** is a rough, black-and-white sketch of a screen. It shows where things go (header, buttons, form fields) without colours, photos or fonts. The goal is to agree on layout and content before anyone spends time on styling.

Here is a text wireframe for the "record a sale" screen of Brian's hardware shop:

```text
+--------------------------------------+
|  KISII HARDWARE        [Logout]      |
+--------------------------------------+
|  Record a sale                       |
|                                      |
|  Item:      [ Cement 50kg      v ]   |
|  Quantity:  [ 2 ]                    |
|  Payment:   (o) Cash  ( ) M-Pesa     |
|                                      |
|  Total:     KES 1,560                |
|                                      |
|          [  Save sale  ]             |
+--------------------------------------+
```

You can draw wireframes on paper, on a whiteboard, or with free tools such as Figma, Excalidraw or Canva. Paper is perfect for a first version.

> **Tip:** Sketch the phone version first. Most Kenyan users will open your app on a smartphone, and a layout that works on a small screen is easy to stretch for a laptop.

### Low and high fidelity

| Type | Looks like | When to use it |
|---|---|---|
| Low-fidelity wireframe | Boxes and labels, hand-drawn | Early ideas, quick client feedback |
| High-fidelity mockup | Real colours, fonts and images | After the layout is agreed |
| Prototype | Clickable mockup | Testing the flow with real users |

## Flowcharts: planning the logic

A **flowchart** shows the steps and decisions in a process. Standard shapes keep it readable:

| Shape | Meaning |
|---|---|
| Oval | Start or end |
| Rectangle | A step or action |
| Diamond | A decision (yes/no question) |
| Arrow | The direction of flow |

Here is the logic for saving a sale, written as a text flowchart:

```text
(Start)
   |
[Read item and quantity]
   |
<Is quantity more than 0?> --No--> [Show "Enter a quantity"] --> (End)
   | Yes
<Is there enough stock?> --No--> [Show "Not enough stock"] --> (End)
   | Yes
[Reduce stock, save sale]
   |
[Show "Sale saved"]
   |
(End)
```

## From flowchart to code

Each diamond becomes an `if`, and each rectangle becomes a line of code. Compare the flowchart above with this function:

```example javascript
const stock = { "Cement 50kg": 4 };

function saveSale(item, quantity) {
  if (quantity <= 0) {
    return "Enter a quantity";
  }
  if (stock[item] < quantity) {
    return "Not enough stock";
  }
  stock[item] = stock[item] - quantity;
  return "Sale saved";
}

console.log(saveSale("Cement 50kg", 0));
console.log(saveSale("Cement 50kg", 6));
console.log(saveSale("Cement 50kg", 3));
console.log("Cement left:", stock["Cement 50kg"]);
```

```output
Enter a quantity
Not enough stock
Sale saved
Cement left: 1
```

Because the decisions were planned first, writing the code was almost a translation exercise.

## Planning the data

Before coding, also list what information you will store. For the shop that might be:

```text
Item:  name, price, quantity_in_stock
Sale:  item, quantity, total, payment_method, date, staff_member
```

This simple list later becomes your database tables.

```exercise
Q: Fill in the flowchart shape used for a yes/no decision.
A decision is drawn as a ___.
A: diamond
H: It has four corners and looks like a rotated square.
```

```exercise
Q: Complete the name of a rough black-and-white sketch of a screen.
A ___ shows layout without colours or photos.
A: wireframe
H: Wire + frame.
```

## Practice task

For your own app idea, draw a **paper wireframe** of the most important screen (phone size), and a **flowchart** for one action on that screen, with at least two decisions. Take a clear photo of both; they are the first items for your portfolio.
