CREATE TABLE categories (
  category_id INT PRIMARY KEY,
  category_name VARCHAR(50) NOT NULL,
  parent_category_id INT,
  FOREIGN KEY (parent_category_id) REFERENCES categories(category_id)
);