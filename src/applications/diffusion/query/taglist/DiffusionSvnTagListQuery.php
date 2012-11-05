<?php

final class DiffusionSvnTagListQuery extends DiffusionTagListQuery {

  protected function executeQuery() {
    // Nothing meaningful to be done in Subversion.
    return array();
  }

}
