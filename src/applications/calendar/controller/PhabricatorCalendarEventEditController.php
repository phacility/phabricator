<?php

final class PhabricatorCalendarEventEditController
  extends PhabricatorCalendarController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function isCreate() {
    return !$this->id;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $user_phid = $viewer->getPHID();
    $error_name = true;
    $error_recurrence_end_date = null;
    $error_start_date = true;
    $error_end_date = true;
    $validation_exception = null;

    $is_recurring_id = celerity_generate_unique_node_id();
    $recurrence_end_date_id = celerity_generate_unique_node_id();
    $frequency_id = celerity_generate_unique_node_id();
    $all_day_id = celerity_generate_unique_node_id();
    $start_date_id = celerity_generate_unique_node_id();
    $end_date_id = celerity_generate_unique_node_id();

    $next_workflow = $request->getStr('next');
    $uri_query = $request->getStr('query');

    if ($this->isCreate()) {
      $mode = $request->getStr('mode');
      $event = PhabricatorCalendarEvent::initializeNewCalendarEvent(
        $viewer,
        $mode);

      $create_start_year = $request->getInt('year');
      $create_start_month = $request->getInt('month');
      $create_start_day = $request->getInt('day');
      $create_start_time = $request->getStr('time');

      if ($create_start_year) {
        $start = AphrontFormDateControlValue::newFromParts(
          $viewer,
          $create_start_year,
          $create_start_month,
          $create_start_day,
          $create_start_time);
        if (!$start->isValid()) {
          return new Aphront400Response();
        }
        $start_value = AphrontFormDateControlValue::newFromEpoch(
          $viewer,
          $start->getEpoch());

        $end = clone $start_value->getDateTime();
        $end->modify('+1 hour');
        $end_value = AphrontFormDateControlValue::newFromEpoch(
          $viewer,
          $end->format('U'));

      } else {
        list($start_value, $end_value) = $this->getDefaultTimeValues($viewer);
      }

      $recurrence_end_date_value = clone $end_value;
      $recurrence_end_date_value->setOptional(true);

      $submit_label = pht('Create');
      $page_title = pht('Create Event');
      $redirect = 'created';
      $subscribers = array();
      $invitees = array($user_phid);
      $cancel_uri = $this->getApplicationURI();
    } else {
      $event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

      if (!$event) {
        return new Aphront404Response();
      }

      if ($request->getURIData('sequence')) {
        $index = $request->getURIData('sequence');

        $result = $this->getEventAtIndexForGhostPHID(
          $viewer,
          $event->getPHID(),
          $index);

        if ($result) {
          return id(new AphrontRedirectResponse())
            ->setURI('/calendar/event/edit/'.$result->getID().'/');
        }

        $event = $this->createEventFromGhost(
          $viewer,
          $event,
          $index);

        return id(new AphrontRedirectResponse())
          ->setURI('/calendar/event/edit/'.$event->getID().'/');
      }

      $end_value = AphrontFormDateControlValue::newFromEpoch(
        $viewer,
        $event->getDateTo());
      $start_value = AphrontFormDateControlValue::newFromEpoch(
        $viewer,
        $event->getDateFrom());
      $recurrence_end_date_value = id(clone $end_value)
        ->setOptional(true);

      $submit_label = pht('Update');
      $page_title   = pht('Update Event');

      $subscribers = PhabricatorSubscribersQuery::loadSubscribersForPHID(
        $event->getPHID());

      $invitees = array();
      foreach ($event->getInvitees() as $invitee) {
        if ($invitee->isUninvited()) {
          continue;
        } else {
          $invitees[] = $invitee->getInviteePHID();
        }
      }

      $cancel_uri = '/'.$event->getMonogram();
    }

    $name = $event->getName();
    $description = $event->getDescription();
    $is_all_day = $event->getIsAllDay();
    $is_recurring = $event->getIsRecurring();
    $is_parent = $event->getIsRecurrenceParent();
    $frequency = idx($event->getRecurrenceFrequency(), 'rule');
    $icon = $event->getIcon();

    $current_policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($event)
      ->execute();

    if ($request->isFormPost()) {
      $xactions = array();
      $name = $request->getStr('name');

      $start_value = AphrontFormDateControlValue::newFromRequest(
        $request,
        'start');
      $end_value = AphrontFormDateControlValue::newFromRequest(
        $request,
        'end');
      $recurrence_end_date_value = AphrontFormDateControlValue::newFromRequest(
        $request,
        'recurrenceEndDate');
      $recurrence_end_date_value->setOptional(true);
      $description = $request->getStr('description');
      $subscribers = $request->getArr('subscribers');
      $edit_policy = $request->getStr('editPolicy');
      $view_policy = $request->getStr('viewPolicy');
      $is_recurring = $request->getStr('isRecurring') ? 1 : 0;
      $frequency = $request->getStr('frequency');
      $is_all_day = $request->getStr('isAllDay');
      $icon = $request->getStr('icon');

      $invitees = $request->getArr('invitees');
      $new_invitees = $this->getNewInviteeList($invitees, $event);
      $status_attending = PhabricatorCalendarEventInvitee::STATUS_ATTENDING;
      if ($this->isCreate()) {
        $status = idx($new_invitees, $viewer->getPHID());
        if ($status) {
          $new_invitees[$viewer->getPHID()] = $status_attending;
        }
      }

      $xactions[] = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(
          PhabricatorCalendarEventTransaction::TYPE_NAME)
        ->setNewValue($name);

      if ($is_recurring && $this->isCreate()) {
        $xactions[] = id(new PhabricatorCalendarEventTransaction())
          ->setTransactionType(
            PhabricatorCalendarEventTransaction::TYPE_RECURRING)
          ->setNewValue($is_recurring);

        $xactions[] = id(new PhabricatorCalendarEventTransaction())
          ->setTransactionType(
            PhabricatorCalendarEventTransaction::TYPE_FREQUENCY)
          ->setNewValue(array('rule' => $frequency));

        if (!$recurrence_end_date_value->isDisabled()) {
          $xactions[] = id(new PhabricatorCalendarEventTransaction())
            ->setTransactionType(
              PhabricatorCalendarEventTransaction::TYPE_RECURRENCE_END_DATE)
            ->setNewValue($recurrence_end_date_value);
        }
      }

      if (($is_recurring && $this->isCreate()) || !$is_parent) {
        $xactions[] = id(new PhabricatorCalendarEventTransaction())
          ->setTransactionType(
            PhabricatorCalendarEventTransaction::TYPE_ALL_DAY)
          ->setNewValue($is_all_day);

        $xactions[] = id(new PhabricatorCalendarEventTransaction())
          ->setTransactionType(
            PhabricatorCalendarEventTransaction::TYPE_ICON)
          ->setNewValue($icon);

        $xactions[] = id(new PhabricatorCalendarEventTransaction())
          ->setTransactionType(
            PhabricatorCalendarEventTransaction::TYPE_START_DATE)
          ->setNewValue($start_value);

        $xactions[] = id(new PhabricatorCalendarEventTransaction())
          ->setTransactionType(
            PhabricatorCalendarEventTransaction::TYPE_END_DATE)
          ->setNewValue($end_value);
      }


      $xactions[] = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(
          PhabricatorTransactions::TYPE_SUBSCRIBERS)
        ->setNewValue(array('=' => array_fuse($subscribers)));

      $xactions[] = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(
          PhabricatorCalendarEventTransaction::TYPE_INVITE)
        ->setNewValue($new_invitees);

      $xactions[] = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(
          PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION)
        ->setNewValue($description);

      $xactions[] = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
        ->setNewValue($request->getStr('viewPolicy'));

      $xactions[] = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
        ->setNewValue($request->getStr('editPolicy'));

      $editor = id(new PhabricatorCalendarEventEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $xactions = $editor->applyTransactions($event, $xactions);
        $response = id(new AphrontRedirectResponse());
        switch ($next_workflow) {
          case 'day':
            if (!$uri_query) {
              $uri_query = 'month';
            }
            $year = $start_value->getDateTime()->format('Y');
            $month = $start_value->getDateTime()->format('m');
            $day = $start_value->getDateTime()->format('d');
            $response->setURI(
              '/calendar/query/'.$uri_query.'/'.$year.'/'.$month.'/'.$day.'/');
            break;
          default:
            $response->setURI('/E'.$event->getID());
            break;
        }
        return $response;
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $error_name = $ex->getShortMessage(
            PhabricatorCalendarEventTransaction::TYPE_NAME);
        $error_start_date = $ex->getShortMessage(
            PhabricatorCalendarEventTransaction::TYPE_START_DATE);
        $error_end_date = $ex->getShortMessage(
            PhabricatorCalendarEventTransaction::TYPE_END_DATE);
        $error_recurrence_end_date = $ex->getShortMessage(
            PhabricatorCalendarEventTransaction::TYPE_RECURRENCE_END_DATE);

        $event->setViewPolicy($view_policy);
        $event->setEditPolicy($edit_policy);
      }
    }

    $is_recurring_checkbox = null;
    $recurrence_end_date_control = null;
    $recurrence_frequency_select = null;

    $all_day_checkbox = null;
    $start_control = null;
    $end_control = null;

    $recurring_date_edit_label = null;

    $name = id(new AphrontFormTextControl())
      ->setLabel(pht('Name'))
      ->setName('name')
      ->setValue($name)
      ->setError($error_name);

    if ($this->isCreate()) {
      Javelin::initBehavior('recurring-edit', array(
        'isRecurring' => $is_recurring_id,
        'frequency' => $frequency_id,
        'recurrenceEndDate' => $recurrence_end_date_id,
      ));

      $is_recurring_checkbox = id(new AphrontFormCheckboxControl())
        ->addCheckbox(
          'isRecurring',
          1,
          pht('Recurring Event'),
          $is_recurring,
          $is_recurring_id);

      $recurrence_end_date_control = id(new AphrontFormDateControl())
        ->setUser($viewer)
        ->setName('recurrenceEndDate')
        ->setLabel(pht('Recurrence End Date'))
        ->setError($error_recurrence_end_date)
        ->setValue($recurrence_end_date_value)
        ->setID($recurrence_end_date_id)
        ->setIsTimeDisabled(true)
        ->setIsDisabled($recurrence_end_date_value->isDisabled())
        ->setAllowNull(true);

      $recurrence_frequency_select = id(new AphrontFormSelectControl())
        ->setName('frequency')
        ->setOptions(array(
            PhabricatorCalendarEvent::FREQUENCY_DAILY => pht('Daily'),
            PhabricatorCalendarEvent::FREQUENCY_WEEKLY => pht('Weekly'),
            PhabricatorCalendarEvent::FREQUENCY_MONTHLY => pht('Monthly'),
            PhabricatorCalendarEvent::FREQUENCY_YEARLY => pht('Yearly'),
          ))
        ->setValue($frequency)
        ->setLabel(pht('Recurring Event Frequency'))
        ->setID($frequency_id)
        ->setDisabled(!$is_recurring);
    }

    if ($this->isCreate() || (!$is_parent && !$this->isCreate())) {
      Javelin::initBehavior('event-all-day', array(
        'allDayID' => $all_day_id,
        'startDateID' => $start_date_id,
        'endDateID' => $end_date_id,
      ));

      $all_day_checkbox = id(new AphrontFormCheckboxControl())
        ->addCheckbox(
          'isAllDay',
          1,
          pht('All Day Event'),
          $is_all_day,
          $all_day_id);

      $start_control = id(new AphrontFormDateControl())
        ->setUser($viewer)
        ->setName('start')
        ->setLabel(pht('Start'))
        ->setError($error_start_date)
        ->setValue($start_value)
        ->setID($start_date_id)
        ->setIsTimeDisabled($is_all_day)
        ->setEndDateID($end_date_id);

      $end_control = id(new AphrontFormDateControl())
        ->setUser($viewer)
        ->setName('end')
        ->setLabel(pht('End'))
        ->setError($error_end_date)
        ->setValue($end_value)
        ->setID($end_date_id)
        ->setIsTimeDisabled($is_all_day);
    } else if ($is_parent) {
      $recurring_date_edit_label = id(new AphrontFormStaticControl())
        ->setUser($viewer)
        ->setValue(pht('Date and time of recurring event cannot be edited.'));

      if (!$recurrence_end_date_value->isDisabled()) {
        $disabled_recurrence_end_date_value =
          $recurrence_end_date_value->getValueAsFormat('M d, Y');
        $recurrence_end_date_control = id(new AphrontFormStaticControl())
          ->setUser($viewer)
          ->setLabel(pht('Recurrence End Date'))
          ->setValue($disabled_recurrence_end_date_value)
          ->setDisabled(true);
      }

      $recurrence_frequency_select = id(new AphrontFormSelectControl())
        ->setName('frequency')
        ->setOptions(array(
            'daily' => pht('Daily'),
            'weekly' => pht('Weekly'),
            'monthly' => pht('Monthly'),
            'yearly' => pht('Yearly'),
          ))
        ->setValue($frequency)
        ->setLabel(pht('Recurring Event Frequency'))
        ->setID($frequency_id)
        ->setDisabled(true);

      $all_day_checkbox = id(new AphrontFormCheckboxControl())
        ->addCheckbox(
          'isAllDay',
          1,
          pht('All Day Event'),
          $is_all_day,
          $all_day_id)
        ->setDisabled(true);

      $start_disabled = $start_value->getValueAsFormat('M d, Y, g:i A');
      $end_disabled = $end_value->getValueAsFormat('M d, Y, g:i A');

      $start_control = id(new AphrontFormStaticControl())
        ->setUser($viewer)
        ->setLabel(pht('Start'))
        ->setValue($start_disabled)
        ->setDisabled(true);

      $end_control = id(new AphrontFormStaticControl())
        ->setUser($viewer)
        ->setLabel(pht('End'))
        ->setValue($end_disabled);
    }

    $description = id(new PhabricatorRemarkupControl())
      ->setLabel(pht('Description'))
      ->setName('description')
      ->setValue($description)
      ->setUser($viewer);

    $view_policies = id(new AphrontFormPolicyControl())
      ->setUser($viewer)
      ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
      ->setPolicyObject($event)
      ->setPolicies($current_policies)
      ->setName('viewPolicy');
    $edit_policies = id(new AphrontFormPolicyControl())
      ->setUser($viewer)
      ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
      ->setPolicyObject($event)
      ->setPolicies($current_policies)
      ->setName('editPolicy');

    $subscribers = id(new AphrontFormTokenizerControl())
      ->setLabel(pht('Subscribers'))
      ->setName('subscribers')
      ->setValue($subscribers)
      ->setUser($viewer)
      ->setDatasource(new PhabricatorMetaMTAMailableDatasource());

    $invitees = id(new AphrontFormTokenizerControl())
      ->setLabel(pht('Invitees'))
      ->setName('invitees')
      ->setValue($invitees)
      ->setUser($viewer)
      ->setDatasource(new PhabricatorMetaMTAMailableDatasource());

    if ($this->isCreate()) {
      $icon_uri = $this->getApplicationURI('icon/');
    } else {
      $icon_uri = $this->getApplicationURI('icon/'.$event->getID().'/');
    }
    $icon_display = PhabricatorCalendarIcon::renderIconForChooser($icon);
    $icon = id(new AphrontFormChooseButtonControl())
      ->setLabel(pht('Icon'))
      ->setName('icon')
      ->setDisplayValue($icon_display)
      ->setButtonText(pht('Choose Icon...'))
      ->setChooseURI($icon_uri)
      ->setValue($icon);

    $form = id(new AphrontFormView())
      ->addHiddenInput('next', $next_workflow)
      ->addHiddenInput('query', $uri_query)
      ->setUser($viewer)
      ->appendChild($name);

    if ($recurring_date_edit_label) {
      $form->appendControl($recurring_date_edit_label);
    }
    if ($is_recurring_checkbox) {
      $form->appendChild($is_recurring_checkbox);
    }
    if ($recurrence_end_date_control) {
      $form->appendChild($recurrence_end_date_control);
    }
    if ($recurrence_frequency_select) {
      $form->appendControl($recurrence_frequency_select);
    }

    $form
      ->appendChild($all_day_checkbox)
      ->appendChild($start_control)
      ->appendChild($end_control)
      ->appendControl($view_policies)
      ->appendControl($edit_policies)
      ->appendControl($subscribers)
      ->appendControl($invitees)
      ->appendChild($description)
      ->appendChild($icon);


    if ($request->isAjax()) {
      return $this->newDialog()
        ->setTitle($page_title)
        ->setWidth(AphrontDialogView::WIDTH_FULL)
        ->appendForm($form)
        ->addCancelButton($cancel_uri)
        ->addSubmitButton($submit_label);
    }

    $submit = id(new AphrontFormSubmitControl())
      ->addCancelButton($cancel_uri)
      ->setValue($submit_label);

    $form->appendChild($submit);

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($page_title)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();

    if (!$this->isCreate()) {
      $crumbs->addTextCrumb('E'.$event->getId(), '/E'.$event->getId());
    }

    $crumbs->addTextCrumb($page_title);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText($page_title)
      ->setValidationException($validation_exception)
      ->appendChild($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        ),
      array(
        'title' => $page_title,
      ));
  }


  public function getNewInviteeList(array $phids, $event) {
    $invitees = $event->getInvitees();
    $invitees = mpull($invitees, null, 'getInviteePHID');
    $invited_status = PhabricatorCalendarEventInvitee::STATUS_INVITED;
    $uninvited_status = PhabricatorCalendarEventInvitee::STATUS_UNINVITED;
    $phids = array_fuse($phids);

    $new = array();
    foreach ($phids as $phid) {
      $old_status = $event->getUserInviteStatus($phid);
      if ($old_status != $uninvited_status) {
        continue;
      }
      $new[$phid] = $invited_status;
    }

    foreach ($invitees as $invitee) {
      $deleted_invitee = !idx($phids, $invitee->getInviteePHID());
      if ($deleted_invitee) {
        $new[$invitee->getInviteePHID()] = $uninvited_status;
      }
    }

    return $new;
  }

  private function getDefaultTimeValues($viewer) {
    $start = new DateTime('@'.time());
    $start->setTimeZone($viewer->getTimeZone());

    $start->setTime($start->format('H'), 0, 0);
    $start->modify('+1 hour');
    $end = id(clone $start)->modify('+1 hour');

    $start_value = AphrontFormDateControlValue::newFromEpoch(
      $viewer,
      $start->format('U'));
    $end_value = AphrontFormDateControlValue::newFromEpoch(
      $viewer,
      $end->format('U'));

    return array($start_value, $end_value);
  }

}
