---
title: SQL CASE Expressions
module: 5
minutes: 8
---

The `CASE` expression lets a query make decisions, much like `if ... else` in a programming language. It looks at conditions in order and returns the value for the first one that is true. You can use it anywhere a value is allowed: in `SELECT`, `ORDER BY`, `GROUP BY`, inside aggregate functions and more.

## Searched CASE

The general form tests a list of conditions:

```sql
CASE
  WHEN condition1 THEN result1
  WHEN condition2 THEN result2
  ELSE default_result
END
```

Label each product as Budget, Mid-range or Premium:

```example sql
SELECT name, price,
       CASE
         WHEN price < 2000  THEN 'Budget'
         WHEN price < 10000 THEN 'Mid-range'
         ELSE 'Premium'
       END AS price_band
FROM products
ORDER BY price;
```

The conditions are checked **from top to bottom**, and the first true one wins. A product costing 1,200 matches the first `WHEN`, so the second `WHEN` (which would also be true) is never checked. That is why the second condition doesn't need to say `price >= 2000 AND price < 10000`.

## Stock warnings

`CASE` is great for turning numbers into messages a person can act on:

```example sql
SELECT name, stock,
       CASE
         WHEN stock = 0  THEN 'Out of stock'
         WHEN stock < 20 THEN 'Reorder soon'
         ELSE 'OK'
       END AS stock_status
FROM products
ORDER BY stock;
```

## Simple CASE

When every condition compares the same column with a fixed value, there is a shorter form:

```sql
CASE column
  WHEN value1 THEN result1
  WHEN value2 THEN result2
  ELSE default_result
END
```

Translate order statuses for a customer-facing report:

```example sql
SELECT order_id, status,
       CASE status
         WHEN 'pending'   THEN 'We have received your order'
         WHEN 'shipped'   THEN 'On the way'
         WHEN 'delivered' THEN 'Delivered'
         ELSE 'Cancelled'
       END AS message
FROM orders;
```

## ELSE and NULL

If no `WHEN` matches and there is no `ELSE`, `CASE` returns `NULL`. In the example below Nyeri Stationers has no country, so neither branch matches:

```example sql
SELECT name, country,
       CASE
         WHEN country = 'Kenya' THEN 'Local'
         WHEN country IS NOT NULL THEN 'Export'
       END AS market
FROM customers;
```

> **Tip:** Always include an `ELSE`, even if it is just `ELSE 'Unknown'`. It makes your intention clear and avoids surprise `NULL`s.

## CASE inside aggregate functions

A powerful pattern is putting `CASE` inside `SUM` or `COUNT` to count several different things in a single pass over the table. How many orders are in each state, all on one row:

```example sql
SELECT
  COUNT(*) AS all_orders,
  SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
  SUM(CASE WHEN status IN ('pending', 'shipped') THEN 1 ELSE 0 END) AS open_orders,
  SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
FROM orders;
```

This is sometimes called **conditional aggregation**. It turns rows into columns, which is often how a report is laid out.

## Grouping by a CASE

You can also group by a `CASE` expression to build your own categories. Customers per market:

```example sql
SELECT CASE WHEN country = 'Kenya' THEN 'Kenya' ELSE 'Rest of East Africa' END AS market,
       COUNT(*) AS customers
FROM customers
GROUP BY CASE WHEN country = 'Kenya' THEN 'Kenya' ELSE 'Rest of East Africa' END;
```

Notice that Nyeri Stationers lands in "Rest of East Africa", because `country = 'Kenya'` is not true for a `NULL` country. Handling `NULL` explicitly with `WHEN country IS NULL THEN 'Unknown'` would be more accurate.

## Custom sort orders

`CASE` in `ORDER BY` lets you sort in an order that isn't alphabetical. Show open orders first, pending before shipped:

```example sql
SELECT order_id, status
FROM orders
ORDER BY CASE status
           WHEN 'pending' THEN 1
           WHEN 'shipped' THEN 2
           WHEN 'delivered' THEN 3
           ELSE 4
         END,
         order_id;
```

> **Note:** MySQL also has an `IF(condition, a, b)` function and SQL Server has `IIF()`. `CASE` is the standard version and works everywhere.

```exercise
Q: Complete the CASE expression that labels big orders.
SELECT item_id, CASE ___ quantity >= 5 THEN 'Bulk' ___ 'Normal' END FROM order_items;
A: WHEN | ELSE
```

```exercise
Q: Every CASE expression must finish with which keyword?
SELECT name, CASE WHEN stock = 0 THEN 'Out' ELSE 'In' ___ FROM products;
A: END
```
