CREATE DATABASE IF NOT EXISTS phabricator_countdown;

CREATE TABLE phabricator_countdown.countdown_timer (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  authorPHID VARCHAR(64) BINARY NOT NULL,
  datepoint INT UNSIGNED NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL
);

INSERT INTO phabricator_directory.directory_item
  (name, description, href, categoryID, sequence, dateCreated, dateModified)
VALUES
  ("Countdown", "Utilize the full capabilities of your ALU.", "/countdown/", 5, 350,
    UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
