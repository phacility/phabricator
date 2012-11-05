<?php

final class DiffusionSvnCommitParentsQuery
  extends DiffusionCommitParentsQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    // TODO: With merge properties in recent versions of SVN, can we do
    // a better job of this?

    $n = $drequest->getStableCommitName();
    if ($n > 1) {
      $ids = array($n - 1);
    } else {
      $ids = array();
    }

    return self::loadCommitsByIdentifiers($ids);
  }
}
