<?php

final class DiffusionHovercardEventListener extends PhabricatorEventListener {

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
    $commit = $event->getValue('object');

    if (!($commit instanceof PhabricatorRepositoryCommit)) {
      return;
    }

    $commit_data = $commit->loadCommitData();

    $revision = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $commit->getPHID(),
      DiffusionCommitHasRevisionEdgeType::EDGECONST);
    $revision = reset($revision);

    $author = $commit->getAuthorPHID();

    $phids = array_filter(array(
      $revision,
      $author,
    ));

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();

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

    $event->setValue('hovercard', $hovercard);
  }

}
