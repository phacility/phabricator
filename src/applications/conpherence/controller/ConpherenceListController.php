<?php

/**
 * @group conpherence
 */
final class ConpherenceListController
  extends ConpherenceController {

  const SELECTED_MODE = 'selected';
  const UNSELECTED_MODE = 'unselected';
  const PAGING_MODE = 'paging';

  private $conpherenceID;

  public function setConpherenceID($conpherence_id) {
    $this->conpherenceID = $conpherence_id;
    return $this;
  }
  public function getConpherenceID() {
    return $this->conpherenceID;
  }

  public function willProcessRequest(array $data) {
    $this->setConpherenceID(idx($data, 'id'));
  }

  /**
   * Three main modes of operation...
   *
   * 1 - /conpherence/ - UNSELECTED_MODE
   * 2 - /conpherence/<id>/ - SELECTED_MODE
   * 3 - /conpherence/?direction='up'&... - PAGING_MODE
   *
   * UNSELECTED_MODE is not an Ajax request while the other two are Ajax
   * requests.
   */
  private function determineMode() {
    $request = $this->getRequest();

    $mode = self::UNSELECTED_MODE;
    if ($request->isAjax()) {
      if ($request->getStr('direction')) {
        $mode = self::PAGING_MODE;
      } else {
        $mode = self::SELECTED_MODE;
      }
    }

    return $mode;
  }
  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $title = pht('Conpherence');
    $conpherence = null;

    $scroll_up_participant = $this->getEmptyParticipant();
    $scroll_down_participant = $this->getEmptyParticipant();
    $too_many = ConpherenceParticipantQuery::LIMIT + 1;
    $all_participation = array();

    $mode = $this->determineMode();
    switch ($mode) {
      case self::SELECTED_MODE:
        $conpherence_id = $this->getConpherenceID();
        $conpherence = id(new ConpherenceThreadQuery())
          ->setViewer($user)
          ->withIDs(array($conpherence_id))
          ->executeOne();
        if (!$conpherence) {
          return new Aphront404Response();
        }
        if ($conpherence->getTitle()) {
          $title = $conpherence->getTitle();
        }
        $cursor = $conpherence->getParticipant($user->getPHID());
        $data = $this->loadParticipationWithMidCursor($cursor);
        $all_participation = $data['participation'];
        $scroll_up_participant = $data['scroll_up_participant'];
        $scroll_down_participant = $data['scroll_down_participant'];
        break;
      case self::PAGING_MODE:
        $direction = $request->getStr('direction');
        $id = $request->getInt('participant_id');
        $date_touched = $request->getInt('date_touched');
        $conpherence_phid = $request->getStr('conpherence_phid');
        if ($direction == 'up') {
          $order = ConpherenceParticipantQuery::ORDER_NEWER;
        } else {
          $order = ConpherenceParticipantQuery::ORDER_OLDER;
        }
        $scroller_participant = id(new ConpherenceParticipant())
          ->makeEphemeral()
          ->setID($id)
          ->setDateTouched($date_touched)
          ->setConpherencePHID($conpherence_phid);
        $participation = id(new ConpherenceParticipantQuery())
          ->withParticipantPHIDs(array($user->getPHID()))
          ->withParticipantCursor($scroller_participant)
          ->setOrder($order)
          ->setLimit($too_many)
          ->execute();
        if (count($participation) == $too_many) {
          if ($direction == 'up') {
            $node = $scroll_up_participant = reset($participation);
          } else {
            $node = $scroll_down_participant = end($participation);
          }
          unset($participation[$node->getConpherencePHID()]);
        }
        $all_participation = $participation;
        break;
      case self::UNSELECTED_MODE:
      default:
        $too_many = ConpherenceParticipantQuery::LIMIT + 1;
        $all_participation = id(new ConpherenceParticipantQuery())
          ->withParticipantPHIDs(array($user->getPHID()))
          ->setLimit($too_many)
          ->execute();
        if (count($all_participation) == $too_many) {
          $node = end($participation);
          unset($all_participation[$node->getConpherencePHID()]);
          $scroll_down_participant = $node;
        }
        break;
    }

    $threads = $this->loadConpherenceThreadData(
      $all_participation);

    $thread_view = id(new ConpherenceThreadListView())
      ->setUser($user)
      ->setBaseURI($this->getApplicationURI())
      ->setThreads($threads)
      ->setScrollUpParticipant($scroll_up_participant)
      ->setScrollDownParticipant($scroll_down_participant);

    switch ($mode) {
      case self::SELECTED_MODE:
        $response = id(new AphrontAjaxResponse())->setContent($thread_view);
        break;
      case self::PAGING_MODE:
        $thread_html = $thread_view->renderThreadsHTML();
        $phids = array_keys($participation);
        $content = array(
          'html' => $thread_html,
          'phids' => $phids);
        $response = id(new AphrontAjaxResponse())->setContent($content);
        break;
      case self::UNSELECTED_MODE:
      default:
        $layout = id(new ConpherenceLayoutView())
          ->setBaseURI($this->getApplicationURI())
          ->setThreadView($thread_view)
          ->setRole('list');
        if ($conpherence) {
          $layout->setThread($conpherence);
        }
        $response = $this->buildApplicationPage(
          $layout,
          array(
            'title' => $title,
            'device' => true,
          ));
        break;
    }

    return $response;

  }

  /**
   * Handles the curious case when we are visiting a conpherence directly
   * by issuing two separate queries. Otherwise, additional conpherences
   * are fetched asynchronously. Note these can be earlier or later
   * (up or down), depending on what conpherence was selected on initial
   * load.
   */
  private function loadParticipationWithMidCursor(
    ConpherenceParticipant $cursor) {

    $user = $this->getRequest()->getUser();

    $scroll_up_participant = $this->getEmptyParticipant();
    $scroll_down_participant = $this->getEmptyParticipant();

    // Note this is a bit dodgy since there may be less than this
    // amount in either the up or down direction, thus having us fail
    // to fetch LIMIT in total. Whatevs for now and re-visit if we're
    // fine-tuning this loading process.
    $too_many = ceil(ConpherenceParticipantQuery::LIMIT / 2) + 1;
    $participant_query = id(new ConpherenceParticipantQuery())
      ->withParticipantPHIDs(array($user->getPHID()))
      ->setLimit($too_many);
    $current_selection_epoch = $cursor->getDateTouched();
    $set_one = $participant_query
      ->withParticipantCursor($cursor)
      ->setOrder(ConpherenceParticipantQuery::ORDER_NEWER)
      ->execute();

    if (count($set_one) == $too_many) {
      $node = reset($set_one);
      unset($set_one[$node->getConpherencePHID()]);
      $scroll_up_participant = $node;
    }

    $set_two = $participant_query
      ->withParticipantCursor($cursor)
      ->setOrder(ConpherenceParticipantQuery::ORDER_OLDER)
      ->execute();

    if (count($set_two) == $too_many) {
      $node = end($set_two);
      unset($set_two[$node->getConpherencePHID()]);
      $scroll_down_participant = $node;
    }

    $participation = array_merge(
      $set_one,
      $set_two);

    return array(
      'scroll_up_participant' => $scroll_up_participant,
      'scroll_down_participant' => $scroll_down_participant,
      'participation' => $participation);
  }

  private function loadConpherenceThreadData($participation) {
    $user = $this->getRequest()->getUser();
    $conpherence_phids = array_keys($participation);
    $conpherences = array();
    if ($conpherence_phids) {
      $conpherences = id(new ConpherenceThreadQuery())
        ->setViewer($user)
        ->withPHIDs($conpherence_phids)
        ->needParticipantCache(true)
        ->execute();

      // this will re-sort by participation data
      $conpherences = array_select_keys($conpherences, $conpherence_phids);
    }

    return $conpherences;
  }

  private function getEmptyParticipant() {
    return id(new ConpherenceParticipant())
      ->makeEphemeral();
  }

}
