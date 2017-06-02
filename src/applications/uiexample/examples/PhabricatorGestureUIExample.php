<?php

final class PhabricatorGestureUIExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Gestures');
  }

  public function getDescription() {
    return pht(
      'Use %s to listen for gesture events. Note that you '.
      'must be in device mode for this to work (you can narrow your browser '.
      'window if you are on a desktop).',
      phutil_tag('tt', array(), 'touchable'));
  }

  public function getCategory() {
    return pht('Technical');
  }

  public function renderExample() {
    $id = celerity_generate_unique_node_id();

    Javelin::initBehavior(
      'phabricator-gesture-example',
      array(
        'rootID' => $id,
      ));

    return javelin_tag(
      'div',
      array(
        'sigil' => 'touchable',
        'id' => $id,
        'style' => 'width: 320px; height: 240px; margin: auto;',
      ),
      '');
  }
}
