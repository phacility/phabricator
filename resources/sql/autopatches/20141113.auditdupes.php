<?php

$table = new PhabricatorRepositoryAuditRequest();
$conn_w = $table->establishConnection('w');

echo pht('Removing duplicate Audit requests...')."\n";
$seen_audit_map = array();
foreach (new LiskMigrationIterator($table) as $request) {
  $commit_phid = $request->getCommitPHID();
  $auditor_phid = $request->getAuditorPHID();
  if (isset($seen_audit_map[$commit_phid][$auditor_phid])) {
    $request->delete();
  }

  if (!isset($seen_audit_map[$commit_phid])) {
    $seen_audit_map[$commit_phid] = array();
  }

  $seen_audit_map[$commit_phid][$auditor_phid] = 1;
}

echo pht('Done.')."\n";
