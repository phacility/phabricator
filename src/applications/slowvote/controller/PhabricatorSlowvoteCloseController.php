<?php

final class PhabricatorSlowvoteCloseController
  extends PhabricatorSlowvoteController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $poll = id(new PhabricatorSlowvoteQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$poll) {
      return new Aphront404Response();
    }

    $close_uri = '/V'.$poll->getID();

    if ($request->isFormPost()) {
      if ($poll->getIsClosed()) {
        $new_status = 0;
      } else {
        $new_status = 1;
      }

      $xactions = array();

      $xactions[] = id(new PhabricatorSlowvoteTransaction())
        ->setTransactionType(PhabricatorSlowvoteTransaction::TYPE_CLOSE)
        ->setNewValue($new_status);

      id(new PhabricatorSlowvoteEditor())
        ->setActor($user)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($poll, $xactions);

      return id(new AphrontRedirectResponse())->setURI($close_uri);
    }

    if ($poll->getIsClosed()) {
      $title = pht('Reopen Poll');
      $content = pht('Are you sure you want to reopen the poll?');
      $submit = pht('Reopen');
    } else {
      $title = pht('Close Poll');
      $content = pht('Are you sure you want to close the poll?');
      $submit = pht('Close');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendChild($content)
      ->addSubmitButton($submit)
      ->addCancelButton($close_uri);
  }

}
