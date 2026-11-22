---
title: SQL GROUP BY and HAVING
module: 4
minutes: 10
---

Aggregate functions give you one number for a whole table. `GROUP BY` lets you get one number **per group**: customers per country, products per category, orders per status. `HAVING` then filters those groups.

## GROUP BY syntax

```sql
SELECT group_column, AGGREGATE(column)
FROM table_name
WHERE condition
GROUP BY group_column
ORDER BY ...;
```

The database sorts rows into piles that share the same value in the `GROUP BY` column, then runs the aggregate function once for each pile.

## One group column

How many customers are in each country?

```example sql
SELECT country, COUNT(*) AS customers
FROM customers
GROUP BY country;
```

Kenya has 7 customers, Tanzania 2, and so on. Notice that `NULL` forms its own group with Nyeri Stationers in it.

Number of products and average price in each category, biggest categories first:

```example sql
SELECT category,
       COUNT(*)   AS products,
       AVG(price) AS avg_price
FROM products
GROUP BY category
ORDER BY products DESC;
```

## The golden rule of GROUP BY

Every column in the `SELECT` list must either:

1. appear in the `GROUP BY` clause, or
2. be inside an aggregate function.

Selecting `name` while grouping by `category` makes no sense, because each category contains several names. MySQL (with strict mode on), PostgreSQL and SQL Server report an error.

> **Note:** SQLite is lenient and lets you break this rule, returning a value from an arbitrary row in the group. Don't rely on it: write queries that would also work on a strict database.

## Grouping by several columns

Each unique **combination** becomes a group. Customers per country and city:

```example sql
SELECT country, city, COUNT(*) AS customers
FROM customers
GROUP BY country, city
ORDER BY country, city;
```

## Grouping by a calculated value

You can group by an expression. `SUBSTR(order_date, 1, 7)` takes the first seven characters of the date, `2024-03`, which is the year and month. Orders per month:

```example sql
SELECT SUBSTR(order_date, 1, 7) AS month,
       COUNT(*) AS orders
FROM orders
GROUP BY SUBSTR(order_date, 1, 7)
ORDER BY month;
```

> **Tip:** Different databases have different date functions. In MySQL you would write `DATE_FORMAT(order_date, '%Y-%m')`; in PostgreSQL `TO_CHAR(order_date, 'YYYY-MM')`. `SUBSTR` works here because the dates are stored as text.

## HAVING: filtering groups

`WHERE` filters **rows** before they are grouped. To filter on the **result** of an aggregate, such as "only countries with more than one customer", you need `HAVING`, which runs after grouping:

```example sql
SELECT country, COUNT(*) AS customers
FROM customers
GROUP BY country
HAVING COUNT(*) > 1;
```

You cannot write `WHERE COUNT(*) > 1`, because when `WHERE` runs the groups do not exist yet.

## WHERE and HAVING together

Which customers have placed more than one order that was not cancelled? `WHERE` removes the cancelled orders first, `GROUP BY` makes one group per customer, and `HAVING` keeps the busy ones:

```example sql
SELECT customer_id, COUNT(*) AS orders
FROM orders
WHERE status <> 'cancelled'
GROUP BY customer_id
HAVING COUNT(*) > 1
ORDER BY orders DESC;
```

Customer 2 (Savannah Tech) has three orders and customer 1 (Lakeside Books) has two. To show their names instead of IDs you need a join, coming up in [INNER JOIN](inner-join).

Total units sold per product, only products that sold at least 4 units:

```example sql
SELECT product_id, SUM(quantity) AS units_sold
FROM order_items
GROUP BY product_id
HAVING SUM(quantity) >= 4
ORDER BY units_sold DESC;
```

## Order of clauses

The clauses must be written in this order, which is also roughly the order the database runs them:

| Clause | What it does |
|---|---|
| `FROM` | choose the table(s) |
| `WHERE` | filter rows |
| `GROUP BY` | form groups |
| `HAVING` | filter groups |
| `SELECT` | calculate output columns |
| `ORDER BY` | sort the result |
| `LIMIT` | cut the result down |

(When you *write* the query, `SELECT` still comes first.)

```exercise
Q: Count the orders in each status.
SELECT status, COUNT(*) FROM orders ___ ___ status;
A: GROUP | BY
```

```exercise
Q: Show categories that have more than two products.
SELECT category, COUNT(*) FROM products GROUP BY category ___ COUNT(*) > 2;
A: HAVING
```
