-- Skope Shop: the sample database every "Try it Yourself" SQL example runs against.
-- It is loaded fresh (SQLite, in the browser) each time an example runs.

CREATE TABLE customers (
  customer_id INTEGER PRIMARY KEY,
  name        TEXT NOT NULL,
  contact     TEXT,
  city        TEXT,
  country     TEXT,
  joined      TEXT
);
INSERT INTO customers VALUES
 (1,'Lakeside Books','Achieng Otieno','Kisumu','Kenya','2023-01-14'),
 (2,'Savannah Tech','Brian Mwangi','Nairobi','Kenya','2023-02-03'),
 (3,'Coastline Traders','Fatuma Ali','Mombasa','Kenya','2023-03-21'),
 (4,'Highland Farms','Kiprono Kirui','Eldoret','Kenya','2023-04-09'),
 (5,'Victoria Prints','Grace Akinyi','Kisumu','Kenya','2023-05-30'),
 (6,'Kampala Digital','Joseph Okello','Kampala','Uganda','2023-06-18'),
 (7,'Arusha Outfitters','Neema Mollel','Arusha','Tanzania','2023-07-02'),
 (8,'Rift Valley Cafe','Daniel Kamau','Nakuru','Kenya','2023-08-25'),
 (9,'Kigali Studio','Aline Uwase','Kigali','Rwanda','2023-09-12'),
 (10,'Dar Electronics','Hassan Juma','Dar es Salaam','Tanzania','2023-10-07'),
 (11,'Nyeri Stationers','Wanjiru Njeri','Nyeri',NULL,'2024-01-19'),
 (12,'Homa Bay Fresh','Ouma Odhiambo','Homa Bay','Kenya','2024-02-28');

CREATE TABLE products (
  product_id INTEGER PRIMARY KEY,
  name       TEXT NOT NULL,
  category   TEXT,
  price      REAL,
  stock      INTEGER
);
INSERT INTO products VALUES
 (1,'Laptop Stand','Accessories',2500,40),
 (2,'Wireless Mouse','Accessories',1200,120),
 (3,'USB-C Hub','Accessories',3800,35),
 (4,'27" Monitor','Displays',28500,12),
 (5,'Mechanical Keyboard','Accessories',6500,25),
 (6,'Webcam HD','Video',4200,30),
 (7,'Noise-Cancelling Headset','Audio',9800,18),
 (8,'Portable SSD 1TB','Storage',11500,22),
 (9,'Flash Drive 64GB','Storage',900,200),
 (10,'Desk Lamp LED','Office',1800,60),
 (11,'Office Chair','Office',15500,8),
 (12,'Bluetooth Speaker','Audio',5200,0);

CREATE TABLE orders (
  order_id    INTEGER PRIMARY KEY,
  customer_id INTEGER REFERENCES customers(customer_id),
  order_date  TEXT,
  status      TEXT
);
INSERT INTO orders VALUES
 (101,1,'2024-03-02','delivered'),
 (102,2,'2024-03-05','delivered'),
 (103,2,'2024-03-18','shipped'),
 (104,5,'2024-04-01','delivered'),
 (105,3,'2024-04-11','cancelled'),
 (106,6,'2024-04-20','delivered'),
 (107,8,'2024-05-03','pending'),
 (108,1,'2024-05-15','shipped'),
 (109,9,'2024-05-22','delivered'),
 (110,10,'2024-06-01','pending'),
 (111,4,'2024-06-09','delivered'),
 (112,2,'2024-06-17','delivered');

CREATE TABLE order_items (
  item_id    INTEGER PRIMARY KEY,
  order_id   INTEGER REFERENCES orders(order_id),
  product_id INTEGER REFERENCES products(product_id),
  quantity   INTEGER
);
INSERT INTO order_items VALUES
 (1,101,1,2),(2,101,2,2),(3,102,4,1),(4,102,5,1),(5,103,8,2),
 (6,104,10,3),(7,104,9,10),(8,105,7,1),(9,106,3,4),(10,106,2,4),
 (11,107,11,1),(12,108,6,2),(13,109,4,2),(14,109,7,2),(15,110,12,5),
 (16,111,1,1),(17,111,10,1),(18,112,8,1),(19,112,5,2),(20,112,2,3);
