<?php

final class PHUITimelineView extends AphrontView {

  private $events = array();
  private $id;
  private $shouldTerminate = false;
  private $shouldAddSpacers = true;
  private $pager;
  private $renderData = array();
  private $quoteTargetID;
  private $quoteRef;

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function setShouldTerminate($term) {
    $this->shouldTerminate = $term;
    return $this;
  }

  public function setShouldAddSpacers($bool) {
    $this->shouldAddSpacers = $bool;
    return $this;
  }

  public function setPager(AphrontCursorPagerView $pager) {
    $this->pager = $pager;
    return $this;
  }

  public function getPager() {
    return $this->pager;
  }

  public function addEvent(PHUITimelineEventView $event) {
    $this->events[] = $event;
    return $this;
  }

  public function setRenderData(array $data) {
    $this->renderData = $data;
    return $this;
  }

  public function setQuoteTargetID($quote_target_id) {
    $this->quoteTargetID = $quote_target_id;
    return $this;
  }

  public function getQuoteTargetID() {
    return $this->quoteTargetID;
  }

  public function setQuoteRef($quote_ref) {
    $this->quoteRef = $quote_ref;
    return $this;
  }

  public function getQuoteRef() {
    return $this->quoteRef;
  }

  public function render() {
    if ($this->getPager()) {
      if ($this->id === null) {
        $this->id = celerity_generate_unique_node_id();
      }
      Javelin::initBehavior(
        'phabricator-show-older-transactions',
        array(
          'timelineID' => $this->id,
          'renderData' => $this->renderData,
        ));
    }
    $events = $this->buildEvents();

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-timeline-view',
        'id' => $this->id,
      ),
      $events);
  }

  public function buildEvents() {
    require_celerity_resource('phui-timeline-view-css');

    $spacer = self::renderSpacer();

    $hide = array();
    $show = array();

    // Bucket timeline events into events we'll hide by default (because they
    // predate your most recent interaction with the object) and events we'll
    // show by default.
    foreach ($this->events as $event) {
      if ($event->getHideByDefault()) {
        $hide[] = $event;
      } else {
        $show[] = $event;
      }
    }

    // If you've never interacted with the object, all the events will be shown
    // by default. We may still need to paginate if there are a large number
    // of events.
    $more = (bool)$hide;
    if ($this->getPager()) {
      if ($this->getPager()->getHasMoreResults()) {
        $more = true;
      }
    }

    $events = array();
    if ($more && $this->getPager()) {
      $uri = $this->getPager()->getNextPageURI();
      $uri->setQueryParam('quoteTargetID', $this->getQuoteTargetID());
      $uri->setQueryParam('quoteRef', $this->getQuoteRef());
      $events[] = javelin_tag(
        'div',
        array(
          'sigil' => 'show-older-block',
          'class' => 'phui-timeline-older-transactions-are-hidden',
        ),
        array(
          pht('Older changes are hidden. '),
          ' ',
          javelin_tag(
            'a',
            array(
              'href' => (string) $uri,
              'mustcapture' => true,
              'sigil' => 'show-older-link',
            ),
            pht('Show older changes.')),
        ));

      if ($show) {
        $events[] = $spacer;
      }
    }

    if ($show) {
      $events[] = phutil_implode_html($spacer, $show);
    }

    if ($events) {
      if ($this->shouldAddSpacers) {
        $events = array($spacer, $events, $spacer);
      }
    } else {
      $events = array($spacer);
    }

    if ($this->shouldTerminate) {
      $events[] = self::renderEnder(true);
    }

    return $events;
  }

  public static function renderSpacer() {
    return phutil_tag(
      'div',
      array(
        'class' => 'phui-timeline-event-view '.
                   'phui-timeline-spacer',
      ),
      '');
  }

  public static function renderEnder() {
    return phutil_tag(
      'div',
      array(
        'class' => 'phui-timeline-event-view '.
                   'the-worlds-end',
      ),
      '');
  }

}
