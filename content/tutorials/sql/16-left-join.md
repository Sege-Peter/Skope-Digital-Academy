---
title: SQL LEFT JOIN and Multiple Joins
module: 5
minutes: 10
---

An [INNER JOIN](inner-join) only keeps rows that match in both tables. Often that is exactly right, but sometimes the rows **without** a match are the interesting ones: customers who have never ordered, products nobody has bought. For those questions you need a `LEFT JOIN`.

## LEFT JOIN syntax

```sql
SELECT columns
FROM left_table
LEFT JOIN right_table
  ON left_table.column = right_table.column;
```

A `LEFT JOIN` returns **every row from the left table** (the one named after `FROM`). Where a matching row exists in the right table, its columns are filled in. Where no match exists, the right table's columns are `NULL`.

It is also written `LEFT OUTER JOIN`; the word `OUTER` is optional.

## Seeing the difference

Compare this with the inner join from the previous lesson:

```example sql
SELECT c.name, o.order_id, o.status
FROM customers AS c
LEFT JOIN orders AS o ON o.customer_id = c.customer_id
ORDER BY c.name;
```

Every customer is listed. Arusha Outfitters, Homa Bay Fresh and Nyeri Stationers now appear with `NULL` in `order_id` and `status`, because they have no orders.

## Finding rows with no match

That `NULL` gives you a neat trick: filter for it with `IS NULL` to find the left rows that have **no** match.

```example sql
SELECT c.name, c.city, c.joined
FROM customers AS c
LEFT JOIN orders AS o ON o.customer_id = c.customer_id
WHERE o.order_id IS NULL;
```

That is the sales team's follow-up list: customers who signed up but have never ordered.

## Counting with LEFT JOIN

To count orders per customer **including zeros**, count a column from the right table. `COUNT(o.order_id)` skips the `NULL`s, so customers without orders get 0 rather than 1:

```example sql
SELECT c.name, COUNT(o.order_id) AS orders
FROM customers AS c
LEFT JOIN orders AS o ON o.customer_id = c.customer_id
GROUP BY c.customer_id, c.name
ORDER BY orders DESC, c.name;
```

> **Warning:** `COUNT(*)` would count the row produced for a customer with no orders and wrongly report 1. With a `LEFT JOIN`, count a column from the right-hand table.

## RIGHT JOIN and FULL OUTER JOIN

A `RIGHT JOIN` is the mirror image: it keeps every row from the **right** table. Any right join can be rewritten as a left join by swapping the table order, which is why many people only ever use `LEFT JOIN`.

A `FULL OUTER JOIN` keeps every row from **both** tables, filling in `NULL` on whichever side has no match.

> **Note:** Not every database supports these. SQLite only added `RIGHT JOIN` and `FULL OUTER JOIN` in version 3.39 (2022), so older SQLite versions reject them, and MySQL still has no `FULL OUTER JOIN`. The examples in this tutorial stick to `INNER` and `LEFT` joins so they run everywhere.

## Joining three or more tables

To answer "which products did each customer buy?" you need all four tables: customers → orders → order_items → products. Simply add one `JOIN` after another; each new table joins to one already in the query:

```example sql
SELECT c.name AS customer,
       o.order_id,
       p.name AS product,
       oi.quantity
FROM customers AS c
JOIN orders      AS o  ON o.customer_id = c.customer_id
JOIN order_items AS oi ON oi.order_id   = o.order_id
JOIN products    AS p  ON p.product_id  = oi.product_id
ORDER BY o.order_id;
```

Now we can answer real business questions. The total value of each order:

```example sql
SELECT o.order_id,
       c.name AS customer,
       SUM(oi.quantity * p.price) AS order_total
FROM orders AS o
JOIN customers   AS c  ON c.customer_id = o.customer_id
JOIN order_items AS oi ON oi.order_id   = o.order_id
JOIN products    AS p  ON p.product_id  = oi.product_id
GROUP BY o.order_id, c.name
ORDER BY order_total DESC;
```

And total spending per customer on delivered orders, with every customer listed (zero if nothing delivered). Here the filter on status goes in the `ON` clause, so customers without delivered orders are kept:

```example sql
SELECT c.name,
       COALESCE(SUM(oi.quantity * p.price), 0) AS delivered_value
FROM customers AS c
LEFT JOIN orders      AS o  ON o.customer_id = c.customer_id
                           AND o.status = 'delivered'
LEFT JOIN order_items AS oi ON oi.order_id = o.order_id
LEFT JOIN products    AS p  ON p.product_id = oi.product_id
GROUP BY c.customer_id, c.name
ORDER BY delivered_value DESC;
```

> **Tip:** With a `LEFT JOIN`, a condition on the right table in `WHERE` throws away the unmatched rows (their values are `NULL`), turning it back into an inner join. Put such conditions in the `ON` clause instead, as above.

```exercise
Q: List every customer, with their orders if they have any.
SELECT c.name, o.order_id FROM customers c ___ JOIN orders o ON o.customer_id = c.customer_id;
A: LEFT / LEFT OUTER
```

```exercise
Q: Keep only customers who have no orders.
SELECT c.name FROM customers c LEFT JOIN orders o ON o.customer_id = c.customer_id WHERE o.order_id ___ ___;
A: IS | NULL
```
