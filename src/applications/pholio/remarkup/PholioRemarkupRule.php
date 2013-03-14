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

    if (strlen($options)) {
      $parser = new PhutilSimpleOptions();
      $opts = $parser->parse(substr($options, 1));

      if (isset($opts['image'])) {
        $images = array_unique(
          explode('&', preg_replace('/\s+/', '', $opts['image'])));

        $embed_mock->setImages($images);
      }
    }

    return $embed_mock->render();
  }

}
