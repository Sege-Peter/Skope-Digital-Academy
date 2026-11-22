---
title: SQL DELETE
module: 6
minutes: 7
---

The `DELETE` statement **removes rows** from a table. A test record needs cleaning up, a cancelled order should go, a product is discontinued. Of all SQL statements, `DELETE` deserves the most care, because deleted data is usually gone for good.

## Syntax

```sql
DELETE FROM table_name
WHERE condition;
```

Every row for which the condition is true is removed. There is no column list: `DELETE` always removes whole rows. To clear just one value in a row, use [UPDATE](update) and set it to `NULL`.

## Deleting one row

Order 105 was cancelled. Remove its order line first, then the order itself, and check what is left:

```example sql
DELETE FROM order_items WHERE order_id = 105;
DELETE FROM orders WHERE order_id = 105;

SELECT order_id, status FROM orders ORDER BY order_id;
```

Order 105 is gone; eleven orders remain.

Why delete the order lines first? Because `order_items.order_id` refers to the order. If you deleted the order but left its lines, they would point to an order that no longer exists, called an **orphaned** row. Databases that enforce foreign keys refuse to create orphans; see [CREATE TABLE](create-table).

## Deleting several rows

Any condition works, just as with `SELECT`. Delete all order lines with a quantity of 1:

```example sql
DELETE FROM order_items
WHERE quantity = 1;

SELECT COUNT(*) AS remaining_lines FROM order_items;
```

Of the 20 order lines, 7 had a quantity of 1, so 13 remain.

## Deleting with a subquery

Remove customers who have never placed an order:

```example sql
DELETE FROM customers
WHERE customer_id NOT IN (SELECT customer_id FROM orders);

SELECT customer_id, name FROM customers;
```

Arusha Outfitters, Nyeri Stationers and Homa Bay Fresh are deleted, leaving the nine customers who have ordered.

## Deleting every row

If you leave out `WHERE`, **all rows** are deleted. The table itself stays, with its columns, but it is empty:

```example sql
DELETE FROM order_items;

SELECT COUNT(*) AS lines_left FROM order_items;
```

> **Warning:** `DELETE FROM customers;` without a `WHERE` empties the whole table. On a real system, always run a `SELECT` with the same `WHERE` clause first, check the rows, and only then change `SELECT *` to `DELETE`.

## DELETE vs TRUNCATE vs DROP

| Statement | What it does |
|---|---|
| `DELETE FROM t WHERE ...` | removes the matching rows; can be filtered and undone inside a transaction |
| `DELETE FROM t` | removes all rows, one by one; the table stays |
| `TRUNCATE TABLE t` | removes all rows very quickly; the table stays; no `WHERE` |
| `DROP TABLE t` | removes the table itself, its structure and all its data |

> **Note:** SQLite does not have `TRUNCATE TABLE`; a `DELETE` without `WHERE` is automatically optimised to do the same job. MySQL, PostgreSQL, SQL Server and Oracle all support `TRUNCATE`.

## Soft deletes

Many real applications never actually delete important records like customers or orders. Instead they keep a column such as `status` or `deleted_at` and mark the row as removed. That keeps the history for reports and audits, and a mistake can be reversed with a simple `UPDATE`. In Skope Shop, a cancelled order keeps `status = 'cancelled'` instead of disappearing, which is exactly this idea.

> **Tip:** Make regular backups. A backup is the only reliable undo for a `DELETE` that has already been committed.

```exercise
Q: Delete the product with ID 12.
___ ___ products WHERE product_id = 12;
A: DELETE | FROM
```

```exercise
Q: Delete all orders with the status 'cancelled', and only those.
DELETE FROM orders ___ status = 'cancelled';
A: WHERE
```
