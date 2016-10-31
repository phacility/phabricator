<?php

final class PhabricatorCalendarEventCancelController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    // Just check CAN_VIEW first. Then we'll check if this is an import so
    // we can raise a better error.
    $event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$event) {
      return new Aphront404Response();
    }

    $response = $this->newImportedEventResponse($event);
    if ($response) {
      return $response;
    }

    // Now that we've done the import check, check for CAN_EDIT.
    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $event,
      PhabricatorPolicyCapability::CAN_EDIT);

    $cancel_uri = $event->getURI();

    $is_parent = $event->isParentEvent();
    $is_child = $event->isChildEvent();

    $is_cancelled = $event->getIsCancelled();
    $is_recurring = $event->getIsRecurring();

    $validation_exception = null;
    if ($request->isFormPost()) {

      $targets = array();
      if ($is_recurring) {
        $mode = $request->getStr('mode');
        $is_future = ($mode == 'future');

        // We need to fork the event if we're cancelling just the parent, or
        // are cancelling a child and all future events.
        $must_fork = ($is_child && $is_future) ||
                     ($is_parent && !$is_future);

        if ($must_fork) {
          if ($is_child) {
            $xactions = array();

            $xaction = id(new PhabricatorCalendarEventTransaction())
              ->setTransactionType(
                PhabricatorCalendarEventForkTransaction::TRANSACTIONTYPE)
              ->setNewValue(true);

            $editor = id(new PhabricatorCalendarEventEditor())
              ->setActor($viewer)
              ->setContentSourceFromRequest($request)
              ->setContinueOnNoEffect(true)
              ->setContinueOnMissingFields(true);

            $editor->applyTransactions($event, array($xaction));

            $targets[] = $event;
          } else {
            // TODO: This is a huge mess; we need to load or generate the
            // first child, then fork that, then apply the event to the
            // parent. Just bail for now.
            throw new Exception(
              pht(
                'Individual edits to parent events are not yet supported '.
                'because they are real tricky to implement.'));
          }
        } else {
          $targets[] = $event;
        }

        if ($is_future) {
          // NOTE: If you can't edit some of the future events, we just
          // don't try to update them. This seems like it's probably what
          // users are likely to expect.

          // NOTE: This only affects events that are currently in the same
          // series, not all events that were ever in the original series.
          // We could use series PHIDs instead of parent PHIDs to affect more
          // events if this turns out to be counterintuitive. Other
          // applications differ in their behavior.

          $future = id(new PhabricatorCalendarEventQuery())
            ->setViewer($viewer)
            ->withParentEventPHIDs(array($event->getPHID()))
            ->withUTCInitialEpochBetween($event->getUTCInitialEpoch(), null)
            ->requireCapabilities(
              array(
                PhabricatorPolicyCapability::CAN_VIEW,
                PhabricatorPolicyCapability::CAN_EDIT,
              ))
            ->execute();
          foreach ($future as $future_event) {
            $targets[] = $future_event;
          }
        }
      } else {
        $targets = array($event);
      }

      foreach ($targets as $target) {
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
          $editor->applyTransactions($target, array($xaction));
        } catch (PhabricatorApplicationTransactionValidationException $ex) {
          $validation_exception = $ex;
          break;
        }

      }

      if (!$validation_exception) {
        return id(new AphrontRedirectResponse())->setURI($cancel_uri);
      }
    }

    if ($is_cancelled) {
      $title = pht('Reinstate Event');
      if ($is_recurring) {
        $body = pht(
          'This event is part of a series. Which events do you want to '.
          'reinstate?');
        $show_control = true;
      } else {
        $body = pht('Reinstate this event?');
        $show_control = false;
      }
      $submit = pht('Reinstate Event');
    } else {
      $title = pht('Cancel Event');
      if ($is_recurring) {
        $body = pht(
          'This event is part of a series. Which events do you want to '.
          'cancel?');
        $show_control = true;
      } else {
        $body = pht('Cancel this event?');
        $show_control = false;
      }
      $submit = pht('Cancel Event');
    }

    $dialog = $this->newDialog()
      ->setTitle($title)
      ->setValidationException($validation_exception)
      ->appendParagraph($body)
      ->addCancelButton($cancel_uri, pht('Back'))
      ->addSubmitButton($submit);

    if ($show_control) {
      $form = id(new AphrontFormView())
        ->setViewer($viewer)
        ->appendControl(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Cancel Events'))
            ->setName('mode')
            ->setOptions(
              array(
                'this' => pht('Only This Event'),
                'future' => pht('All Future Events'),
              )));
      $dialog->appendForm($form);
    }

    return $dialog;
  }
}
