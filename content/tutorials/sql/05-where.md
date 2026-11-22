---
title: SQL WHERE
module: 2
minutes: 9
---

So far every query has returned every row of a table. Real questions are usually narrower: *customers in Kisumu*, *products under KSh 2,000*, *orders still pending*. The `WHERE` clause filters rows so you only get the ones that match a condition.

## Syntax

```sql
SELECT column1, column2, ...
FROM table_name
WHERE condition;
```

The database checks the condition for each row. Rows where it is **true** are kept; all others are left out.

## Filtering on text

Find every customer based in Kisumu:

```example sql
SELECT name, contact, city
FROM customers
WHERE city = 'Kisumu';
```

Text values go inside **single quotes**. Without the quotes, SQL would think `Kisumu` was the name of a column and give an error.

> **Note:** Whether text comparisons are case-sensitive depends on the database. In SQLite and PostgreSQL, `'kisumu'` does not equal `'Kisumu'`. In MySQL with its usual settings, they are equal. Match the exact case of the stored data to be safe.

## Filtering on numbers

Numbers are written without quotes. Find products that cost less than KSh 2,000:

```example sql
SELECT name, price
FROM products
WHERE price < 2000;
```

## Comparison operators

| Operator | Meaning | Example |
|---|---|---|
| `=` | equal to | `status = 'pending'` |
| `<>` | not equal to | `country <> 'Kenya'` |
| `!=` | not equal to (widely supported, not in the original standard) | `country != 'Kenya'` |
| `>` | greater than | `stock > 50` |
| `<` | less than | `price < 1000` |
| `>=` | greater than or equal to | `price >= 5000` |
| `<=` | less than or equal to | `quantity <= 2` |

Products with 50 or more units in stock:

```example sql
SELECT name, stock
FROM products
WHERE stock >= 50;
```

Orders that are not delivered yet (or were cancelled):

```example sql
SELECT order_id, customer_id, status
FROM orders
WHERE status <> 'delivered';
```

## Filtering on dates

In the Skope Shop database dates are stored as text in the form `YYYY-MM-DD`. Because the year comes first, comparing them as text gives the right chronological order. Orders placed from 1 May 2024 onwards:

```example sql
SELECT order_id, order_date
FROM orders
WHERE order_date >= '2024-05-01';
```

> **Tip:** Always store dates as `YYYY-MM-DD` (the ISO 8601 format). Formats like `05/01/2024` cannot be compared or sorted correctly as text, and they are ambiguous: is that 5 January or 1 May?

## Conditions using calculations

The condition can use any expression, including arithmetic on columns. Which products have more than KSh 200,000 of value sitting in the warehouse?

```example sql
SELECT name, price, stock
FROM products
WHERE price * stock > 200000;
```

## WHERE is not only for SELECT

The same `WHERE` clause decides which rows are changed by [UPDATE](update) and which rows are removed by [DELETE](delete). There, getting the condition wrong can change far more data than you meant to, so it pays to master `WHERE` now.

> **Warning:** You cannot find empty values with `= NULL`. The condition `country = NULL` is never true. Use `country IS NULL` instead, as explained in [NULL Values](null-values).

## Summary

- `WHERE` keeps only the rows for which the condition is true.
- Quote text values, don't quote numbers.
- Use `=`, `<>`, `<`, `>`, `<=`, `>=` to compare.

To combine several conditions, such as "in Kenya **and** joined in 2024", continue to [AND, OR, NOT](and-or-not).

```exercise
Q: Show customers whose country is Tanzania.
SELECT name FROM customers ___ country = ___;
A: WHERE | 'Tanzania'
H: Text values need single quotes.
```

```exercise
Q: Show products that are out of stock (stock is zero).
SELECT name FROM products WHERE stock ___ 0;
A: =
```
