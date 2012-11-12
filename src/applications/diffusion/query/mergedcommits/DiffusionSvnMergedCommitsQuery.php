<?php

final class DiffusionSvnMergedCommitsQuery extends DiffusionMergedCommitsQuery {

  protected function executeQuery() {
    // TODO: It might be possible to do something reasonable in recent versions
    // of SVN.
    return array();
  }

}
