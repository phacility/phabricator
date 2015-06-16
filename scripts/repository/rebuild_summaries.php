#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$commit = new PhabricatorRepositoryCommit();

$conn_w = id(new PhabricatorRepository())->establishConnection('w');
$sizes = queryfx_all(
  $conn_w,
  'SELECT repositoryID, count(*) N FROM %T GROUP BY repositoryID',
  $commit->getTableName());
$sizes = ipull($sizes, 'N', 'repositoryID');

$maxes = queryfx_all(
  $conn_w,
  'SELECT repositoryID, max(epoch) maxEpoch FROM %T GROUP BY repositoryID',
  $commit->getTableName());
$maxes = ipull($maxes, 'maxEpoch', 'repositoryID');


$repository_ids = array_keys($sizes + $maxes);

echo pht('Updating %d repositories', count($repository_ids));

foreach ($repository_ids as $repository_id) {
  $last_commit = queryfx_one(
    $conn_w,
    'SELECT id FROM %T WHERE repositoryID = %d AND epoch = %d LIMIT 1',
    $commit->getTableName(),
    $repository_id,
    idx($maxes, $repository_id, 0));
  if ($last_commit) {
    $last_commit = $last_commit['id'];
  } else {
    $last_commit = 0;
  }
  queryfx(
    $conn_w,
    'INSERT INTO %T (repositoryID, lastCommitID, size, epoch)
      VALUES (%d, %d, %d, %d) ON DUPLICATE KEY UPDATE
        lastCommitID = VALUES(lastCommitID),
        size = VALUES(size),
        epoch = VALUES(epoch)',
    PhabricatorRepository::TABLE_SUMMARY,
    $repository_id,
    $last_commit,
    idx($sizes, $repository_id, 0),
    idx($maxes, $repository_id, 0));
  echo '.';
}
echo "\n".pht('Done.')."\n";
