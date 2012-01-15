UPDATE phabricator_directory.directory_item
  SET name = 'MetaMTA (Admin Only)'
  WHERE href = '/mail/';

DELETE FROM phabricator_directory.directory_item
  WHERE href = '/xhprof/';
