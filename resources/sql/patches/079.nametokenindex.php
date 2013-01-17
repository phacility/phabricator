<?php

echo "Indexing username tokens for typeaheads...\n";

$table = new PhabricatorUser();
$table->openTransaction();
$table->beginReadLocking();

$users = $table->loadAll();
echo count($users)." users to index";
foreach ($users as $user) {
  $user->updateNameTokens();
  echo ".";
}

$table->endReadLocking();
$table->saveTransaction();
echo "\nDone.\n";
