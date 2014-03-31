<?php

final class ReleephAuthorFieldSpecification
  extends ReleephFieldSpecification {

  private static $authorMap = array();

  public function getFieldKey() {
    return 'author';
  }

  public function bulkLoad(array $releeph_requests) {
    foreach ($releeph_requests as $releeph_request) {
      $commit = $releeph_request->loadPhabricatorRepositoryCommit();
      if ($commit) {
        $author_phid = $commit->getAuthorPHID();
        self::$authorMap[$releeph_request->getPHID()] = $author_phid;
      }
    }
  }

  public function getName() {
    return 'Author';
  }

  public function renderValueForHeaderView() {
    $rr = $this->getReleephRequest();
    $author_phid = idx(self::$authorMap, $rr->getPHID());
    if ($author_phid) {
      $handle = id(new PhabricatorHandleQuery())
        ->setViewer($this->getUser())
        ->withPHIDs(array($author_phid))
        ->executeOne();
      return $handle->renderLink();
    } else {
      return 'Unknown Author';
    }
  }

}
