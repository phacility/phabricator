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

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $user_phid = $user->getPHID();
    $error_name = true;
    $error_start_date = true;
    $error_end_date = true;
    $validation_exception = null;

    if ($this->isCreate()) {
      $event = PhabricatorCalendarEvent::initializeNewCalendarEvent($user);
      $end_value = AphrontFormDateControlValue::newFromEpoch($user, time());
      $start_value = AphrontFormDateControlValue::newFromEpoch($user, time());
      $submit_label = pht('Create');
      $page_title = pht('Create Event');
      $redirect = 'created';
      $subscribers = array();
      $invitees = array($user_phid);
      $cancel_uri = $this->getApplicationURI();
    } else {
      $event = id(new PhabricatorCalendarEventQuery())
        ->setViewer($user)
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

      $end_value = AphrontFormDateControlValue::newFromEpoch(
        $user,
        $event->getDateTo());
      $start_value = AphrontFormDateControlValue::newFromEpoch(
        $user,
        $event->getDateFrom());

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
    $type = $event->getStatus();
    $is_all_day = $event->getIsAllDay();

    $current_policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($event)
      ->execute();

    if ($request->isFormPost()) {
      $xactions = array();
      $name = $request->getStr('name');
      $type = $request->getInt('status');

      $start_value = AphrontFormDateControlValue::newFromRequest(
        $request,
        'start');
      $end_value = AphrontFormDateControlValue::newFromRequest(
        $request,
        'end');
      $description = $request->getStr('description');
      $subscribers = $request->getArr('subscribers');
      $edit_policy = $request->getStr('editPolicy');
      $view_policy = $request->getStr('viewPolicy');
      $is_all_day = $request->getStr('isAllDay');

      $invitees = $request->getArr('invitees');
      $new_invitees = $this->getNewInviteeList($invitees, $event);
      $status_attending = PhabricatorCalendarEventInvitee::STATUS_ATTENDING;
      if ($this->isCreate()) {
        $status = idx($new_invitees, $user->getPHID());
        if ($status) {
          $new_invitees[$user->getPHID()] = $status_attending;
        }
      }

      $xactions[] = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(
          PhabricatorCalendarEventTransaction::TYPE_NAME)
        ->setNewValue($name);

      $xactions[] = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(
          PhabricatorCalendarEventTransaction::TYPE_ALL_DAY)
        ->setNewValue($is_all_day);

      $xactions[] = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(
          PhabricatorCalendarEventTransaction::TYPE_START_DATE)
        ->setNewValue($start_value);

      $xactions[] = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(
          PhabricatorCalendarEventTransaction::TYPE_END_DATE)
        ->setNewValue($end_value);

      $xactions[] = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(
          PhabricatorCalendarEventTransaction::TYPE_STATUS)
        ->setNewValue($type);

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
        ->setActor($user)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $xactions = $editor->applyTransactions($event, $xactions);
        $response = id(new AphrontRedirectResponse());
        return $response->setURI('/E'.$event->getID());
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $error_name = $ex->getShortMessage(
            PhabricatorCalendarEventTransaction::TYPE_NAME);
        $error_start_date = $ex->getShortMessage(
            PhabricatorCalendarEventTransaction::TYPE_START_DATE);
        $error_end_date = $ex->getShortMessage(
            PhabricatorCalendarEventTransaction::TYPE_END_DATE);

        $event->setViewPolicy($view_policy);
        $event->setEditPolicy($edit_policy);
      }
    }

    $all_day_id = celerity_generate_unique_node_id();
    $start_date_id = celerity_generate_unique_node_id();
    $end_date_id = celerity_generate_unique_node_id();

    Javelin::initBehavior('event-all-day', array(
      'allDayID' => $all_day_id,
      'startDateID' => $start_date_id,
      'endDateID' => $end_date_id,
    ));

    $name = id(new AphrontFormTextControl())
      ->setLabel(pht('Name'))
      ->setName('name')
      ->setValue($name)
      ->setError($error_name);

    $status_select = id(new AphrontFormSelectControl())
      ->setLabel(pht('Status'))
      ->setName('status')
      ->setValue($type)
      ->setOptions($event->getStatusOptions());

    $all_day_checkbox = id(new AphrontFormCheckboxControl())
      ->addCheckbox(
        'isAllDay',
        1,
        pht('All Day Event'),
        $is_all_day,
        $all_day_id);

    $start_control = id(new AphrontFormDateControl())
      ->setUser($user)
      ->setName('start')
      ->setLabel(pht('Start'))
      ->setError($error_start_date)
      ->setValue($start_value)
      ->setID($start_date_id)
      ->setIsTimeDisabled($is_all_day);

    $end_control = id(new AphrontFormDateControl())
      ->setUser($user)
      ->setName('end')
      ->setLabel(pht('End'))
      ->setError($error_end_date)
      ->setValue($end_value)
      ->setID($end_date_id)
      ->setIsTimeDisabled($is_all_day);

    $description = id(new AphrontFormTextAreaControl())
      ->setLabel(pht('Description'))
      ->setName('description')
      ->setValue($description);

    $view_policies = id(new AphrontFormPolicyControl())
      ->setUser($user)
      ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
      ->setPolicyObject($event)
      ->setPolicies($current_policies)
      ->setName('viewPolicy');
    $edit_policies = id(new AphrontFormPolicyControl())
      ->setUser($user)
      ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
      ->setPolicyObject($event)
      ->setPolicies($current_policies)
      ->setName('editPolicy');

    $subscribers = id(new AphrontFormTokenizerControl())
      ->setLabel(pht('Subscribers'))
      ->setName('subscribers')
      ->setValue($subscribers)
      ->setUser($user)
      ->setDatasource(new PhabricatorMetaMTAMailableDatasource());

    $invitees = id(new AphrontFormTokenizerControl())
      ->setLabel(pht('Invitees'))
      ->setName('invitees')
      ->setValue($invitees)
      ->setUser($user)
      ->setDatasource(new PhabricatorMetaMTAMailableDatasource());

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild($name)
      ->appendChild($status_select)
      ->appendChild($all_day_checkbox)
      ->appendChild($start_control)
      ->appendChild($end_control)
      ->appendControl($view_policies)
      ->appendControl($edit_policies)
      ->appendControl($subscribers)
      ->appendControl($invitees)
      ->appendChild($description);


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

}
