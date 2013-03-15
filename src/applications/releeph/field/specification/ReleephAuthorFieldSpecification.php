<?php

final class ReleephAuthorFieldSpecification
  extends ReleephFieldSpecification {

  private static $authorMap = array();

  public function bulkLoad(array $releeph_requests) {
    foreach ($releeph_requests as $releeph_request) {
      $commit = $releeph_request->loadPhabricatorRepositoryCommit();
      if ($commit) {
        $author_phid = $commit->getAuthorPHID();
        self::$authorMap[$releeph_request->getPHID()] = $author_phid;
      }
    }

    ReleephUserView::getNewInstance()
      ->setUser($this->getUser())
      ->setReleephProject($this->getReleephProject())
      ->load(self::$authorMap);
  }

  public function getName() {
    return 'Author';
  }

  public function renderValueForHeaderView() {
    $rr = $this->getReleephRequest();
    $author_phid = idx(self::$authorMap, $rr->getPHID());
    if ($author_phid) {
      return ReleephUserView::getNewInstance()
        ->setRenderUserPHID($author_phid)
        ->render();
    } else {
      return 'Unknown Author';
    }
  }

}
