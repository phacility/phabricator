<?php

final class PhabricatorRepositoryTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME         = 'repo:name';
  const TYPE_DESCRIPTION  = 'repo:description';

  public function getApplicationName() {
    return 'repository';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPHIDConstants::PHID_TYPE_REPO;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getApplicationObjectTypeName() {
    return pht('repository');
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        return pht(
          '%s renamed this repository from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description of this repository.',
          $this->renderHandleLink($author_phid));
    }

    return parent::getTitle();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return true;
    }
    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $view = id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setUser($viewer)
      ->setOldText($old)
      ->setNewText($new);

    return $view->render();
  }


}

