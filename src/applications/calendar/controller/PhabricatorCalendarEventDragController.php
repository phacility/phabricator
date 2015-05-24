<?php

final class PhabricatorCalendarEventDragController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$event) {
      return new Aphront404Response();
    }

    if (!$request->validateCSRF()) {
      return new Aphront400Response();
    }

    if ($event->getIsAllDay()) {
      return new Aphront400Response();
    }

    $xactions = array();

    $duration = $event->getDateTo() - $event->getDateFrom();

    $start = $request->getInt('start');
    $start_value = id(AphrontFormDateControlValue::newFromEpoch(
      $viewer,
      $start));

    $end = $start + $duration;
    $end_value = id(AphrontFormDateControlValue::newFromEpoch(
      $viewer,
      $end));


    $xactions[] = id(new PhabricatorCalendarEventTransaction())
      ->setTransactionType(
        PhabricatorCalendarEventTransaction::TYPE_START_DATE)
      ->setNewValue($start_value);

    $xactions[] = id(new PhabricatorCalendarEventTransaction())
      ->setTransactionType(
        PhabricatorCalendarEventTransaction::TYPE_END_DATE)
      ->setNewValue($end_value);


    $editor = id(new PhabricatorCalendarEventEditor())
      ->setActor($viewer)
      ->setContinueOnMissingFields(true)
      ->setContentSourceFromRequest($request)
      ->setContinueOnNoEffect(true);

    $xactions = $editor->applyTransactions($event, $xactions);

    return id(new AphrontReloadResponse());
  }
}
