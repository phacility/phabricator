<?php

final class DiffusionMercurialExpandShortNameQuery
extends DiffusionExpandShortNameQuery {

  protected function executeQuery() {
    $repository = $this->getRepository();
    $commit = $this->getCommit();

    list($full_hash) = $repository->execxLocalCommand(
      'log --template=%s --rev %s',
      '{node}',
      $commit);

    $full_hash = explode("\n", trim($full_hash));

    // TODO: Show "multiple matching commits" if count is larger than 1. For
    // now, pick the first one.

    $this->setCommit(head($full_hash));
  }

}
