<?php

final class PhabricatorTooltipUIExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Tooltips');
  }

  public function getDescription() {
    return pht(
      'Use %s to create tooltips.',
      phutil_tag('tt', array(), 'JX.Tooltip'));
  }

  public function renderExample() {

    Javelin::initBehavior('phabricator-tooltips');
    require_celerity_resource('aphront-tooltip-css');

    $style = 'width: 200px; '.
             'text-align: center; '.
             'margin: 20px; '.
             'background: #dfdfdf; '.
             'padding: 20px 10px; '.
             'border: 1px solid black; ';

    $lorem = <<<EOTEXT
Lorem ipsum dolor sit amet, consectetur adipiscing elit.
EOTEXT;

    $overflow = str_repeat('M', 1024);

    $metas = array(
      'hi' => array(
        'tip' => 'Hi',
      ),
      'lorem (north)' => array(
        'tip' => $lorem,
      ),
      'lorem (east)' => array(
        'tip' => $lorem,
        'align' => 'E',
      ),
      'lorem (south)' => array(
        'tip' => $lorem,
        'align' => 'S',
      ),
      'lorem (west)' => array(
        'tip' => $lorem,
        'align' => 'W',
      ),
      'lorem (large, north)' => array(
        'tip' => $lorem,
        'size' => 300,
      ),
      'lorem (large, east)' => array(
        'tip' => $lorem,
        'size' => 300,
        'align' => 'E',
      ),
      'lorem (large, west)' => array(
        'tip' => $lorem,
        'size' => 300,
        'align' => 'W',
      ),
      'lorem (large, south)' => array(
        'tip' => $lorem,
        'size' => 300,
        'align' => 'S',
      ),
      'overflow (north)' => array(
        'tip' => $overflow,
      ),
      'overflow (east)' => array(
        'tip' => $overflow,
        'align' => 'E',
      ),
      'overflow (south)' => array(
        'tip' => $overflow,
        'align' => 'S',
      ),
      'overflow (west)' => array(
        'tip' => $overflow,
        'align' => 'W',
      ),
    );

    $content = array();
    foreach ($metas as $key => $meta) {
      $content[] = javelin_tag(
        'div',
        array(
          'sigil' => 'has-tooltip',
          'meta'  => $meta,
          'style' => $style,
        ),
        $key);
    }

    return $content;
  }
}
