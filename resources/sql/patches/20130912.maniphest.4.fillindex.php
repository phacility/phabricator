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
  // NOTE: Just ignore this. The indexing script fails if there are no projects
  // yet, and even if it actually fails with something terrible and explosive
  // it isn't very concerning. The worst case here is that "group by projects"
  // doesn't work perfectly in Maniphest, and re-running the script later on
  // can fix it.
}
