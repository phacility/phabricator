<?php

$conn = $schema_conn;

echo "Indexing username tokens for typeaheads...\n";

$users = id(new PhabricatorUser())->loadAll();
echo count($users)." users to index";
foreach ($users as $user) {
  $user->updateNameTokens();
  echo ".";
}

echo "\nDone.\n";
