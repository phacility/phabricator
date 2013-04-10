<?php

final class DifferentialHovercardEventListener extends PhutilEventListener {

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
    $e_task = PhabricatorEdgeConfig::TYPE_DREV_HAS_RELATED_TASK;
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

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($viewer)
      ->loadHandles();

    $hovercard->setTitle('D'.$rev->getID());
    $hovercard->setDetail($rev->getTitle());

    $hovercard->addField(pht('Author'),
      $handles[$rev->getAuthorPHID()]->renderLink());

    $hovercard->addField(pht('Date'),
      phabricator_datetime($rev->getDateModified(), $viewer));

    $hovercard->addField(pht('Reviewers'),
      implode_selected_handle_links(', ', $handles, $reviewer_phids));

    if ($tasks) {
      $hovercard->addField(pht('Task(s)', count($tasks)),
        implode_selected_handle_links(', ', $handles, $tasks));
    }

    if ($rev->getSummary()) {
      $hovercard->addField(pht('Summary'),
        phutil_utf8_shorten($rev->getSummary(), 120));
    }

    $hovercard->addTag(
      DifferentialRevisionDetailView::renderTagForRevision($rev));

    $event->setValue('hovercard', $hovercard);
  }

}

