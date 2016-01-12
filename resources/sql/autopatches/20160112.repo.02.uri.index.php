<?php

$table = new PhabricatorRepository();

foreach (new LiskMigrationIterator($table) as $repo) {
  $repo->updateURIIndex();
}
