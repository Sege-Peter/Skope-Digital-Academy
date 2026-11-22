---
title: Introduction to SQL Querying
module: 4
module_title: Week 4 – SQL, Python and Your Data Career
minutes: 16
---

Spreadsheets struggle once data reaches hundreds of thousands of rows, and most company data lives in **databases** anyway. **SQL** (Structured Query Language, often said "sequel") is how analysts ask a database questions. It is one of the most requested skills in data job adverts.

## The Skope Shop database

The examples below run against a small sample database for "Skope Shop", an electronics supplier with customers across East Africa. It has four tables:

| Table | Columns |
|---|---|
| `customers` | customer_id, name, contact, city, country, joined |
| `products` | product_id, name, category, price, stock |
| `orders` | order_id, customer_id, order_date, status |
| `order_items` | item_id, order_id, product_id, quantity |

Click **Try it Yourself** on any example to run it and change it.

## SELECT and FROM – choosing columns

```example sql
SELECT name, city, country
FROM customers;
```

`SELECT *` returns every column, but naming the columns you need is clearer.

## WHERE – filtering rows

```example sql
SELECT name, price
FROM products
WHERE price > 5000;
```

Text values go in single quotes. Combine conditions with `AND` / `OR`:

```example sql
SELECT name, city
FROM customers
WHERE country = 'Kenya' AND city = 'Kisumu';
```

## ORDER BY and LIMIT – sorting

```example sql
SELECT name, price
FROM products
ORDER BY price DESC
LIMIT 3;
```

`DESC` means largest first; `ASC` (the default) means smallest first.

## Aggregates and GROUP BY – the SQL pivot table

The functions `COUNT`, `SUM`, `AVG`, `MIN` and `MAX` work like their spreadsheet cousins. `GROUP BY` works like a pivot table's Rows:

```example sql
SELECT status, COUNT(*) AS number_of_orders
FROM orders
GROUP BY status
ORDER BY number_of_orders DESC;
```

This shows 7 delivered, 2 shipped, 2 pending and 1 cancelled order.

```example sql
SELECT category, COUNT(*) AS products, ROUND(AVG(price), 0) AS avg_price
FROM products
GROUP BY category;
```

## Finding data-quality problems

SQL is great for spotting gaps. One customer has no country recorded:

```example sql
SELECT name, city
FROM customers
WHERE country IS NULL;
```

Note: you must write `IS NULL`, not `= NULL`.

## JOIN – combining tables

Order lines only store `product_id`. To see product names and calculate sales value, **join** the tables on their matching IDs:

```example sql
SELECT p.name, SUM(oi.quantity) AS units_sold,
       SUM(oi.quantity * p.price) AS revenue_kes
FROM order_items oi
JOIN products p ON p.product_id = oi.product_id
GROUP BY p.name
ORDER BY revenue_kes DESC;
```

The 27" Monitor earns the most (KES 85,500). A careful analyst would notice that this query also counts cancelled and pending orders – try adding `JOIN orders o ON o.order_id = oi.order_id` and `WHERE o.status = 'delivered'` before `GROUP BY` to count only completed sales.

> **Tip:** Write SQL in this order and read it like a sentence: SELECT *what* FROM *where* WHERE *condition* GROUP BY *groups* ORDER BY *sorting* LIMIT *how many*.

```exercise
Q: Complete the query to show only products in the 'Audio' category.
SELECT name, price FROM products ___ category = 'Audio';
A: WHERE
```

```exercise
Q: Complete the query to count customers per country.
SELECT country, ___(*) FROM customers ___ BY country;
A: COUNT | GROUP
```

```exercise
Q: Complete the query to list products from cheapest to most expensive.
SELECT name, price FROM products ORDER BY price ___;
A: ASC
H: The opposite of DESC.
```

## Practice task

Using the Try it Yourself editor, write queries to answer: (1) Which products have zero stock? (2) How many customers joined in 2024? (Hint: `WHERE joined LIKE '2024%'`.) (3) Which city has the most customers? Write each question and your SQL in a notebook – these are exactly the kinds of questions asked in junior data analyst interviews.
