#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$revision = new DifferentialRevision();

$empty_revisions = queryfx_all(
  $revision->establishConnection('r'),
  'select distinct r.id from differential_revision r left join '.
  'differential_diff d on r.id=d.revisionID where d.revisionID is NULL');

$empty_revisions = ipull($empty_revisions, 'id');

if (!$empty_revisions) {
  echo "No empty revisions found.\n";
  exit(0);
}

echo phutil_console_wrap(
  "The following revision don't contain any diff:\n".
    implode(', ', $empty_revisions));

if (!phutil_console_confirm('Do you want to delete them?')) {
  echo "Cancelled.\n";
  exit(1);
}

foreach ($empty_revisions as $revision_id) {
  $revision = id(new DifferentialRevision())->load($revision_id);
  $revision->delete();
}

echo "Done.\n";
