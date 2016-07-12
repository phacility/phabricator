<?php

echo pht(
  "Populating Legalpad Documents with titles, %s, and %s...\n",
  'recentContributorPHIDs',
  'contributorCounts');
$table = new LegalpadDocument();
$table->openTransaction();

foreach (new LiskMigrationIterator($table) as $document) {
  $updated = false;
  $id = $document->getID();

  echo pht('Document %d: ', $id);
  if (!$document->getTitle()) {
    $document_body = id(new LegalpadDocumentBody())
      ->loadOneWhere('phid = %s', $document->getDocumentBodyPHID());
    $title = $document_body->getTitle();
    $document->setTitle($title);
    $updated = true;
    echo pht('Added title: %s', $title)."\n";
  } else {
    echo "-\n";
  }

  if (!$document->getContributorCount() ||
      !$document->getRecentContributorPHIDs()) {
    $updated = true;
    $type = PhabricatorObjectHasContributorEdgeType::EDGECONST;
    $contributors = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $document->getPHID(),
      $type);
    $document->setRecentContributorPHIDs(array_slice($contributors, 0, 3));
    echo pht('Added recent contributor PHIDs.')."\n";
    $document->setContributorCount(count($contributors));
    echo pht('Added contributor count.')."\n";
  }

  if (!$updated) {
    echo "-\n";
    continue;
  }

  $document->save();
}

$table->saveTransaction();
echo pht('Done.')."\n";
