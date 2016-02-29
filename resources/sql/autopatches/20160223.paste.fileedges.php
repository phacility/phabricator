<?php

// For a while in November 2015, attachment edges between pastes and their
// underlying file data were not written correctly. This restores edges for
// any missing pastes.

$table = new PhabricatorPaste();
$edge_type = PhabricatorObjectHasFileEdgeType::EDGECONST;

foreach (new LiskMigrationIterator($table) as $paste) {
  $paste_phid = $paste->getPHID();
  $file_phid = $paste->getFilePHID();

  if (!$file_phid) {
    continue;
  }

  id(new PhabricatorEdgeEditor())
    ->addEdge($paste_phid, $edge_type, $file_phid)
    ->save();
}
