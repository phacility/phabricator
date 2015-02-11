<?php

echo 'Populating Legalpad Documents with ',
 "titles, recentContributorPHIDs, and contributorCounts...\n";
$table = new LegalpadDocument();
$table->openTransaction();

foreach (new LiskMigrationIterator($table) as $document) {
  $updated = false;
  $id = $document->getID();

  echo "Document {$id}: ";
  if (!$document->getTitle()) {
    $document_body = id(new LegalpadDocumentBody())
      ->loadOneWhere('phid = %s', $document->getDocumentBodyPHID());
    $title = $document_body->getTitle();
    $document->setTitle($title);
    $updated = true;
    echo "Added title: $title\n";
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
    echo "Added recent contributor phids.\n";
    $document->setContributorCount(count($contributors));
    echo "Added contributor count.\n";
  }

  if (!$updated) {
    echo "-\n";
    continue;
  }

  $document->save();
}

$table->saveTransaction();
echo "Done.\n";
