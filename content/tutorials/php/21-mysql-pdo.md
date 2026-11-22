---
title: PHP and MySQL with PDO
module: 6
module_title: Databases and Objects
minutes: 20
---

Almost every dynamic website stores its data in a database: users, courses, orders, payments. PHP's most popular partner is **MySQL** (or its twin **MariaDB**, which XAMPP installs). In this lesson you will connect to a database, read and write data, and, most importantly, do it safely with **prepared statements**.

> **Note:** These examples need a running MySQL/MariaDB server, so the outputs shown describe what you get with the sample data below. If you are new to SQL itself, work through our SQL tutorial first.

## What is PDO?

**PDO** (PHP Data Objects) is PHP's built-in database layer. It gives you one consistent set of methods for MySQL, MariaDB, PostgreSQL, SQLite and more. You may also see the older `mysqli` extension in existing code; it works too, but PDO is cleaner and is the recommended choice for new projects.

## Preparing the database

Start MySQL in the XAMPP Control Panel, open `http://localhost/phpmyadmin`, and run this SQL to create a database and table:

```sql
CREATE DATABASE school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE school;

CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    county VARCHAR(50) NOT NULL,
    balance INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO students (name, email, county, balance) VALUES
('Achieng Otieno', 'achieng@example.com', 'Kisumu', 2500),
('Brian Kiptoo', 'brian@example.com', 'Uasin Gishu', 0),
('Fatuma Ali', 'fatuma@example.com', 'Mombasa', 1200);
```

## Connecting

Create a `PDO` object with a **DSN** (a connection string naming the driver, host, database and character set), a username and a password. On a fresh XAMPP install the user is `root` with an empty password.

Save this as `db.php` so every page can `require` it:

```php
<?php
$dsn = "mysql:host=localhost;dbname=school;charset=utf8mb4";

$pdo = new PDO($dsn, "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
```

Then test it with a small page. `fetchColumn()` returns the first column of the first row, which is handy for counts.

```example php
<?php
require "db.php";

$count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
echo "Connected. The students table has $count rows.";
```

```output
Connected. The students table has 3 rows.
```

The three options are worth using on every project:

- `ERRMODE_EXCEPTION` makes PDO throw an exception when a query fails, instead of failing silently.
- `FETCH_ASSOC` returns each row as an associative array (`$row["name"]`).
- `EMULATE_PREPARES => false` uses the database's real prepared statements.

> **Warning:** On a live server, never use `root` with an empty password. Create a dedicated database user with a strong password and only the permissions your app needs, and keep those credentials in a config file outside the public folder.

## Reading many rows

When a query has **no user input** at all, you can run it directly with `query()`. `fetchAll()` returns every row as an array of arrays, ready to loop over.

```example php
<?php
require "db.php";

$stmt = $pdo->query("SELECT name, county, balance FROM students ORDER BY name");
$students = $stmt->fetchAll();

foreach ($students as $s) {
    echo $s["name"] . " (" . $s["county"] . ") owes KSh " . $s["balance"] . "\n";
}
echo count($students) . " students found.";
```

```output
Achieng Otieno (Kisumu) owes KSh 2500
Brian Kiptoo (Uasin Gishu) owes KSh 0
Fatuma Ali (Mombasa) owes KSh 1200
3 students found.
```

## SQL injection: why prepared statements matter

Suppose a search page builds its SQL by gluing in what the visitor typed:

```php
// DANGEROUS - never do this
$county = $_GET["county"];
$sql = "SELECT * FROM students WHERE county = '$county'";
```

If a visitor types `' OR '1'='1` into the search box, the query becomes `... WHERE county = '' OR '1'='1'`, which matches **every** row. With other inputs, an attacker can read password hashes, change balances or delete tables. This is called **SQL injection**, and it has caused some of the biggest data breaches in history.

The fix is a **prepared statement**. You send the SQL with **placeholders**, and send the values separately. The database never confuses the values with SQL code, whatever they contain.

## Prepared statements with placeholders

There are two placeholder styles: positional `?` and named `:name`. You `prepare()` the SQL once, then `execute()` it with the values. This request is for `search.php?county=Kisumu`:

```example php
<?php
require "db.php";

$county = $_GET["county"] ?? "";

$stmt = $pdo->prepare("SELECT id, name, balance FROM students WHERE county = ?");
$stmt->execute([$county]);
$rows = $stmt->fetchAll();

foreach ($rows as $row) {
    echo "#{$row['id']} " . htmlspecialchars($row["name"]) . " - KSh {$row['balance']}\n";
}
```

```output
#1 Achieng Otieno - KSh 2500
```

If someone sends `' OR '1'='1`, the database searches for a county literally named `' OR '1'='1`, finds nothing, and prints nothing. The attack simply does not work.

### Fetching one row

`fetch()` returns the next row, or `false` when there are none. It is perfect for "find by ID" and login lookups.

```example php
<?php
require "db.php";

$stmt = $pdo->prepare("SELECT name, email FROM students WHERE id = :id");
$stmt->execute(["id" => 3]);
$student = $stmt->fetch();

if ($student === false) {
    echo "No student with that ID.";
} else {
    echo $student["name"] . " <" . $student["email"] . ">";
}
```

```output
Fatuma Ali <fatuma@example.com>
```

## Inserting data

`INSERT`, `UPDATE` and `DELETE` use exactly the same prepare-and-execute pattern. After an insert, `lastInsertId()` gives the new row's `AUTO_INCREMENT` ID.

```example php
<?php
require "db.php";

$stmt = $pdo->prepare(
    "INSERT INTO students (name, email, county, balance) VALUES (:name, :email, :county, :balance)"
);
$stmt->execute([
    "name" => "Joseph Mwangi",
    "email" => "joseph@example.com",
    "county" => "Nyeri",
    "balance" => 3000,
]);

echo "Saved student with ID " . $pdo->lastInsertId();
```

```output
Saved student with ID 4
```

In a real form, the values would come from validated `$_POST` fields (see [Form Validation](form-validation)).

## Updating and deleting

`rowCount()` tells you how many rows the statement changed.

```example php
<?php
require "db.php";

$pay = $pdo->prepare("UPDATE students SET balance = balance - ? WHERE id = ?");
$pay->execute([1000, 1]);
echo "Payment applied to " . $pay->rowCount() . " student(s).\n";

$del = $pdo->prepare("DELETE FROM students WHERE email = ?");
$del->execute(["nobody@example.com"]);
echo "Deleted " . $del->rowCount() . " row(s).";
```

```output
Payment applied to 1 student(s).
Deleted 0 row(s).
```

> **Tip:** When several queries must succeed or fail together, such as moving money between two accounts, wrap them in a transaction: `$pdo->beginTransaction();`, run the queries, then `$pdo->commit();`. If anything throws, call `$pdo->rollBack();` in the `catch` block.

## Handling errors

With `ERRMODE_EXCEPTION`, a failed connection or query throws a `PDOException`. Catch it, log the details for yourself, and show the visitor a friendly message. Here the email `achieng@example.com` already exists, so the `UNIQUE` rule rejects the insert:

```example php
<?php
require "db.php";

try {
    $stmt = $pdo->prepare("INSERT INTO students (name, email, county) VALUES (?, ?, ?)");
    $stmt->execute(["Achieng O.", "achieng@example.com", "Kisumu"]);
    echo "Registered!";
} catch (PDOException $e) {
    error_log($e->getMessage());
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        echo "That email address is already registered.";
    } else {
        echo "Sorry, something went wrong. Please try again.";
    }
}
```

```output
That email address is already registered.
```

`1062` is MySQL's error code for a duplicate entry.

> **Warning:** Never `echo $e->getMessage()` to visitors on a live site. Database errors can reveal table names, queries and server details. Log them with `error_log()` instead.

## Summary

| Task | Method |
|---|---|
| Connect | `new PDO($dsn, $user, $pass, $options)` |
| Query with no user input | `$pdo->query($sql)` |
| Query with user input | `$stmt = $pdo->prepare($sql); $stmt->execute($values);` |
| All rows / one row | `$stmt->fetchAll()` / `$stmt->fetch()` |
| New row's ID | `$pdo->lastInsertId()` |
| Rows changed | `$stmt->rowCount()` |

```exercise
Q: Prepare a query with a placeholder, then run it with the value.
$stmt = $pdo->___("SELECT * FROM students WHERE email = ?");
$stmt->___([$email]);
A: prepare | execute
```

```exercise
Q: Fetch all result rows into an array.
$rows = $stmt->___();
A: fetchAll
```

```exercise
Q: Get the ID of the row you just inserted.
$id = $pdo->___();
A: lastInsertId
```
