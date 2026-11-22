---
title: SQL INNER JOIN
module: 5
module_title: Joins and Subqueries
minutes: 10
---

In a well-designed database, information is split across tables. The `orders` table knows *which* customer placed an order (`customer_id`), but the customer's name and city live in `customers`. A **join** combines rows from two tables into one result, matching them on a shared column.

## Why we need joins

Look at the orders on their own:

```example sql
SELECT order_id, customer_id, order_date
FROM orders;
```

`customer_id` 2 is not very meaningful to a person. We want "Savannah Tech". To get it, we match each order to the customer whose `customer_id` is the same.

## INNER JOIN syntax

```sql
SELECT columns
FROM table1
INNER JOIN table2
  ON table1.column = table2.column;
```

The `ON` clause gives the **join condition**: which rows belong together. Most of the time you join a foreign key (`orders.customer_id`) to the primary key it points to (`customers.customer_id`).

## Your first join

```example sql
SELECT orders.order_id,
       orders.order_date,
       customers.name,
       customers.city
FROM orders
INNER JOIN customers
  ON orders.customer_id = customers.customer_id;
```

For each order, the database finds the customer with the matching ID and glues the two rows together. Each order appears once, now with its customer's name and city.

Because both tables have a `customer_id` column, you must say which table you mean by writing `table.column`. Table aliases (from [Aliases](aliases)) make this much shorter:

```example sql
SELECT o.order_id, o.status, c.name AS customer, c.country
FROM orders AS o
INNER JOIN customers AS c ON o.customer_id = c.customer_id
ORDER BY c.name;
```

> **Tip:** The word `INNER` is optional: `JOIN` on its own means `INNER JOIN`. Many people write `JOIN` for short.

## What "inner" means

An inner join returns **only rows that have a match in both tables**. Arusha Outfitters, Nyeri Stationers and Homa Bay Fresh have never placed an order, so none of them appear in the results above. If you need to include rows without a match, use a [LEFT JOIN](left-join).

## Joining order lines to products

The same idea works between `order_items` and `products`. Show every order line with the product name and price:

```example sql
SELECT oi.order_id, p.name AS product, oi.quantity, p.price
FROM order_items AS oi
JOIN products AS p ON oi.product_id = p.product_id
ORDER BY oi.order_id;
```

## Calculating with joined columns

Once tables are joined you can use columns from both in calculations, filters and groups. Line totals (quantity × price) for order 112:

```example sql
SELECT p.name, oi.quantity, p.price,
       oi.quantity * p.price AS line_total
FROM order_items AS oi
JOIN products AS p ON oi.product_id = p.product_id
WHERE oi.order_id = 112;
```

And combining with `GROUP BY`: the number of orders for each customer, using names instead of IDs:

```example sql
SELECT c.name, COUNT(*) AS orders
FROM customers AS c
JOIN orders AS o ON o.customer_id = c.customer_id
GROUP BY c.customer_id, c.name
ORDER BY orders DESC, c.name;
```

## Another way you may see joins written

Older code often lists tables with commas and puts the join condition in `WHERE`:

```sql
SELECT o.order_id, c.name
FROM orders o, customers c
WHERE o.customer_id = c.customer_id;
```

This gives the same result, but if you forget the condition, you get every order paired with every customer (12 × 12 = 144 rows), called a **Cartesian product**. The explicit `JOIN ... ON` style keeps the join condition next to the join and is much harder to get wrong.

> **Warning:** A missing or wrong `ON` condition is the most common cause of joins returning far too many rows. If a result looks suspiciously large, check the `ON` clause first.

## Other join types

| Join | Returns |
|---|---|
| `INNER JOIN` | only rows with a match in both tables |
| `LEFT JOIN` | all rows from the left table, plus matches from the right |
| `RIGHT JOIN` | all rows from the right table, plus matches from the left |
| `FULL OUTER JOIN` | all rows from both tables, matched where possible |
| `CROSS JOIN` | every row of one table paired with every row of the other |

The next lesson covers `LEFT JOIN` and joining three or more tables.

```exercise
Q: Complete the join between orders and customers.
SELECT o.order_id, c.name FROM orders o ___ customers c ___ o.customer_id = c.customer_id;
A: JOIN / INNER JOIN | ON
```

```exercise
Q: Show each order line with its product name.
SELECT oi.order_id, p.name FROM order_items oi JOIN products p ON oi.product_id = p.___;
A: product_id
```
