<?php

final class PholioMockTimelineEngine
  extends PhabricatorTimelineEngine {

  protected function newTimelineView() {
    $viewer = $this->getViewer();
    $object = $this->getObject();

    PholioMockQuery::loadImages(
      $viewer,
      array($object),
      $need_inline_comments = true);

    return id(new PholioTransactionView())
      ->setMock($object);
  }

}
