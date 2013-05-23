<?php

/**
 * @group countdown
 */
final class PhabricatorCountdownRemarkupRule
  extends PhabricatorRemarkupRuleObject {

  protected function getObjectNamePrefix() {
    return 'C';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');
    return id(new CountdownQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();
  }

  protected function renderObjectEmbed($object, $handle, $options) {
    require_celerity_resource('javelin-behavior-countdown-timer');

    $prefix = 'phabricator-timer-';
    $counter = phutil_tag(
      'span',
      array(
        'id' => $object->getPHID(),
      ),
      array(
        javelin_tag('span', array('sigil' => $prefix.'days'), ''), 'd',
        javelin_tag('span', array('sigil' => $prefix.'hours'), ''), 'h',
        javelin_tag('span', array('sigil' => $prefix.'minutes'), ''), 'm',
        javelin_tag('span', array('sigil' => $prefix.'seconds'), ''), 's',
      ));

    Javelin::initBehavior('countdown-timer', array(
      'timestamp' => $object->getEpoch(),
      'container' => $object->getPHID(),
    ));

    return $counter;
  }

}
