<?php

final class PhabricatorCalendarEventCancelController
  extends PhabricatorCalendarController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request  = $this->getRequest();
    $user     = $request->getUser();

    $status = id(new PhabricatorCalendarEventQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    if (!$status) {
      return new Aphront404Response();
    }

    $cancel_uri = '/E'.$status->getID();
    $validation_exception = null;
    $is_cancelled = $status->getIsCancelled();

    if ($request->isFormPost()) {
      $xactions = array();

      $xaction = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(
          PhabricatorCalendarEventTransaction::TYPE_CANCEL)
        ->setNewValue(!$is_cancelled);

      $editor = id(new PhabricatorCalendarEventEditor())
        ->setActor($user)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      try {
        $editor->applyTransactions($status, array($xaction));
        return id(new AphrontRedirectResponse())->setURI($cancel_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
      }
    }

    if ($is_cancelled) {
      $title = pht('Reinstate Event');
      $paragraph = pht('Reinstate this event?');
      $cancel = pht('Don\'t Reinstate Event');
      $submit = pht('Reinstate Event');
    } else {
      $title = pht('Cancel Event');
      $paragraph = pht('You can always reinstate the event later.');
      $cancel = pht('Don\'t Cancel Event');
      $submit = pht('Cancel Event');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->setValidationException($validation_exception)
      ->appendParagraph($paragraph)
      ->addCancelButton($cancel_uri, $cancel)
      ->addSubmitButton($submit);
  }
}
