CREATE DATABASE IF NOT EXISTS phabricator_pastebin;

CREATE TABLE phabricator_pastebin.pastebin_paste (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  phid VARCHAR(64) BINARY NOT NULL,
  authorPHID VARCHAR(64) BINARY NOT NULL,
  filePHID VARCHAR(64) BINARY NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL
);

INSERT INTO phabricator_directory.directory_item
  (name, description, href, categoryID, sequence, dateCreated, dateModified)
VALUES
  ("Paste", "Mmm... tasty, delicious paste.", "/paste/", 5, 150,
    UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
