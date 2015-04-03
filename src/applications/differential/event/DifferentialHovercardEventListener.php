<?php

final class DifferentialHovercardEventListener
  extends PhabricatorEventListener {

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
    $rev = $event->getValue('object');

    if (!($rev instanceof DifferentialRevision)) {
      return;
    }

    $rev->loadRelationships();
    $reviewer_phids = $rev->getReviewers();
    $e_task = DifferentialRevisionHasTaskEdgeType::EDGECONST;
    $edge_query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($phid))
      ->withEdgeTypes(
        array(
          $e_task,
        ));
    $edge_query->execute();
    $tasks = $edge_query->getDestinationPHIDs();

    $hovercard->setTitle('D'.$rev->getID());
    $hovercard->setDetail($rev->getTitle());

    $hovercard->addField(
      pht('Author'),
      $viewer->renderHandle($rev->getAuthorPHID()));

    $hovercard->addField(
      pht('Reviewers'),
      $viewer->renderHandleList($reviewer_phids)->setAsInline(true));

    if ($tasks) {
      $hovercard->addField(
        pht('Tasks'),
        $viewer->renderHandleList($tasks)->setAsInline(true));
    }

    if ($rev->getSummary()) {
      $hovercard->addField(pht('Summary'),
        id(new PhutilUTF8StringTruncator())
        ->setMaximumGlyphs(120)
        ->truncateString($rev->getSummary()));
    }

    $hovercard->addTag(
      DifferentialRevisionDetailView::renderTagForRevision($rev));

    $event->setValue('hovercard', $hovercard);
  }

}
