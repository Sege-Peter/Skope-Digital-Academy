---
title: SQL Keyword Reference
module: 7
module_title: Reference and Quiz
minutes: 6
---

A quick reference to the SQL keywords, operators and functions used in this tutorial. Follow the lesson links for full explanations and runnable examples.

## Querying data

| Keyword | Description | Example | Lesson |
|---|---|---|---|
| `SELECT` | Chooses the columns or values to return | `SELECT name, price FROM products;` | [SELECT](select) |
| `*` | All columns | `SELECT * FROM orders;` | [SELECT](select) |
| `FROM` | Names the table to read from | `FROM customers` | [SELECT](select) |
| `DISTINCT` | Removes duplicate rows from the result | `SELECT DISTINCT country FROM customers;` | [SELECT DISTINCT](select-distinct) |
| `WHERE` | Keeps only rows that meet a condition | `WHERE city = 'Kisumu'` | [WHERE](where) |
| `AND` | Both conditions must be true | `WHERE price > 1000 AND stock > 0` | [AND, OR, NOT](and-or-not) |
| `OR` | At least one condition must be true | `WHERE category = 'Audio' OR category = 'Video'` | [AND, OR, NOT](and-or-not) |
| `NOT` | Reverses a condition | `WHERE NOT country = 'Kenya'` | [AND, OR, NOT](and-or-not) |
| `ORDER BY` | Sorts the result | `ORDER BY price DESC` | [ORDER BY](order-by) |
| `ASC` / `DESC` | Ascending (default) / descending sort | `ORDER BY joined DESC` | [ORDER BY](order-by) |
| `LIMIT` | Maximum number of rows to return (SQLite, MySQL, PostgreSQL) | `LIMIT 5` | [LIMIT](limit) |
| `OFFSET` | Skips rows before `LIMIT` starts | `LIMIT 5 OFFSET 10` | [LIMIT](limit) |
| `TOP` | SQL Server's version of `LIMIT` | `SELECT TOP 5 * FROM products;` | [LIMIT](limit) |
| `AS` | Gives a column or table an alias | `price * stock AS stock_value` | [Aliases](aliases) |

## Conditions and operators

| Keyword | Description | Example | Lesson |
|---|---|---|---|
| `=`, `<>`, `<`, `>`, `<=`, `>=` | Comparison operators | `WHERE stock >= 50` | [WHERE](where) |
| `IS NULL` | Value is missing | `WHERE country IS NULL` | [NULL Values](null-values) |
| `IS NOT NULL` | Value is present | `WHERE country IS NOT NULL` | [NULL Values](null-values) |
| `LIKE` | Matches a text pattern | `WHERE name LIKE '%USB%'` | [LIKE](like-wildcards) |
| `%` | Wildcard: any number of characters | `LIKE 'K%'` | [LIKE](like-wildcards) |
| `_` | Wildcard: exactly one character | `LIKE '_o%'` | [LIKE](like-wildcards) |
| `IN` | Value is in a list or subquery result | `WHERE status IN ('pending', 'shipped')` | [IN and BETWEEN](in-between) |
| `BETWEEN` | Value is within a range (ends included) | `WHERE price BETWEEN 1000 AND 5000` | [IN and BETWEEN](in-between) |
| `EXISTS` | Subquery returns at least one row | `WHERE EXISTS (SELECT 1 FROM orders o WHERE ...)` | [Subqueries](subqueries) |

To join pieces of text together, standard SQL uses two pipe characters (see [SELECT](select)):

```sql
SELECT city || ', ' || country FROM customers;
```

MySQL uses `CONCAT(city, ', ', country)` instead, and SQL Server uses `+`.

## Functions

| Function | Description | Example | Lesson |
|---|---|---|---|
| `COUNT()` | Number of rows or non-NULL values | `COUNT(*)`, `COUNT(DISTINCT city)` | [Aggregate Functions](aggregate-functions) |
| `SUM()` | Total of a numeric column | `SUM(quantity)` | [Aggregate Functions](aggregate-functions) |
| `AVG()` | Average of a numeric column | `AVG(price)` | [Aggregate Functions](aggregate-functions) |
| `MIN()` | Smallest value | `MIN(order_date)` | [Aggregate Functions](aggregate-functions) |
| `MAX()` | Largest value | `MAX(price)` | [Aggregate Functions](aggregate-functions) |
| `ROUND()` | Rounds a number | `ROUND(AVG(price), 2)` | [Aggregate Functions](aggregate-functions) |
| `COALESCE()` | First non-NULL value in a list | `COALESCE(country, 'Unknown')` | [NULL Values](null-values) |
| `SUBSTR()` | Part of a text value | `SUBSTR(order_date, 1, 7)` | [GROUP BY and HAVING](group-by-having) |

## Grouping and logic

| Keyword | Description | Example | Lesson |
|---|---|---|---|
| `GROUP BY` | Forms groups of rows for aggregate functions | `GROUP BY category` | [GROUP BY and HAVING](group-by-having) |
| `HAVING` | Filters groups after grouping | `HAVING COUNT(*) > 1` | [GROUP BY and HAVING](group-by-having) |
| `CASE` | If/then logic inside a query | `CASE WHEN stock = 0 THEN 'Out' ELSE 'In' END` | [CASE](case-expressions) |
| `WHEN` / `THEN` / `ELSE` / `END` | Parts of a `CASE` expression | see above | [CASE](case-expressions) |

## Joins

| Keyword | Description | Example | Lesson |
|---|---|---|---|
| `JOIN` / `INNER JOIN` | Rows with a match in both tables | `FROM orders o JOIN customers c ON o.customer_id = c.customer_id` | [INNER JOIN](inner-join) |
| `ON` | The join condition | `ON oi.product_id = p.product_id` | [INNER JOIN](inner-join) |
| `LEFT JOIN` | All rows from the left table, matches from the right | `FROM customers c LEFT JOIN orders o ON ...` | [LEFT JOIN](left-join) |
| `RIGHT JOIN` | All rows from the right table (not in older SQLite) | `FROM orders o RIGHT JOIN customers c ON ...` | [LEFT JOIN](left-join) |
| `FULL OUTER JOIN` | All rows from both tables (not in MySQL or older SQLite) | `FROM a FULL OUTER JOIN b ON ...` | [LEFT JOIN](left-join) |
| `CROSS JOIN` | Every row paired with every row | `FROM products CROSS JOIN customers` | [INNER JOIN](inner-join) |

## Changing data

| Keyword | Description | Example | Lesson |
|---|---|---|---|
| `INSERT INTO` | Adds new rows | `INSERT INTO products (name, price) VALUES ('Mouse Pad', 300);` | [INSERT INTO](insert-into) |
| `VALUES` | The values for a new row | `VALUES (13, 'Kitale Agrovet')` | [INSERT INTO](insert-into) |
| `UPDATE` | Changes existing rows | `UPDATE orders SET status = 'shipped' WHERE order_id = 107;` | [UPDATE](update) |
| `SET` | The columns and new values in an `UPDATE` | `SET stock = stock + 30` | [UPDATE](update) |
| `DELETE FROM` | Removes rows | `DELETE FROM orders WHERE status = 'cancelled';` | [DELETE](delete) |
| `TRUNCATE TABLE` | Quickly removes all rows (not in SQLite) | `TRUNCATE TABLE order_items;` | [DELETE](delete) |

## Defining tables

| Keyword | Description | Example | Lesson |
|---|---|---|---|
| `CREATE TABLE` | Creates a new table | `CREATE TABLE suppliers (...);` | [CREATE TABLE](create-table) |
| `ALTER TABLE` | Changes an existing table | `ALTER TABLE products ADD COLUMN supplier_id INTEGER;` | [CREATE TABLE](create-table) |
| `DROP TABLE` | Deletes a table and its data | `DROP TABLE IF EXISTS suppliers;` | [CREATE TABLE](create-table) |
| `PRIMARY KEY` | Uniquely identifies each row | `customer_id INTEGER PRIMARY KEY` | [CREATE TABLE](create-table) |
| `FOREIGN KEY` / `REFERENCES` | Links to a key in another table | `REFERENCES customers(customer_id)` | [CREATE TABLE](create-table) |
| `NOT NULL` | Column must have a value | `name TEXT NOT NULL` | [CREATE TABLE](create-table) |
| `UNIQUE` | No duplicate values allowed | `email TEXT UNIQUE` | [CREATE TABLE](create-table) |
| `DEFAULT` | Value used when none is given | `method TEXT DEFAULT 'M-Pesa'` | [CREATE TABLE](create-table) |
| `CHECK` | Values must satisfy a condition | `CHECK (rating BETWEEN 1 AND 5)` | [CREATE TABLE](create-table) |

> **Tip:** Clause order in a `SELECT` statement is always: `SELECT` → `FROM` → `JOIN ... ON` → `WHERE` → `GROUP BY` → `HAVING` → `ORDER BY` → `LIMIT`.
