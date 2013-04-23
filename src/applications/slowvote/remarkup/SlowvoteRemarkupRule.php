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
    $polls = array(id(new PhabricatorSlowvotePoll())->load(head($ids)));

    return id(new PhabricatorSlowvotePoll())
      ->loadAllWhere('id IN (%Ld)', $ids);
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
