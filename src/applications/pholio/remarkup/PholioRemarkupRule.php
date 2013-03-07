<?php

final class PholioRemarkupRule
  extends PhabricatorRemarkupRuleObject {

  protected function getObjectNamePrefix() {
    return 'M';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');
    return id(new PholioMockQuery())
      ->setViewer($viewer)
      ->needImages(true)
      ->needTokenCounts(true)
      ->withIDs($ids)
      ->execute();
  }

  protected function renderObjectEmbed($object, $handle, $options) {
    $embed_mock = id(new PholioMockEmbedView())
      ->setMock($object);

    return $embed_mock->render();
  }

}
