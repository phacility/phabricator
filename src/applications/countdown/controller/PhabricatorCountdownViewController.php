<?php

final class PhabricatorCountdownViewController
  extends PhabricatorCountdownController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }


  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $timer = id(new PhabricatorTimer())->load($this->id);
    if (!$timer) {
      return new Aphront404Response();
    }

    require_celerity_resource('phabricator-countdown-css');

    $chrome_visible = $request->getBool('chrome', true);
    $chrome_new = $chrome_visible ? false : null;
    $chrome_link = phutil_tag(
      'a',
      array(
        'href' => $request->getRequestURI()->alter('chrome', $chrome_new),
        'class' => 'phabricator-timer-chrome-link',
      ),
      $chrome_visible ? pht('Disable Chrome') : pht('Enable Chrome'));

    $container = celerity_generate_unique_node_id();
    $content = hsprintf(
      '<div class="phabricator-timer" id="%s">
        <h1 class="phabricator-timer-header">%s &middot; %s</h1>
        <div class="phabricator-timer-pane">
          <table class="phabricator-timer-table">
            <tr>
              <th>%s</th>
              <th>%s</th>
              <th>%s</th>
              <th>%s</th>
            </tr>
            <tr>%s%s%s%s</tr>
          </table>
        </div>
        %s
      </div>',
      $container,
      $timer->getTitle(),
      phabricator_datetime($timer->getDatePoint(), $user),
      pht('Days'),
      pht('Hours'),
      pht('Minutes'),
      pht('Seconds'),
      javelin_tag('td', array('sigil' => 'phabricator-timer-days'), ''),
      javelin_tag('td', array('sigil' => 'phabricator-timer-hours'), ''),
      javelin_tag('td', array('sigil' => 'phabricator-timer-minutes'), ''),
      javelin_tag('td', array('sigil' => 'phabricator-timer-seconds'), ''),
      $chrome_link);

    Javelin::initBehavior('countdown-timer', array(
      'timestamp' => $timer->getDatepoint(),
      'container' => $container,
    ));

    $panel = $content;

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($timer->getTitle())
          ->setHref($this->getApplicationURI($this->id.'/')));

    return $this->buildApplicationPage(
      array(
        ($chrome_visible ? $crumbs : ''),
        $panel,
      ),
      array(
        'title' => pht('Countdown: %s', $timer->getTitle()),
        'chrome' => $chrome_visible,
        'device' => true,
      ));
  }

}
