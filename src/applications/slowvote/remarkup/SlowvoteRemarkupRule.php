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
      ->execute();
  }

  protected function renderObjectEmbed($object, $handle, $options) {
    $viewer = $this->getEngine()->getConfig('viewer');

    $options = id(new PhabricatorSlowvoteOption())->loadAllWhere(
      'pollID = %d',
      $object->getID());
    $choices = id(new PhabricatorSlowvoteChoice())->loadAllWhere(
      'pollID = %d',
      $object->getID());
    $choices_by_user = mgroup($choices, 'getAuthorPHID');

    $viewer_choices = idx($choices_by_user, $viewer->getPHID(), array());

    $embed = id(new SlowvoteEmbedView())
      ->setPoll($object)
      ->setOptions($options)
      ->setViewerChoices($viewer_choices);

    return $embed->render();
  }

}
