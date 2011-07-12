/* Older versions incorrectly computed the depth for the root page. */
UPDATE phabricator_phriction.phriction_document
  SET depth = 0 where slug = '/';

INSERT INTO phabricator_directory.directory_item
  (name, description, href, categoryID, sequence, dateCreated, dateModified)
VALUES
  ("Phriction", "Write things down.", "/w/", 4, 1100,
    UNIX_TIMESTAMP(), UNIX_TIMESTAMP());