---
title: SQL Subqueries
module: 5
minutes: 10
---

A **subquery** is a `SELECT` statement written inside another SQL statement. The inner query runs to produce a value, a list or a table, and the outer query uses that result. Subqueries let you answer questions in steps, the way you would think them through yourself.

Subqueries are always written in brackets.

## A subquery that returns one value

Which products cost more than the average price? You need two steps: work out the average, then compare each product with it. A subquery does both at once:

```example sql
SELECT name, price
FROM products
WHERE price > (SELECT AVG(price) FROM products)
ORDER BY price DESC;
```

The inner query `SELECT AVG(price) FROM products` returns a single number (about 7,617). The outer query then uses it exactly as if you had typed that number. A subquery like this, returning one row and one column, is called a **scalar subquery**.

The newest customer, without knowing the date in advance:

```example sql
SELECT name, city, joined
FROM customers
WHERE joined = (SELECT MAX(joined) FROM customers);
```

> **Warning:** If a subquery used with `=`, `<` or `>` returns more than one row, most databases raise an error. Make sure it can only return one value, usually by using an aggregate function.

## A subquery that returns a list: IN

With `IN`, the subquery can return many rows (one column). Customers who have at least one order still pending:

```example sql
SELECT name, contact
FROM customers
WHERE customer_id IN (
  SELECT customer_id
  FROM orders
  WHERE status = 'pending'
);
```

Subqueries can be nested. Customers who have ever bought a product in the Audio category:

```example sql
SELECT name
FROM customers
WHERE customer_id IN (
  SELECT customer_id FROM orders
  WHERE order_id IN (
    SELECT order_id FROM order_items
    WHERE product_id IN (
      SELECT product_id FROM products WHERE category = 'Audio'
    )
  )
);
```

The same question can also be answered with joins. Both are correct; choose whichever you find clearer.

## NOT IN and its NULL trap

`NOT IN` finds rows whose value is not in the list. Customers who have never ordered:

```example sql
SELECT name
FROM customers
WHERE customer_id NOT IN (SELECT customer_id FROM orders);
```

> **Warning:** If the subquery returns even one `NULL`, `NOT IN` returns no rows at all, because SQL cannot be sure a value differs from an unknown one. Add `WHERE customer_id IS NOT NULL` inside the subquery, or use `NOT EXISTS` (below), which does not have this problem.

## EXISTS and correlated subqueries

`EXISTS` is true if the subquery returns **any** rows. The subquery below refers to `c.customer_id` from the outer query, so it runs once for each customer. That is called a **correlated subquery**.

```example sql
SELECT c.name
FROM customers AS c
WHERE EXISTS (
  SELECT 1
  FROM orders AS o
  WHERE o.customer_id = c.customer_id
    AND o.status = 'shipped'
);
```

`SELECT 1` is a convention: `EXISTS` only cares whether rows exist, not what they contain. `NOT EXISTS` does the opposite and is the safe way to find rows with no match.

## Subqueries in the SELECT list

A scalar subquery can also produce a column. Each customer with their number of orders:

```example sql
SELECT c.name,
       (SELECT COUNT(*) FROM orders AS o
        WHERE o.customer_id = c.customer_id) AS orders
FROM customers AS c
ORDER BY orders DESC;
```

## Subqueries in FROM

A subquery in the `FROM` clause acts as a temporary table, sometimes called a **derived table**. It must be given an alias. Here we first compute each order's total, then average those totals:

```example sql
SELECT ROUND(AVG(order_total), 2) AS average_order_value
FROM (
  SELECT oi.order_id, SUM(oi.quantity * p.price) AS order_total
  FROM order_items AS oi
  JOIN products AS p ON p.product_id = oi.product_id
  GROUP BY oi.order_id
) AS totals;
```

> **Tip:** For long queries, many databases (SQLite, PostgreSQL, MySQL 8+, SQL Server) support the `WITH` clause, also called a common table expression. It lets you name a subquery up front: `WITH totals AS (SELECT ...) SELECT AVG(order_total) FROM totals;`.

```exercise
Q: Show products cheaper than the average price.
SELECT name FROM products WHERE price < (SELECT ___(price) FROM products);
A: AVG
```

```exercise
Q: Show customers who appear in the orders table.
SELECT name FROM customers WHERE customer_id ___ (SELECT customer_id FROM orders);
A: IN
```
