<?php

final class DiffusionHovercardEventListener extends PhutilEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERHOVERCARD);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_DIDRENDERHOVERCARD:
        $this->handleHovercardEvent($event);
      break;
    }
  }

  private function handleHovercardEvent($event) {
    $viewer = $event->getUser();
    $hovercard = $event->getValue('hovercard');
    $object_handle = $event->getValue('handle');
    $phid = $object_handle->getPHID();
    $commit = $event->getValue('object');

    if (!($commit instanceof PhabricatorRepositoryCommit)) {
      return;
    }

    $commit_data = $commit->loadCommitData();
    $revision = $commit_data->getCommitDetail('differential.revisionPHID');

    $author = $commit->getAuthorPHID();

    $phids = array_filter(array(
      $revision,
      $author,
    ));

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($viewer)
      ->loadHandles();

    if ($author) {
      $author = $handles[$author]->renderLink();
    } else {
      $author = phutil_tag('em', array(), $commit_data->getAuthorName());
    }

    $hovercard->setTitle($object_handle->getName());
    $hovercard->setDetail($commit->getSummary());

    $hovercard->addField(pht('Author'), $author);
    $hovercard->addField(pht('Date'),
      phabricator_date($commit->getEpoch(), $viewer));

    if ($commit->getAuditStatus() !=
      PhabricatorAuditCommitStatusConstants::NONE) {

      $hovercard->addField(pht('Audit Status'),
        PhabricatorAuditCommitStatusConstants::getStatusName(
          $commit->getAuditStatus()));
    }

    if ($revision) {
      $rev_handle = $handles[$revision];
      $hovercard->addField(pht('Revision'), $rev_handle->renderLink());
    }
    $hovercard->setColor(PhabricatorActionHeaderView::HEADER_YELLOW);

    $event->setValue('hovercard', $hovercard);
  }

}

