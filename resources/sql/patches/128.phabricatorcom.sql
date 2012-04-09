UPDATE phabricator_directory.directory_item SET
  href = REPLACE(href, 'http://phabricator.com/', 'http://www.phabricator.com/')
  WHERE href LIKE 'http://phabricator.com/%';
