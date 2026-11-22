---
title: SQL SELECT
module: 2
module_title: Querying Data
minutes: 9
---

The `SELECT` statement reads data from a database. It is by far the most used SQL statement: most of the time you are not changing data, you are asking questions about it.

## Syntax

```sql
SELECT column1, column2, ...
FROM table_name;
```

- After `SELECT` you list the **columns** you want, separated by commas.
- After `FROM` you name the **table** that holds them.

The answer comes back as a new table called the **result set**. It only exists to show you the answer; the original table is not changed.

## Selecting specific columns

To see just the name and price of every product:

```example sql
SELECT name, price
FROM products;
```

The columns appear in the order you list them, not the order they were created in. Swap them around and the result changes shape:

```example sql
SELECT price, name, stock
FROM products;
```

## Selecting all columns

Use an asterisk `*` to get every column:

```example sql
SELECT * FROM customers;
```

> **Tip:** `SELECT *` is handy for exploring a table you don't know yet. In real applications, list the columns you need instead. Your query then keeps working if someone adds a column later, and the database sends less data over the network.

## Calculations in SELECT

You are not limited to plain columns. You can do arithmetic with `+`, `-`, `*` and `/`, and the database calculates a value for every row. This shows the total value of the stock held for each product:

```example sql
SELECT name, price, stock, price * stock
FROM products;
```

The last column gets an ugly heading (`price * stock`). In the [Aliases](aliases) lesson you will learn to name it properly with `AS`.

You can also join pieces of text together. Standard SQL (and SQLite, PostgreSQL and Oracle) uses `||` for this:

```example sql
SELECT name || ' (' || city || ')'
FROM customers;
```

> **Note:** MySQL does not use `||` for text by default; it uses the function `CONCAT(name, ' (', city, ')')`. SQL Server uses `+`. This is one of those small dialect differences.

## SELECT without a table

A `SELECT` can also simply calculate something, with no table at all. This is a quick way to test an expression:

```example sql
SELECT 2500 * 3, 'Karibu Skope Shop!';
```

> **Note:** Some databases, such as Oracle, insist on a `FROM` clause and provide a dummy table called `DUAL` for this purpose: `SELECT 2500 * 3 FROM DUAL;`.

## Text values and quotes

When you write a text value in SQL, put it in **single quotes**: `'Kisumu'`. Numbers do not need quotes: `2500`. Double quotes are reserved for names of tables and columns that contain spaces or unusual characters, for example `"order date"`. It is best to avoid such names altogether and use underscores, like `order_date`.

## Order of the result

Without instructions, a database returns rows in whatever order is quickest for it. That often looks like insertion order on a small table, but you must not rely on it. When the order matters, use [ORDER BY](order-by).

## Summary

- `SELECT columns FROM table;` reads data.
- `*` means all columns.
- You can calculate new values from columns.
- The result is a temporary table; the stored data is unchanged.

```exercise
Q: Show the name and stock of every product.
SELECT ___, ___ FROM products;
A: name | stock
```

```exercise
Q: Show the contact person and city of every customer.
___ contact, city ___ customers;
A: SELECT | FROM
```
