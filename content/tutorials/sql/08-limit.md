---
title: SQL LIMIT
module: 2
minutes: 7
---

Tables in real systems can hold millions of rows. Often you only need a few of them: the top five products, the ten newest orders, the first page of search results. The `LIMIT` clause tells the database the maximum number of rows to return.

## Syntax

```sql
SELECT column1, column2, ...
FROM table_name
ORDER BY column
LIMIT number;
```

## Getting the first rows

Show just three customers:

```example sql
SELECT name, city
FROM customers
LIMIT 3;
```

On its own, `LIMIT` gives you "any three rows", because without `ORDER BY` the database chooses the order. That is fine for a quick peek at a table, but for real questions you almost always combine `LIMIT` with `ORDER BY`.

## Top-N queries

The three most expensive products:

```example sql
SELECT name, price
FROM products
ORDER BY price DESC
LIMIT 3;
```

The five most recent orders:

```example sql
SELECT order_id, order_date, status
FROM orders
ORDER BY order_date DESC
LIMIT 5;
```

The database first sorts all the rows, then keeps only the first few. `LIMIT` always comes last in the query.

> **Warning:** If several rows tie at the cut-off point, which ones you get is not guaranteed. Add a second sort column (for example `ORDER BY price DESC, name`) to make the result predictable.

## Skipping rows with OFFSET

`OFFSET` skips a number of rows before `LIMIT` starts counting. This is how websites show results one page at a time. With 4 products per page, page 1 is `LIMIT 4 OFFSET 0`, page 2 is `LIMIT 4 OFFSET 4`, and so on:

```example sql
SELECT product_id, name, price
FROM products
ORDER BY product_id
LIMIT 4 OFFSET 4;
```

That returns products 5 to 8, the second page.

You will also see a short form, `LIMIT 4, 4`, in MySQL and SQLite. In that form the **offset comes first** and the count second, the opposite way round from the long form, which confuses many people. Prefer `LIMIT ... OFFSET ...`.

## LIMIT in other databases

`LIMIT` is supported by SQLite, MySQL, MariaDB and PostgreSQL, but it is not part of the original SQL standard, and some databases use different words for the same idea:

| Database | First 3 rows |
|---|---|
| SQLite, MySQL, PostgreSQL | `SELECT * FROM products LIMIT 3;` |
| SQL Server, MS Access | `SELECT TOP 3 * FROM products;` |
| Oracle 12c+, PostgreSQL, SQL Server 2012+ (standard SQL) | `SELECT * FROM products FETCH FIRST 3 ROWS ONLY;` |
| Older Oracle | `SELECT * FROM products WHERE ROWNUM <= 3;` |

> **Note:** The examples in this tutorial run on SQLite, so use `LIMIT` here. If you later work with SQL Server, remember `TOP`.

## Combining everything so far

Here every clause you have learned works together: the two cheapest Accessories that are actually in stock.

```example sql
SELECT name, price, stock
FROM products
WHERE category = 'Accessories' AND stock > 0
ORDER BY price
LIMIT 2;
```

The clauses must appear in this order: `SELECT`, `FROM`, `WHERE`, `ORDER BY`, `LIMIT`.

> **Tip:** When exploring a large table for the first time, add `LIMIT 10` to your `SELECT *`. It keeps the result small and the query fast.

```exercise
Q: Show the single product with the most units in stock.
SELECT name, stock FROM products ORDER BY stock DESC ___ 1;
A: LIMIT
```

```exercise
Q: Show the 3rd and 4th oldest orders (skip the first two).
SELECT order_id, order_date FROM orders ORDER BY order_date LIMIT 2 ___ 2;
A: OFFSET
```
