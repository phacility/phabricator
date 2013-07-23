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


  public function getTagContent() {
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
          "C".$countdown->getID(),
          ' ',
          phutil_tag(
            'a',
            array(
              'href' => '/countdown/'.$countdown->getID(),
            ),
            $countdown->getTitle()),
        ));
    }


    $container = celerity_generate_unique_node_id();
    $content = hsprintf(
      '<div class="phabricator-timer" id="%s">
        %s
        <table class="phabricator-timer-table">
          <tr>
            <th>%s</th>
            <th>%s</th>
            <th>%s</th>
            <th>%s</th>
          </tr>
          <tr>%s%s%s%s</tr>
        </table>
      </div>',
      $container,
      $header,
      pht('Days'),
      pht('Hours'),
      pht('Minutes'),
      pht('Seconds'),
      javelin_tag('td', array('sigil' => 'phabricator-timer-days'), '-'),
      javelin_tag('td', array('sigil' => 'phabricator-timer-hours'), '-'),
      javelin_tag('td', array('sigil' => 'phabricator-timer-minutes'), '-'),
      javelin_tag('td', array('sigil' => 'phabricator-timer-seconds'), '-'));

    Javelin::initBehavior('countdown-timer', array(
      'timestamp' => $countdown->getEpoch(),
      'container' => $container,
    ));

    return $content;
  }

}
