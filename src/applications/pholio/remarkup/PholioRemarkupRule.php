<?php

final class PholioRemarkupRule extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return 'M';
  }

  protected function getObjectIDPattern() {
    // Match "M123", "M123/456", and "M123/456/". Users can hit the latter
    // forms when clicking comment anchors on a mock page.
    return '[1-9]\d*(?:/[1-9]\d*/?)?';
  }

  protected function getObjectHref(
    $object,
    PhabricatorObjectHandle $handle,
    $id) {

    $href = $handle->getURI();

    // If the ID has a `M123/456` component, link to that specific image.
    $id = explode('/', $id);
    if (isset($id[1])) {
      $href = $href.'/'.$id[1].'/';
    }

    if ($this->getEngine()->getConfig('uri.full')) {
      $href = PhabricatorEnv::getURI($href);
    }

    return $href;
  }

  protected function loadObjects(array $ids) {
    // Strip off any image ID components of the URI.
    $map = array();
    foreach ($ids as $id) {
      $map[head(explode('/', $id))][] = $id;
    }

    $viewer = $this->getEngine()->getConfig('viewer');
    $mocks = id(new PholioMockQuery())
      ->setViewer($viewer)
      ->needCoverFiles(true)
      ->needImages(true)
      ->needTokenCounts(true)
      ->withIDs(array_keys($map))
      ->execute();

    $results = array();
    foreach ($mocks as $mock) {
      $ids = idx($map, $mock->getID(), array());
      foreach ($ids as $id) {
        $results[$id] = $mock;
      }
    }

    return $results;
  }

  protected function renderObjectEmbed(
    $object,
    PhabricatorObjectHandle $handle,
    $options) {

    $viewer = $this->getEngine()->getConfig('viewer');

    $embed_mock = id(new PholioMockEmbedView())
      ->setUser($viewer)
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
