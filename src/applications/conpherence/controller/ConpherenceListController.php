<?php

final class ConpherenceListController extends ConpherenceController {

  const SELECTED_MODE = 'selected';
  const UNSELECTED_MODE = 'unselected';

  /**
   * Two main modes of operation...
   *
   * 1 - /conpherence/ - UNSELECTED_MODE
   * 2 - /conpherence/<id>/ - SELECTED_MODE
   *
   * UNSELECTED_MODE is not an Ajax request while the other two are Ajax
   * requests.
   */
  private function determineMode() {
    $request = $this->getRequest();

    $mode = self::UNSELECTED_MODE;
    if ($request->isAjax()) {
      $mode = self::SELECTED_MODE;
    }

    return $mode;
  }

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $title = pht('Conpherence');
    $conpherence = null;

    $limit = ConpherenceThreadListView::SEE_MORE_LIMIT * 5;
    $all_participation = array();

    $mode = $this->determineMode();
    switch ($mode) {
      case self::SELECTED_MODE:
        $conpherence_id = $request->getURIData('id');
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
        $cursor = $conpherence->getParticipantIfExists($user->getPHID());
        $data = $this->loadDefaultParticipation($limit);
        $all_participation = $data['all_participation'];
        if (!$cursor) {
          $menu_participation = id(new ConpherenceParticipant())
            ->makeEphemeral()
            ->setConpherencePHID($conpherence->getPHID())
            ->setParticipantPHID($user->getPHID());
        } else {
          $menu_participation = $cursor;
        }
        $all_participation =
          array($conpherence->getPHID() => $menu_participation) +
          $all_participation;
        break;
      case self::UNSELECTED_MODE:
      default:
        $data = $this->loadDefaultParticipation($limit);
        $all_participation = $data['all_participation'];
        break;
    }

    $threads = $this->loadConpherenceThreadData(
      $all_participation);

    $thread_view = id(new ConpherenceThreadListView())
      ->setUser($user)
      ->setBaseURI($this->getApplicationURI())
      ->setThreads($threads);

    switch ($mode) {
      case self::SELECTED_MODE:
        $response = id(new AphrontAjaxResponse())->setContent($thread_view);
        break;
      case self::UNSELECTED_MODE:
      default:
        $layout = id(new ConpherenceLayoutView())
          ->setUser($user)
          ->setBaseURI($this->getApplicationURI())
          ->setThreadView($thread_view)
          ->setRole('list');
        if ($conpherence) {
          $policy_objects = id(new PhabricatorPolicyQuery())
            ->setViewer($user)
            ->setObject($conpherence)
            ->execute();
          $layout->setHeader($this->buildHeaderPaneContent(
            $conpherence,
            $policy_objects));
          $layout->setThread($conpherence);
        } else {
          $thread = ConpherenceThread::initializeNewThread($user);
          $thread->attachHandles(array());
          $thread->attachTransactions(array());
          $thread->makeEphemeral();
          $layout->setHeader(
            $this->buildHeaderPaneContent($thread, array()));
        }
        $response = $this->buildApplicationPage(
          $layout,
          array(
            'title' => $title,
          ));
        break;
    }

    return $response;

  }

  private function loadDefaultParticipation($limit) {
    $viewer = $this->getRequest()->getUser();

    $all_participation = id(new ConpherenceParticipantQuery())
      ->withParticipantPHIDs(array($viewer->getPHID()))
      ->setLimit($limit)
      ->execute();

    return array(
      'all_participation' => $all_participation,
    );
  }

  private function loadConpherenceThreadData($participation) {
    $user = $this->getRequest()->getUser();
    $conpherence_phids = array_keys($participation);
    $conpherences = array();
    if ($conpherence_phids) {
      $conpherences = id(new ConpherenceThreadQuery())
        ->setViewer($user)
        ->withPHIDs($conpherence_phids)
        ->needCropPics(true)
        ->needParticipantCache(true)
        ->execute();

      // this will re-sort by participation data
      $conpherences = array_select_keys($conpherences, $conpherence_phids);
    }

    return $conpherences;
  }

}
