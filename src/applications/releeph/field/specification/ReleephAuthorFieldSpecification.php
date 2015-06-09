<?php

final class ReleephAuthorFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'author';
  }

  public function getName() {
    return pht('Author');
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    $pull = $this->getReleephRequest();
    $commit = $pull->loadPhabricatorRepositoryCommit();
    if (!$commit) {
      return array();
    }

    $author_phid = $commit->getAuthorPHID();
    if (!$author_phid) {
      return array();
    }

    return array($author_phid);
  }

  public function renderPropertyViewValue(array $handles) {
    return $this->renderHandleList($handles);
  }

}
