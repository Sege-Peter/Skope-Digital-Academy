---
title: SQL Aliases
module: 3
minutes: 7
---

An **alias** is a temporary name you give to a column or a table inside a single query. Aliases make results easier to read and long queries easier to write. They do not rename anything in the database itself.

## Column aliases

Use `AS` after a column or expression to name it in the result:

```example sql
SELECT name AS product, price AS price_ksh
FROM products;
```

Aliases are most valuable for calculated columns, which otherwise get headings like `price * stock`:

```example sql
SELECT name,
       price * stock AS stock_value
FROM products
ORDER BY stock_value DESC;
```

Notice that the alias `stock_value` can be used in `ORDER BY`. The database works out the `SELECT` list before sorting, so the new name is already known.

## Aliases with spaces

If you want a heading with spaces or capital letters, put the alias in **double quotes**:

```example sql
SELECT name AS "Customer",
       contact AS "Contact Person",
       city || ', ' || COALESCE(country, '?') AS "Location"
FROM customers;
```

> **Note:** Standard SQL uses double quotes for names. MySQL also accepts backticks (`` `Contact Person` ``) and SQL Server accepts square brackets (`[Contact Person]`). Single quotes are for text values, not names, so avoid them for aliases.

## AS is optional

The keyword `AS` can be left out: `SELECT name product FROM products` works. But it is easy to misread, especially if you forget a comma (`SELECT name price` silently renames `name` to `price`!). Writing `AS` makes your intention clear.

> **Tip:** Always write `AS` for column aliases. It costs two characters and prevents confusing mistakes.

## Table aliases

You can also give a **table** a short alias in the `FROM` clause, then use it to qualify column names:

```example sql
SELECT p.name, p.category, p.price
FROM products AS p
WHERE p.price > 10000;
```

With a single table this is just shorter. With several tables it becomes essential, because two tables can have columns with the same name. Both `customers` and `products` have a column called `name`, and both `orders` and `customers` have `customer_id`. Writing `c.name` or `o.customer_id` makes clear which one you mean:

```example sql
SELECT o.order_id, o.order_date, c.name AS customer
FROM orders AS o
JOIN customers AS c ON c.customer_id = o.customer_id
ORDER BY o.order_date;
```

Joins are covered properly in [INNER JOIN](inner-join). For now, notice how much shorter `o.` and `c.` are than repeating `orders.` and `customers.` everywhere.

> **Note:** Oracle does not allow the word `AS` before a table alias; write `FROM orders o`. Leaving `AS` out for table aliases works in every database, so you will often see it written that way.

## Where can you use an alias?

A column alias belongs to the result, so it is available in `ORDER BY`, but in standard SQL it is **not** available in `WHERE`. The `WHERE` clause is applied before the `SELECT` list is calculated, so the alias doesn't exist yet:

```sql
-- Not portable: many databases reject this
SELECT name, price * stock AS stock_value
FROM products
WHERE stock_value > 200000;
```

Repeat the expression instead:

```example sql
SELECT name, price * stock AS stock_value
FROM products
WHERE price * stock > 200000;
```

> **Note:** SQLite is unusually forgiving and does accept an alias in `WHERE`, but MySQL, PostgreSQL and SQL Server do not. Write the portable version.

```exercise
Q: Show each product's price including 16% VAT, in a column named with_vat.
SELECT name, price * 1.16 ___ with_vat FROM products;
A: AS
```

```exercise
Q: Give the customers table the short alias c.
SELECT c.name FROM customers AS ___;
A: c
```
