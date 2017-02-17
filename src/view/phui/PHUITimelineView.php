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

    // Track why we're hiding older results.
    $hide_reason = null;

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

    if ($more) {
      $hide_reason = 'comment';
    }

    if ($this->getPager()) {
      if ($this->getPager()->getHasMoreResults()) {
        if (!$more) {
          $hide_reason = 'limit';
        }
        $more = true;
      }
    }

    $events = array();
    if ($more && $this->getPager()) {
      switch ($hide_reason) {
        case 'comment':
          $hide_help = pht(
            'Changes from before your most recent comment are hidden.');
          break;
        case 'limit':
        default:
          $hide_help = pht(
            'There are a very large number of changes, so older changes are '.
            'hidden.');
          break;
      }

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
          $hide_help,
          ' ',
          javelin_tag(
            'a',
            array(
              'href' => (string)$uri,
              'mustcapture' => true,
              'sigil' => 'show-older-link',
            ),
            pht('Show Older Changes')),
        ));

      if ($show) {
        $events[] = $spacer;
      }
    }

    if ($show) {
      $this->prepareBadgeData($show);
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
      $events[] = self::renderEnder();
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

  private function prepareBadgeData(array $events) {
    assert_instances_of($events, 'PHUITimelineEventView');

    $viewer = $this->getUser();
    $can_use_badges = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorBadgesApplication',
      $viewer);
    if (!$can_use_badges) {
      return;
    }

    $user_phid_type = PhabricatorPeopleUserPHIDType::TYPECONST;
    $badge_edge_type = PhabricatorRecipientHasBadgeEdgeType::EDGECONST;

    $user_phids = array();
    foreach ($events as $key => $event) {
      $author_phid = $event->getAuthorPHID();
      if (!$author_phid) {
        unset($events[$key]);
        continue;
      }

      if (phid_get_type($author_phid) != $user_phid_type) {
        // This is likely an application actor, like "Herald" or "Harbormaster".
        // They can't have badges.
        unset($events[$key]);
        continue;
      }

      $user_phids[$author_phid] = $author_phid;
    }

    if (!$user_phids) {
      return;
    }


    $awards = id(new PhabricatorBadgesAwardQuery())
      ->setViewer($this->getViewer())
      ->withRecipientPHIDs($user_phids)
      ->execute();

    $awards = mgroup($awards, 'getRecipientPHID');

    foreach ($events as $event) {

      $author_awards = idx($awards, $event->getAuthorPHID(), array());

      $badges = array();
      foreach ($author_awards as $award) {
        $badge = $award->getBadge();
        if ($badge->getStatus() == PhabricatorBadgesBadge::STATUS_ACTIVE) {
          $badges[$award->getBadgePHID()] = $badge;
        }
      }

      // TODO: Pick the "best" badges in some smart way. For now, just pick
      // the first two.
      $badges = array_slice($badges, 0, 2);

      foreach ($badges as $badge) {
        $badge_view = id(new PHUIBadgeMiniView())
          ->setIcon($badge->getIcon())
          ->setQuality($badge->getQuality())
          ->setHeader($badge->getName())
          ->setTipDirection('E')
          ->setHref('/badges/view/'.$badge->getID());

        $event->addBadge($badge_view);
      }
    }
  }

}
