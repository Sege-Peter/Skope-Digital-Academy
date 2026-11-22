---
title: PHP Quiz
module: 7
minutes: 15
---

Test what you have learned in this PHP tutorial. Each question has exactly one correct answer. If you get one wrong, revisit the lesson linked in the list below, then try again.

- Syntax, variables and types: [Syntax](syntax), [Variables](variables), [Data Types](data-types)
- Logic: [Operators](operators), [Conditions](conditions), [Loops](loops), [Functions](functions)
- Data: [Arrays](arrays), [Array Functions](array-functions)
- The web: [Forms](forms), [Form Validation](form-validation), [Sessions and Cookies](sessions-cookies), [MySQL with PDO](mysql-pdo)
- Objects: [OOP Basics](oop-basics)

```quiz
Q: Where does PHP code run?
- In the visitor's browser
- On the web server, before the page is sent *
- In the database
- Only on the developer's computer

Q: Which of these is a valid PHP variable name?
- $2ndPlace
- total-fees
- $total_fees *
- &totalFees

Q: What does this code print? $x = 5; echo "Value: $x";
- Value: $x
- Value: 5 *
- Value: x
- Nothing, it causes an error

Q: Which operator joins two strings together?
- +
- &
- . *
- ,

Q: What is the result of var_dump(5 === "5");?
- bool(true)
- bool(false) *
- int(5)
- string(1) "5"

Q: What does $name = $_GET["name"] ?? "Guest"; do?
- Always sets $name to "Guest"
- Uses the "name" value from the URL, or "Guest" if it is missing *
- Compares $_GET["name"] with "Guest"
- Throws an error if "name" is missing

Q: Which statement about match (PHP 8) is true?
- It uses loose comparison like switch
- Each arm needs a break statement
- It returns a value and uses strict comparison *
- It silently does nothing when no arm matches

Q: Which loop is designed for walking through every element of an array?
- while
- do...while
- for
- foreach *

Q: A global variable is not visible inside a function. Which keyword lets the function use it?
- static
- global *
- public
- use

Q: Which function should you use when printing user input into an HTML page?
- strip_slashes()
- htmlspecialchars() *
- trim()
- addslashes()

Q: What is the safest way to run a SQL query that contains user input with PDO?
- Join the input into the SQL string with the . operator
- Use a prepared statement with placeholders, then execute() with the values *
- Wrap the input in single quotes
- Remove all spaces from the input first

Q: What is the main difference between include and require?
- require works only with HTML files
- include stops the script if the file is missing; require only warns
- require stops the script with a fatal error if the file is missing; include only warns *
- There is no difference

Q: Which function must be called before you can use $_SESSION?
- session_create()
- setcookie()
- session_start() *
- header()

Q: Inside a class method, what does $this refer to?
- The class's parent
- The current object *
- The last object created
- A global variable

Q: How should passwords be stored in the database?
- As plain text, so users can recover them
- Encrypted with str_rev()
- As a hash made with password_hash() *
- In a cookie
```

## What next?

Congratulations on finishing the PHP tutorial! Good next steps:

- Build a small project from start to finish, such as a student registration system with login, a dashboard and a MySQL table.
- Learn **Composer**, PHP's package manager, and try a framework such as **Laravel**.
- Deploy your project to real hosting (cPanel or a VPS) and enable HTTPS.
- Practise SQL in our SQL tutorial so your database queries become second nature.
