<?php

final class ReleephAuthorFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'author';
  }

  public function getName() {
    return 'Author';
  }

  public function renderPropertyViewValue(array $handles) {
    $pull = $this->getReleephRequest();
    $commit = $pull->loadPhabricatorRepositoryCommit();
    if (!$commit) {
      return null;
    }

    $author_phid = $commit->getAuthorPHID();
    if (!$author_phid) {
      return null;
    }

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($this->getUser())
      ->withPHIDs(array($author_phid))
      ->executeOne();

    return $handle->renderLink();
  }

}
