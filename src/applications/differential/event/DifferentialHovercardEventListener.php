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

    $phids = array_merge(
      array(
        $rev->getAuthorPHID(),
      ),
      $reviewer_phids,
      $tasks);

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();

    $hovercard->setTitle('D'.$rev->getID());
    $hovercard->setDetail($rev->getTitle());

    $hovercard->addField(pht('Author'),
      $handles[$rev->getAuthorPHID()]->renderLink());

    $hovercard->addField(pht('Reviewers'),
      implode_selected_handle_links(', ', $handles, $reviewer_phids));

    if ($tasks) {
      $hovercard->addField(pht('%s Task(s)', new PhutilNumber(count($tasks))),
        implode_selected_handle_links(', ', $handles, $tasks));
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
