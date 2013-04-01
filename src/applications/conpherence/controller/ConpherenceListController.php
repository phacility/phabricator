<?php

/**
 * @group conpherence
 */
final class ConpherenceListController
  extends ConpherenceController {

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

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $title = pht('Conpherence');

    $conpherence_id = $this->getConpherenceID();
    $current_selection_epoch = null;
    $conpherence = null;
    if ($conpherence_id) {
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

      $participant = $conpherence->getParticipant($user->getPHID());
      $current_selection_epoch = $participant->getDateTouched();
    }

    list($unread, $read) = $this->loadStartingConpherences(
      $current_selection_epoch);

    $thread_view = id(new ConpherenceThreadListView())
      ->setUser($user)
      ->setBaseURI($this->getApplicationURI())
      ->setUnreadThreads($unread)
      ->setReadThreads($read);

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())->setContent($thread_view);
    }

    $layout = id(new ConpherenceLayoutView())
      ->setBaseURI($this->getApplicationURI())
      ->setThreadView($thread_view)
      ->setRole('list');

    if ($conpherence) {
      $layout->setThread($conpherence);
    }

    return $this->buildApplicationPage(
      $layout,
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
