<?php

// Update the "PROJ" search index, to:
//
//   - Populate the index itself, which was added recently.
//   - Populate the secondary object name index in Maniphest.

$root = dirname(phutil_get_library_root('phabricator'));

$command = new PhutilExecPassthru(
  'php -f %s -- index --type PROJ',
  $root.'/scripts/search/manage_search.php');
$err = $command->execute();
if ($err) {
  throw new Exception("Update failed!");
}
