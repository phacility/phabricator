<?php

/**
 * @group slowvote
 */
final class SlowvoteRemarkupRule
  extends PhabricatorRemarkupRuleObject {

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

  protected function renderObjectEmbed($object, $handle, $options) {
    $viewer = $this->getEngine()->getConfig('viewer');

    $options = $object->getOptions();
    $choices = $object->getChoices();
    $viewer_choices = $object->getViewerChoices($viewer);

    $embed = id(new SlowvoteEmbedView())
      ->setPoll($object)
      ->setOptions($options)
      ->setViewerChoices($viewer_choices);

    return $embed->render();
  }

}
