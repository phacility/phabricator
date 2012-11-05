<?php

final class DiffusionSvnLastModifiedQuery extends DiffusionLastModifiedQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $path = $drequest->getPath();

    $history_query = DiffusionHistoryQuery::newFromDiffusionRequest(
      $drequest);
    $history_query->setLimit(1);
    $history_query->needChildChanges(true);
    $history_query->needDirectChanges(true);
    $history_array = $history_query->loadHistory();

    if (!$history_array) {
      return array(null, null);
    }

    $history = reset($history_array);

    return array($history->getCommit(), $history->getCommitData());
  }

}
