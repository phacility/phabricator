<?php

final class DiffusionSvnContainsQuery extends DiffusionContainsQuery {

  protected function executeQuery() {
    // NOTE: Subversion doesn't have branches, so we always return empty.
    return array();
  }

}
