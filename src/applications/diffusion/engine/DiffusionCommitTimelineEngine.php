<?php

final class DiffusionCommitTimelineEngine
  extends PhabricatorTimelineEngine {

  protected function newTimelineView() {
    $xactions = $this->getTransactions();

    $path_ids = array();
    foreach ($xactions as $xaction) {
      if ($xaction->hasComment()) {
        $path_id = $xaction->getComment()->getPathID();
        if ($path_id) {
          $path_ids[] = $path_id;
        }
      }
    }

    $path_map = array();
    if ($path_ids) {
      $path_map = id(new DiffusionPathQuery())
        ->withPathIDs($path_ids)
        ->execute();
      $path_map = ipull($path_map, 'path', 'id');
    }

    return id(new PhabricatorAuditTransactionView())
      ->setPathMap($path_map);
  }
}
