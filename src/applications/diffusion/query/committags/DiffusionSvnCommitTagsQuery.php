<?php

final class DiffusionSvnCommitTagsQuery
  extends DiffusionCommitTagsQuery {

  protected function executeQuery() {
    // No meaningful concept of tags in Subversion.
    return array();
  }

}
