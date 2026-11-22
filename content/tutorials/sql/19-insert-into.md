---
title: SQL INSERT INTO
module: 6
module_title: Changing Data
minutes: 8
---

So far you have only read data. The `INSERT INTO` statement **adds new rows** to a table: a new customer signs up, a new product arrives, an order is placed.

> **Note:** Every Try it Yourself run starts with a fresh copy of the Skope Shop database. Each example below inserts data and then runs a `SELECT` so you can see the effect. Your changes vanish on the next run, so experiment as much as you like.

## Syntax

The recommended form names the columns you are filling, then gives the values in the same order:

```sql
INSERT INTO table_name (column1, column2, column3, ...)
VALUES (value1, value2, value3, ...);
```

## Inserting one row

A new customer from Machakos joins Skope Shop:

```example sql
INSERT INTO customers (customer_id, name, contact, city, country, joined)
VALUES (13, 'Machakos Hardware', 'Mutua Musyoka', 'Machakos', 'Kenya', '2024-07-01');

SELECT * FROM customers WHERE customer_id = 13;
```

Text and dates go in single quotes; numbers do not. The values must match the column list one for one: the first value goes into the first named column, and so on.

## Leaving columns out

You don't have to list every column. Any column you leave out gets its **default value**, which is `NULL` unless the table defines something else. Here we don't know the contact person or join date yet:

```example sql
INSERT INTO customers (customer_id, name, city, country)
VALUES (13, 'Kitale Agrovet', 'Kitale', 'Kenya');

SELECT * FROM customers WHERE city = 'Kitale';
```

The `contact` and `joined` columns are `NULL`.

## Automatic IDs

In the Skope Shop tables, each ID column is declared `INTEGER PRIMARY KEY`. In SQLite that means if you leave it out, the database picks the next number for you:

```example sql
INSERT INTO products (name, category, price, stock)
VALUES ('Laptop Bag', 'Accessories', 3200, 50);

SELECT * FROM products ORDER BY product_id DESC LIMIT 3;
```

The new product gets ID 13 automatically.

> **Note:** Each database spells "automatic number" differently: `AUTO_INCREMENT` in MySQL, `SERIAL` or `GENERATED ... AS IDENTITY` in PostgreSQL, `IDENTITY` in SQL Server, and `INTEGER PRIMARY KEY` (optionally with `AUTOINCREMENT`) in SQLite.

## Inserting several rows at once

List several sets of values separated by commas. A new order with two product lines:

```example sql
INSERT INTO orders (order_id, customer_id, order_date, status)
VALUES (113, 12, '2024-06-20', 'pending');

INSERT INTO order_items (order_id, product_id, quantity)
VALUES (113, 9, 20),
       (113, 10, 2);

SELECT o.order_id, o.status, p.name, oi.quantity
FROM orders AS o
JOIN order_items AS oi ON oi.order_id = o.order_id
JOIN products AS p ON p.product_id = oi.product_id
WHERE o.order_id = 113;
```

Multi-row `VALUES` is supported by SQLite, MySQL, PostgreSQL and SQL Server 2008+. It is faster than many separate statements.

## Inserting without a column list

You can skip the column list entirely if you give a value for **every** column in the exact order they were created:

```sql
INSERT INTO customers
VALUES (13, 'Kericho Tea Co', 'Chebet Langat', 'Kericho', 'Kenya', '2024-07-03');
```

> **Warning:** This short form breaks as soon as someone adds or reorders a column in the table, and it is easy to put values in the wrong column. Always name the columns in real code.

## Copying rows with INSERT ... SELECT

Instead of `VALUES`, you can insert the result of a query. Here we create an empty `archived_orders` table with the same columns, then copy the cancelled orders into it:

```example sql
CREATE TABLE archived_orders (
  order_id INTEGER,
  customer_id INTEGER,
  order_date TEXT,
  status TEXT
);

INSERT INTO archived_orders (order_id, customer_id, order_date, status)
SELECT order_id, customer_id, order_date, status
FROM orders
WHERE status = 'cancelled';

SELECT * FROM archived_orders;
```

You will learn `CREATE TABLE` properly in [CREATE TABLE](create-table).

## When an insert fails

The database refuses rows that break the table's rules. Try giving a new customer an ID that is already taken, or leaving out `name`, which is declared `NOT NULL`. You will see an error and nothing is inserted.

```exercise
Q: Complete the statement that adds a new product.
___ ___ products (name, category, price, stock) ___ ('HDMI Cable', 'Accessories', 600, 80);
A: INSERT | INTO | VALUES
```
