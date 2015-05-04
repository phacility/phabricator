<?php

final class SlowvoteRemarkupRule extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return 'V';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    return id(new PhabricatorSlowvoteQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->needOptions(true)
      ->needChoices(true)
      ->needViewerChoices(true)
      ->execute();
  }

  protected function renderObjectEmbed(
    $object,
    PhabricatorObjectHandle $handle,
    $options) {

    $viewer = $this->getEngine()->getConfig('viewer');

    $embed = id(new SlowvoteEmbedView())
      ->setUser($viewer)
      ->setPoll($object);

    return $embed;
  }

}
