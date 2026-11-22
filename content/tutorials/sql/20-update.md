---
title: SQL UPDATE
module: 6
minutes: 8
---

The `UPDATE` statement **changes existing rows**. Prices go up, stock arrives, orders get shipped, customers move to new cities. Each of these is an `UPDATE`.

## Syntax

```sql
UPDATE table_name
SET column1 = value1, column2 = value2, ...
WHERE condition;
```

- `SET` lists the columns to change and their new values.
- `WHERE` decides **which rows** change. Only rows where the condition is true are updated.

## Updating one row

Order 107 has been shipped. Change its status, then look at it:

```example sql
UPDATE orders
SET status = 'shipped'
WHERE order_id = 107;

SELECT * FROM orders WHERE order_id = 107;
```

Using the primary key (`order_id`) in the `WHERE` clause guarantees that exactly one row is affected.

## Updating several columns

Change several columns at once by separating them with commas. Victoria Prints has a new contact person and moved from Kisumu to Siaya:

```example sql
UPDATE customers
SET contact = 'Mary Atieno',
    city = 'Siaya'
WHERE customer_id = 5;

SELECT * FROM customers WHERE customer_id = 5;
```

## Calculating the new value

The new value can be calculated from the old one. A delivery of 30 Bluetooth Speakers arrives:

```example sql
UPDATE products
SET stock = stock + 30
WHERE product_id = 12;

SELECT name, stock FROM products WHERE product_id = 12;
```

`stock + 30` uses the value the row had before the update.

## Updating many rows

When the `WHERE` matches several rows, they are all changed. Raise every Accessories price by 10%:

```example sql
UPDATE products
SET price = ROUND(price * 1.10)
WHERE category = 'Accessories';

SELECT name, category, price FROM products ORDER BY category, name;
```

Only the four Accessories rows changed; the others are untouched. `ROUND()` keeps the new prices as whole shillings.

## The danger of forgetting WHERE

> **Warning:** If you leave out the `WHERE` clause, **every row in the table is updated.** `UPDATE customers SET city = 'Nairobi';` moves all your customers to Nairobi, and there is no undo button.

See it for yourself (it is safe here, the database is a fresh copy):

```example sql
UPDATE customers SET city = 'Nairobi';

SELECT name, city FROM customers;
```

> **Tip:** Before running an `UPDATE` on real data, run a `SELECT` with the **same** `WHERE` clause first. If it returns exactly the rows you expect, swap `SELECT ...` for `UPDATE ... SET ...`. Many people also work inside a transaction (`BEGIN; ... ROLLBACK;` or `COMMIT;`) so a mistake can be undone.

## Using a subquery in UPDATE

The condition can contain a subquery. Mark as "shipped" every pending order from a Kenyan customer:

```example sql
UPDATE orders
SET status = 'shipped'
WHERE status = 'pending'
  AND customer_id IN (SELECT customer_id FROM customers WHERE country = 'Kenya');

SELECT o.order_id, c.name, c.country, o.status
FROM orders AS o
JOIN customers AS c ON c.customer_id = o.customer_id
WHERE o.order_id IN (107, 110);
```

Order 107 (Rift Valley Cafe, Kenya) changed; order 110 (Dar Electronics, Tanzania) did not.

## Setting a value to NULL

To clear a value, set it to `NULL`:

```sql
UPDATE customers SET contact = NULL WHERE customer_id = 9;
```

Note that it is `SET column = NULL` (an assignment), even though you must use `IS NULL` when *testing* for it in `WHERE`.

```exercise
Q: Change the price of product 9 to 950.
___ products ___ price = 950 WHERE product_id = 9;
A: UPDATE | SET
```

```exercise
Q: Mark order 110 as cancelled, and only that order.
UPDATE orders SET status = 'cancelled' ___ order_id = 110;
A: WHERE
```
