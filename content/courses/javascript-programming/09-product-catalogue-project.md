---
title: "Final Project: Product Catalogue"
module: 4
minutes: 22
---

Small businesses in Kenya increasingly sell through WhatsApp, Instagram and simple websites. A **product catalogue** that customers can search and filter is one of the most requested beginner web jobs. In this final project you build one for an imaginary electronics shop in Eldoret, using everything from the course.

## The plan

| Feature | Skill used |
|---|---|
| Products stored as data | arrays of objects |
| Search box and category filter | input events, `filter()` |
| Sort by price | `sort()` |
| Product cards drawn on the page | DOM, `createElement`, `render()` |
| "Order on WhatsApp" link | template strings |

## Step 1: test the filter logic first

As in Week 1, get the logic right in the console before building the page.

```example javascript
const products = [
  { name: "Tecno Spark 20", category: "Phones", price: 15500 },
  { name: "Oraimo Earbuds", category: "Audio", price: 2499 },
  { name: "Samsung A15", category: "Phones", price: 24000 },
  { name: "JBL Go Speaker", category: "Audio", price: 4800 }
];

function filterProducts(list, search, category) {
  return list.filter(p =>
    p.name.toLowerCase().includes(search.toLowerCase()) &&
    (category === "All" || p.category === category)
  );
}

console.log(filterProducts(products, "", "Audio").map(p => p.name));
console.log(filterProducts(products, "sam", "All").map(p => p.name));

const byPrice = [...products].sort((a, b) => a.price - b.price);
console.log(byPrice.map(p => p.price));
```

```output
[ 'Oraimo Earbuds', 'JBL Go Speaker' ]
[ 'Samsung A15' ]
[ 2499, 4800, 15500, 24000 ]
```

- `toLowerCase()` on both sides makes the search ignore capitals, so "sam" finds "Samsung".
- `includes()` checks whether one string contains another.
- `sort((a, b) => a.price - b.price)` sorts cheapest first. `[...products]` makes a copy so the original order is kept.

## Step 2: the full app

```example html
<!DOCTYPE html>
<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 16px; background: #f8fafc; }
    h1 { margin-top: 0; }
    .controls { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
    .controls input, .controls select { padding: 10px; font-size: 15px; flex: 1; min-width: 140px; }
    #grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
    .card { background: white; border-radius: 10px; padding: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
    .card h3 { font-size: 16px; margin: 0 0 6px; }
    .tag { font-size: 12px; color: #64748b; }
    .price { font-weight: bold; color: #16a34a; margin: 8px 0; }
    .card a { display: inline-block; font-size: 14px; color: white; background: #22c55e; padding: 6px 10px; border-radius: 6px; text-decoration: none; }
    .out { color: #dc2626; font-size: 13px; }
  </style>
</head>
<body>
  <h1>Eldoret Gadget Hub</h1>
  <div class="controls">
    <input id="search" placeholder="Search products...">
    <select id="category"></select>
    <select id="sort">
      <option value="none">Sort: default</option>
      <option value="low">Price: low to high</option>
      <option value="high">Price: high to low</option>
    </select>
  </div>
  <p id="count"></p>
  <div id="grid"></div>

  <script>
    const products = [
      { name: "Tecno Spark 20", category: "Phones", price: 15500, inStock: true },
      { name: "Samsung A15", category: "Phones", price: 24000, inStock: true },
      { name: "Nokia C32", category: "Phones", price: 12800, inStock: false },
      { name: "Oraimo Earbuds", category: "Audio", price: 2499, inStock: true },
      { name: "JBL Go Speaker", category: "Audio", price: 4800, inStock: true },
      { name: "20,000mAh Power Bank", category: "Accessories", price: 2800, inStock: true },
      { name: "Fast Charger 25W", category: "Accessories", price: 1200, inStock: false }
    ];
    const shopPhone = "254700000000"; // placeholder: use the client's real WhatsApp number

    const searchEl = document.querySelector("#search");
    const categoryEl = document.querySelector("#category");
    const sortEl = document.querySelector("#sort");
    const grid = document.querySelector("#grid");

    // Build the category options from the data itself
    const categories = ["All", ...new Set(products.map(p => p.category))];
    categories.forEach(function (c) {
      const option = document.createElement("option");
      option.value = c;
      option.textContent = c;
      categoryEl.appendChild(option);
    });

    function render() {
      const search = searchEl.value.trim().toLowerCase();
      const category = categoryEl.value;

      let list = products.filter(p =>
        p.name.toLowerCase().includes(search) &&
        (category === "All" || p.category === category)
      );
      if (sortEl.value === "low") list = [...list].sort((a, b) => a.price - b.price);
      if (sortEl.value === "high") list = [...list].sort((a, b) => b.price - a.price);

      grid.innerHTML = "";
      list.forEach(function (p) {
        const card = document.createElement("div");
        card.className = "card";

        const title = document.createElement("h3");
        title.textContent = p.name;
        const tag = document.createElement("div");
        tag.className = "tag";
        tag.textContent = p.category;
        const price = document.createElement("div");
        price.className = "price";
        price.textContent = "KES " + p.price.toLocaleString();
        card.append(title, tag, price);

        if (p.inStock) {
          const link = document.createElement("a");
          const message = `Hello, I would like to order: ${p.name} (KES ${p.price})`;
          link.href = `https://wa.me/${shopPhone}?text=${encodeURIComponent(message)}`;
          link.target = "_blank";
          link.textContent = "Order on WhatsApp";
          card.appendChild(link);
        } else {
          const out = document.createElement("div");
          out.className = "out";
          out.textContent = "Out of stock";
          card.appendChild(out);
        }
        grid.appendChild(card);
      });

      document.querySelector("#count").textContent =
        list.length === 0 ? "No products match your search." : `Showing ${list.length} of ${products.length} products`;
    }

    searchEl.addEventListener("input", render);
    categoryEl.addEventListener("change", render);
    sortEl.addEventListener("change", render);
    render();
  </script>
</body>
</html>
```

## Key ideas

- The **`input` event** fires on every key press, so results update as the customer types.
- **`new Set(...)`** removes duplicates, so the category list builds itself from the data. Add a "Laptops" product and a new option appears automatically.
- **`encodeURIComponent()`** makes the message safe to put in a link (spaces and brackets become web-safe codes).
- All three controls call the same `render()`. One function, one source of truth.

> **Note:** The WhatsApp number is a placeholder. For a real client, use their business number and confirm prices with them; never invent stock or prices on a live shop.

```exercise
Q: Make the search ignore capital letters.
p.name.___().includes(search)
A: toLowerCase
```

```exercise
Q: Sort a copy of the list from the highest price to the lowest.
[...list].sort((a, b) => ___.price - ___.price)
A: b | a
H: Lowest-first is a.price - b.price. Swap them.
```

## Practice task

Make the catalogue your own portfolio piece: pick a real kind of business near you (a cereals shop, a boutique, a hardware) and enter 8–10 products with sensible example prices. Add one new feature of your choice, such as a "max price" input or an "in stock only" checkbox. Save it as `catalogue.html`. You will present it in the final lesson.
