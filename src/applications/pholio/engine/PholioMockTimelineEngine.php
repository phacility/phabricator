<?php

final class PholioMockTimelineEngine
  extends PhabricatorTimelineEngine {

  protected function newTimelineView() {
    $viewer = $this->getViewer();
    $object = $this->getObject();

    $images = id(new PholioImageQuery())
      ->setViewer($viewer)
      ->withMocks(array($object))
      ->needInlineComments(true)
      ->execute();

    $object->attachImages($images);

    return id(new PholioTransactionView())
      ->setMock($object);
  }

}
