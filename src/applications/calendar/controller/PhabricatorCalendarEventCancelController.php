<?php

final class PhabricatorCalendarEventCancelController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$event) {
      return new Aphront404Response();
    }

    $cancel_uri = $event->getURI();

    $is_parent = $event->isParentEvent();
    $is_child = $event->isChildEvent();
    $is_cancelled = $event->getIsCancelled();

    if ($is_child) {
      $is_parent_cancelled = $event->getParentEvent()->getIsCancelled();
    } else {
      $is_parent_cancelled = false;
    }

    $validation_exception = null;
    if ($request->isFormPost()) {
      $xactions = array();

      $xaction = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(
          PhabricatorCalendarEventCancelTransaction::TRANSACTIONTYPE)
        ->setNewValue(!$is_cancelled);

      $editor = id(new PhabricatorCalendarEventEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      try {
        $editor->applyTransactions($event, array($xaction));
        return id(new AphrontRedirectResponse())->setURI($cancel_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
      }
    }

    if ($is_cancelled) {
      if ($is_parent_cancelled) {
        $title = pht('Cannot Reinstate Instance');
        $paragraph = pht(
          'You cannot reinstate an instance of a cancelled recurring event.');
        $cancel = pht('Back');
        $submit = null;
      } else if ($is_child) {
        $title = pht('Reinstate Instance');
        $paragraph = pht(
          'Reinstate this instance of this recurring event?');
        $cancel = pht('Back');
        $submit = pht('Reinstate Instance');
      } else if ($is_parent) {
        $title = pht('Reinstate Recurring Event');
        $paragraph = pht(
          'Reinstate all instances of this recurring event which have not '.
          'been individually cancelled?');
        $cancel = pht('Back');
        $submit = pht('Reinstate Recurring Event');
      } else {
        $title = pht('Reinstate Event');
        $paragraph = pht('Reinstate this event?');
        $cancel = pht('Back');
        $submit = pht('Reinstate Event');
      }
    } else {
      if ($is_child) {
        $title = pht('Cancel Instance');
        $paragraph = pht('Cancel this instance of this recurring event?');
        $cancel = pht('Back');
        $submit = pht('Cancel Instance');
      } else if ($is_parent) {
        $title = pht('Cancel Recurrin Event');
        $paragraph = pht('Cancel this entire series of recurring events?');
        $cancel = pht('Back');
        $submit = pht('Cancel Recurring Event');
      } else {
        $title = pht('Cancel Event');
        $paragraph = pht(
          'Cancel this event? You can always reinstate the event later.');
        $cancel = pht('Back');
        $submit = pht('Cancel Event');
      }
    }

    return $this->newDialog()
      ->setTitle($title)
      ->setValidationException($validation_exception)
      ->appendParagraph($paragraph)
      ->addCancelButton($cancel_uri, $cancel)
      ->addSubmitButton($submit);
  }
}
