---
title: The Skope Shop Database
module: 1
minutes: 9
---

Before writing real queries, it helps to know the data you are querying. Every example in this tutorial uses the **Skope Shop** database: a small business that sells computer accessories and office equipment to companies across East Africa.

The database has four tables:

| Table | One row is… | Rows |
|---|---|---|
| `customers` | a business that buys from Skope Shop | 12 |
| `products` | an item Skope Shop sells | 12 |
| `orders` | one order placed by a customer | 12 |
| `order_items` | one product line inside an order | 20 |

## The customers table

```example sql
SELECT * FROM customers;
```

| Column | Meaning |
|---|---|
| `customer_id` | a unique number for each customer (the **primary key**) |
| `name` | the business name, e.g. Lakeside Books |
| `contact` | the person we deal with |
| `city`, `country` | where the customer is based |
| `joined` | the date they became a customer, written as `YYYY-MM-DD` |

Most customers are in Kenya, but there are also customers in Uganda, Tanzania and Rwanda. Look carefully and you will see that one customer, Nyeri Stationers, has no country recorded. That empty value is called `NULL`, and it gets its own lesson: [NULL Values](null-values).

## The products table

```example sql
SELECT * FROM products;
```

| Column | Meaning |
|---|---|
| `product_id` | unique number for each product |
| `name` | product name |
| `category` | Accessories, Displays, Video, Audio, Storage or Office |
| `price` | price of one unit in Kenya shillings (KSh) |
| `stock` | how many units are in the warehouse |

Notice the Bluetooth Speaker has a stock of `0`. Zero is a real value (we know there are none), which is different from `NULL` (we don't know).

## The orders table

```example sql
SELECT * FROM orders;
```

| Column | Meaning |
|---|---|
| `order_id` | unique order number (they start at 101) |
| `customer_id` | which customer placed the order |
| `order_date` | when the order was placed |
| `status` | pending, shipped, delivered or cancelled |

The `customer_id` column here is a **foreign key**: it points to a row in the `customers` table. Order 101 has `customer_id` 1, which means it was placed by Lakeside Books.

## The order_items table

An order can contain several different products, so the products in each order are stored in a separate table, one row per product line:

```example sql
SELECT * FROM order_items;
```

| Column | Meaning |
|---|---|
| `item_id` | unique number for each line |
| `order_id` | the order this line belongs to (points to `orders`) |
| `product_id` | the product bought (points to `products`) |
| `quantity` | how many units |

For example, the first two rows say that order 101 contained 2 Laptop Stands (product 1) and 2 Wireless Mice (product 2).

## How the tables connect

The tables form a chain:

```text
customers --< orders --< order_items >-- products
```

- One customer can have **many** orders.
- One order can have **many** order items.
- One product can appear in **many** order items.

Splitting data like this avoids repeating information. The customer's city is stored once in `customers`, not copied into every order. If the customer moves, you update one row. Designing tables this way is called **normalisation**.

To answer a question such as "which products did Savannah Tech buy?", you combine tables using a **join**. That comes later in [INNER JOIN](inner-join). For now, here is a sneak preview so you can see where we are heading:

```example sql
SELECT customers.name, orders.order_id, orders.status
FROM customers
JOIN orders ON orders.customer_id = customers.customer_id;
```

> **Tip:** Keep this page open in another tab while you work through the tutorial. Knowing which columns live in which table is half of writing good SQL.

> **Note:** Each Try it Yourself run loads a fresh copy of the database, so any changes you make (inserts, updates, deletes) disappear on the next run.

```exercise
Q: Show all rows from the table that stores order lines.
SELECT * FROM ___;
A: order_items
```
