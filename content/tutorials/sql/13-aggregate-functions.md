---
title: SQL Aggregate Functions
module: 4
module_title: Summarising Data
minutes: 9
---

Managers rarely want to see every row. They want answers like "how many customers do we have?", "what is our average price?" or "how many units have we sold?". **Aggregate functions** take many rows and squeeze them into a single value.

## The five main aggregate functions

| Function | Returns |
|---|---|
| `COUNT()` | the number of rows or values |
| `SUM()` | the total of a numeric column |
| `AVG()` | the average (mean) of a numeric column |
| `MIN()` | the smallest value |
| `MAX()` | the largest value |

## COUNT

`COUNT(*)` counts rows. How many customers does Skope Shop have?

```example sql
SELECT COUNT(*) AS total_customers
FROM customers;
```

Combine it with `WHERE` to count only matching rows. How many orders are delivered?

```example sql
SELECT COUNT(*) AS delivered_orders
FROM orders
WHERE status = 'delivered';
```

There are three forms of `COUNT` worth knowing:

| Form | Counts |
|---|---|
| `COUNT(*)` | all rows |
| `COUNT(column)` | rows where `column` is not `NULL` |
| `COUNT(DISTINCT column)` | different non-`NULL` values in `column` |

```example sql
SELECT COUNT(*)                AS rows_total,
       COUNT(country)          AS with_country,
       COUNT(DISTINCT country) AS countries
FROM customers;
```

## SUM

`SUM()` adds up a column. Total units sold across every order line:

```example sql
SELECT SUM(quantity) AS units_sold
FROM order_items;
```

You can sum an expression too. The total value of all the stock in the warehouse:

```example sql
SELECT SUM(price * stock) AS stock_value_ksh
FROM products;
```

## AVG

`AVG()` calculates the mean. The average product price, and the average price of Accessories only:

```example sql
SELECT AVG(price) AS avg_price
FROM products;
```

```example sql
SELECT ROUND(AVG(price), 2) AS avg_accessory_price
FROM products
WHERE category = 'Accessories';
```

`ROUND(value, 2)` rounds to two decimal places, which keeps long decimals out of reports.

> **Note:** Be careful when averaging whole numbers. In some databases (SQL Server, for example) `AVG` of an integer column returns a whole number, dropping the fraction. SQLite, MySQL and PostgreSQL return a decimal.

## MIN and MAX

The cheapest and most expensive product prices:

```example sql
SELECT MIN(price) AS cheapest,
       MAX(price) AS dearest
FROM products;
```

`MIN` and `MAX` also work on text (alphabetical order) and on dates. The first and most recent order dates:

```example sql
SELECT MIN(order_date) AS first_order,
       MAX(order_date) AS latest_order
FROM orders;
```

## Aggregates and NULL

All aggregate functions except `COUNT(*)` ignore `NULL` values. `AVG` divides by the number of non-`NULL` values, not by the number of rows. If an empty table or a filter leaves no rows at all, `COUNT` returns 0 while `SUM`, `AVG`, `MIN` and `MAX` return `NULL`.

## A common mistake

Can you show the **name** of the most expensive product with `MAX`? You might try:

```sql
SELECT name, MAX(price) FROM products;
```

Most databases reject this, because `name` is not aggregated: which of 12 names should sit next to a single maximum? (SQLite allows it and happens to pick the matching row, but don't rely on that.) The portable way is a subquery:

```example sql
SELECT name, price
FROM products
WHERE price = (SELECT MAX(price) FROM products);
```

You will learn subqueries in [Subqueries](subqueries). To get a total **per group** (per category, per customer, per month), you need [GROUP BY](group-by-having), which is next.

```exercise
Q: Count how many products are in the Accessories category.
SELECT ___(*) FROM products WHERE category = 'Accessories';
A: COUNT
```

```exercise
Q: Find the lowest stock level of any product.
SELECT ___(stock) FROM products;
A: MIN
```
