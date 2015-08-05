<?php

final class PhabricatorCountdownView extends AphrontTagView {

  private $countdown;
  private $headless;


  public function setHeadless($headless) {
    $this->headless = $headless;
    return $this;
  }

  public function setCountdown(PhabricatorCountdown $countdown) {
    $this->countdown = $countdown;
    return $this;
  }


  protected function getTagContent() {
    $countdown = $this->countdown;

    require_celerity_resource('phabricator-countdown-css');

    $header = null;
    if (!$this->headless) {
      $header = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-timer-header',
        ),
        array(
          'C'.$countdown->getID(),
          ' ',
          phutil_tag(
            'a',
            array(
              'href' => '/countdown/'.$countdown->getID(),
            ),
            $countdown->getTitle()),
        ));
    }


    $ths = array(
      phutil_tag('th', array(), pht('Days')),
      phutil_tag('th', array(), pht('Hours')),
      phutil_tag('th', array(), pht('Minutes')),
      phutil_tag('th', array(), pht('Seconds')),
    );

    $dashes = array(
      javelin_tag('td', array('sigil' => 'phabricator-timer-days'), '-'),
      javelin_tag('td', array('sigil' => 'phabricator-timer-hours'), '-'),
      javelin_tag('td', array('sigil' => 'phabricator-timer-minutes'), '-'),
      javelin_tag('td', array('sigil' => 'phabricator-timer-seconds'), '-'),
    );

    $epoch = $countdown->getEpoch();
    $launch_date = phabricator_datetime($epoch, $this->getUser());
    $foot = phutil_tag(
      'td',
      array(
        'colspan' => '4',
        'class' => 'phabricator-timer-foot',
      ),
      $launch_date);

    $container = celerity_generate_unique_node_id();
    $content = phutil_tag(
      'div',
      array('class' => 'phabricator-timer', 'id' => $container),
      array(
        $header,
        phutil_tag('table', array('class' => 'phabricator-timer-table'), array(
          phutil_tag('tr', array(), $ths),
          phutil_tag('tr', array(), $dashes),
          phutil_tag('tr', array(), $foot),
        )),
      ));

    Javelin::initBehavior('countdown-timer', array(
      'timestamp' => $countdown->getEpoch(),
      'container' => $container,
    ));

    return $content;
  }

}
