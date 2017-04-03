<?php


$use_mysql = false;

$services = PhabricatorSearchService::getAllServices();
foreach ($services as $service) {
  $engine = $service->getEngine();
  if ($engine instanceof PhabricatorMySQLFulltextStorageEngine) {
    $use_mysql = true;
  }
}

if ($use_mysql) {
  $field = new PhabricatorSearchDocumentField();
  $conn = $field->establishConnection('r');

  // We're only going to require this if the index isn't empty: if you're on a
  // fresh install, you don't have to do anything.
  $any_documents = queryfx_one(
    $conn,
    'SELECT * FROM %T LIMIT 1',
    $field->getTableName());

  if ($any_documents) {
    try {
      id(new PhabricatorConfigManualActivity())
        ->setActivityType(PhabricatorConfigManualActivity::TYPE_REINDEX)
        ->save();
    } catch (AphrontDuplicateKeyQueryException $ex) {
      // If we've already noted that this activity is required, just move on.
    }
  }
}
