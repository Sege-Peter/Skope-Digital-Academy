---
title: SQL Quiz
module: 7
minutes: 10
---

Test what you have learned in this SQL tutorial. Each question has one correct answer. If you get stuck, the [SQL Keyword Reference](keyword-reference) has a summary of every keyword, with links back to the lessons.

Before you start, here is one last warm-up query that pulls together joins, grouping, `HAVING`, sorting and `LIMIT`: the top three customers by total value of delivered orders.

```example sql
SELECT c.name,
       SUM(oi.quantity * p.price) AS delivered_value
FROM customers AS c
JOIN orders      AS o  ON o.customer_id = c.customer_id
JOIN order_items AS oi ON oi.order_id   = o.order_id
JOIN products    AS p  ON p.product_id  = oi.product_id
WHERE o.status = 'delivered'
GROUP BY c.customer_id, c.name
HAVING SUM(oi.quantity * p.price) > 10000
ORDER BY delivered_value DESC
LIMIT 3;
```

And a second one combining a subquery with `CASE`: every product with how many units have been ordered and a simple sales label.

```example sql
SELECT p.name,
       COALESCE((SELECT SUM(oi.quantity) FROM order_items AS oi
                 WHERE oi.product_id = p.product_id), 0) AS units_ordered,
       CASE
         WHEN (SELECT SUM(oi.quantity) FROM order_items AS oi
               WHERE oi.product_id = p.product_id) >= 5 THEN 'Best seller'
         ELSE 'Steady'
       END AS label
FROM products AS p
ORDER BY units_ordered DESC;
```

If you can read both of these and explain what each line does, you are in good shape. Now for the quiz.

```quiz
Q: What does SQL stand for?
- Structured Query Language *
- Simple Question Language
- Sequential Query Logic
- Standard Quick Lookup

Q: Which statement returns every column from the customers table?
- GET * FROM customers;
- SELECT ALL customers;
- SELECT * FROM customers; *
- SHOW customers;

Q: Which keyword removes duplicate rows from a result?
- UNIQUE
- DISTINCT *
- DIFFERENT
- SINGLE

Q: How do you find customers whose country is missing?
- WHERE country = NULL
- WHERE country = ''
- WHERE country IS NULL *
- WHERE country == NULL

Q: Which query lists products from most to least expensive?
- SELECT name FROM products ORDER BY price DESC; *
- SELECT name FROM products ORDER BY price;
- SELECT name FROM products SORT BY price DOWN;
- SELECT name FROM products ORDER price DESC;

Q: Which pattern finds names that contain the word "Tech" anywhere?
- LIKE 'Tech'
- LIKE 'Tech%'
- LIKE '%Tech%' *
- LIKE '_Tech_'

Q: In SQL Server, which keyword is used instead of LIMIT?
- FIRST
- TOP *
- ROWS
- MAX

Q: Is the value 6000 included by the condition price BETWEEN 2000 AND 6000?
- Yes, both ends of the range are included *
- No, the upper end is excluded
- No, both ends are excluded
- Only in MySQL

Q: Which clause filters groups after GROUP BY has been applied?
- WHERE
- HAVING *
- FILTER BY
- ORDER BY

Q: What does COUNT(country) count?
- All rows in the table
- Only rows where country is not NULL *
- The number of different countries
- Only rows where country is NULL

Q: Which join returns every customer, even customers with no orders?
- INNER JOIN customers to orders
- customers LEFT JOIN orders *
- customers CROSS JOIN orders
- orders INNER JOIN customers

Q: In AND/OR conditions without brackets, which is evaluated first?
- OR
- AND *
- Whichever comes first in the line
- They are always evaluated together

Q: What happens if you run UPDATE products SET price = 0; with no WHERE clause?
- Nothing, because WHERE is required
- Only the first row is updated
- Every row in the table is updated *
- The database asks for confirmation

Q: Which constraint stops two rows from having the same value in a column?
- NOT NULL
- DEFAULT
- UNIQUE *
- CHECK

Q: What does a FOREIGN KEY do?
- Encrypts a column
- Links a column to the key of another table *
- Hides a column from query results
- Sorts the table automatically
```

> **Tip:** Missed a question? Go back to the lesson, open the Try it Yourself editor and experiment with the Skope Shop database until the idea clicks. Practice is the fastest way to learn SQL.
