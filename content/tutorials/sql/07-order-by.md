---
title: SQL ORDER BY
module: 2
minutes: 8
---

A database makes no promise about the order of rows in a result. If you want the cheapest products first, the newest orders at the top, or customers in alphabetical order, you must ask for it with `ORDER BY`.

## Syntax

```sql
SELECT column1, column2, ...
FROM table_name
ORDER BY column1 [ASC | DESC], column2 [ASC | DESC], ...;
```

- `ASC` sorts in **ascending** order (A to Z, smallest to largest, oldest to newest). It is the default, so you can leave it out.
- `DESC` sorts in **descending** order (Z to A, largest to smallest, newest to oldest).

## Sorting ascending

List products from cheapest to most expensive:

```example sql
SELECT name, price
FROM products
ORDER BY price;
```

## Sorting descending

Add `DESC` to reverse it, most expensive first:

```example sql
SELECT name, price
FROM products
ORDER BY price DESC;
```

Text is sorted alphabetically and dates in `YYYY-MM-DD` form sort chronologically. The most recent orders first:

```example sql
SELECT order_id, order_date, status
FROM orders
ORDER BY order_date DESC;
```

## Sorting by several columns

When two rows have the same value in the first sort column, the second column decides between them, and so on. Sort customers by country, and within each country by city:

```example sql
SELECT name, city, country
FROM customers
ORDER BY country, city;
```

Each column can have its own direction. Products grouped by category (A to Z), with the most expensive first inside each category:

```example sql
SELECT category, name, price
FROM products
ORDER BY category ASC, price DESC;
```

> **Note:** `DESC` applies only to the column directly before it. In `ORDER BY category, price DESC`, the category is still sorted ascending.

## Sorting by a calculation

You can sort by an expression, even one that is not in the `SELECT` list. Products with the most stock value first:

```example sql
SELECT name, price, stock
FROM products
ORDER BY price * stock DESC;
```

## Where ORDER BY goes

`ORDER BY` comes **after** `WHERE`. The database first filters the rows, then sorts what is left:

```example sql
SELECT name, price
FROM products
WHERE category = 'Accessories'
ORDER BY price DESC;
```

The full order of clauses you have learned so far is `SELECT ... FROM ... WHERE ... ORDER BY ...`. Later you will add `GROUP BY`, `HAVING` and `LIMIT` to this sequence.

## How NULL values sort

Where do empty (`NULL`) values go? It depends on the database. SQLite and MySQL put `NULL`s **first** in ascending order; PostgreSQL and Oracle put them **last**. Run this and look for Nyeri Stationers:

```example sql
SELECT name, country
FROM customers
ORDER BY country;
```

> **Tip:** Standard SQL lets you choose with `NULLS FIRST` or `NULLS LAST`, e.g. `ORDER BY country NULLS LAST`. PostgreSQL, Oracle and recent SQLite versions support it; MySQL does not.

## Sorting by column position

You may see `ORDER BY 2` in other people's code. It means "sort by the second column in the SELECT list". It works, but it breaks silently if someone later changes the column list, so prefer column names.

```exercise
Q: List customers by the date they joined, newest first.
SELECT name, joined FROM customers ORDER BY joined ___;
A: DESC
```

```exercise
Q: Sort products alphabetically by name.
SELECT name FROM products ___ ___ name;
A: ORDER | BY
```
