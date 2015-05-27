<?php

echo pht('Indexing username tokens for typeaheads...')."\n";

$table = new PhabricatorUser();
$table->openTransaction();
$table->beginReadLocking();

$users = $table->loadAll();
echo pht('%d users to index', count($users));
foreach ($users as $user) {
  $user->updateNameTokens();
  echo '.';
}

$table->endReadLocking();
$table->saveTransaction();
echo "\n".pht('Done.')."\n";
