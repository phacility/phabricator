<?php

$table = new PhabricatorRepositoryCommit();
$conn = $table->establishConnection('w');

$rows = queryfx_all(
  $conn,
  'SELECT fullCommitName FROM repository_badcommit');

$viewer = PhabricatorUser::getOmnipotentUser();

foreach ($rows as $row) {
  $identifier = $row['fullCommitName'];

  $commit = id(new DiffusionCommitQuery())
    ->setViewer($viewer)
    ->withIdentifiers(array($identifier))
    ->executeOne();

  if (!$commit) {
    echo tsprintf(
      "%s\n",
      pht(
        'Skipped hint for "%s", this is not a valid commit.',
        $identifier));
  } else {
    PhabricatorRepositoryCommitHint::updateHint(
      $commit->getRepository()->getPHID(),
      $commit->getCommitIdentifier(),
      null,
      PhabricatorRepositoryCommitHint::HINT_UNREADABLE);

    echo tsprintf(
      "%s\n",
      pht(
        'Updated commit hint for "%s".',
        $identifier));
  }
}
