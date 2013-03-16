<?php

/**
 * @group conpherence
 */
final class ConpherenceListController extends
  ConpherenceController {

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
      $this->setSelectedConpherencePHID($conpherence->getPHID());

      $participant = $conpherence->getParticipant($user->getPHID());
      $current_selection_epoch = $participant->getDateTouched();
    }

    $this->loadStartingConpherences($current_selection_epoch);
    $nav = $this->buildSideNavView();

    $main_pane = $this->renderEmptyMainPane();
    $nav->appendChild(
      array(
        $main_pane,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function renderEmptyMainPane() {
    $this->initJavelinBehaviors(true);
    return phutil_tag(
      'div',
      array(
        'id' => 'conpherence-main-pane'
      ),
      array(
        phutil_tag(
          'div',
          array(
            'class' => 'conpherence-header-pane',
            'id' => 'conpherence-header-pane',
          ),
          ''),
        phutil_tag(
          'div',
          array(
            'class' => 'conpherence-widget-pane',
            'id' => 'conpherence-widget-pane'
          ),
          ''),
        javelin_tag(
          'div',
          array(
            'class' => 'conpherence-message-pane',
            'id' => 'conpherence-message-pane'
          ),
          array(
            phutil_tag(
              'div',
              array(
                'class' => 'conpherence-messages',
                'id' => 'conpherence-messages'
              ),
              ''),
            phutil_tag(
              'div',
              array(
                'id' => 'conpherence-form'
              ),
              '')
          ))
      ));
  }


}
