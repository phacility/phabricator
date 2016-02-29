<?php

$table = new HarbormasterBuildPlan();

foreach (new LiskMigrationIterator($table) as $plan) {
  PhabricatorSearchWorker::queueDocumentForIndexing(
    $plan->getPHID(),
    array(
      'force' => true,
    ));
}
