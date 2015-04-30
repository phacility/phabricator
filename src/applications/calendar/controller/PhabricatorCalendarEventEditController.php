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
    $validation_exception = null;

    $start_time = id(new AphrontFormDateControl())
      ->setUser($user)
      ->setName('start')
      ->setLabel(pht('Start'))
      ->setInitialTime(AphrontFormDateControl::TIME_START_OF_DAY);

    $end_time = id(new AphrontFormDateControl())
      ->setUser($user)
      ->setName('end')
      ->setLabel(pht('End'))
      ->setInitialTime(AphrontFormDateControl::TIME_END_OF_DAY);

    if ($this->isCreate()) {
      $event = PhabricatorCalendarEvent::initializeNewCalendarEvent($user);
      $end_value = $end_time->readValueFromRequest($request);
      $start_value = $start_time->readValueFromRequest($request);
      $submit_label = pht('Create');
      $page_title = pht('Create Event');
      $redirect = 'created';
      $subscribers = array();
      $invitees = array($user_phid);
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

      $end_time->setValue($event->getDateTo());
      $start_time->setValue($event->getDateFrom());
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
    }

    $errors = array();
    if ($request->isFormPost()) {
      $xactions = array();
      $name = $request->getStr('name');
      $type = $request->getInt('status');
      $start_value = $start_time->readValueFromRequest($request);
      $end_value = $end_time->readValueFromRequest($request);
      $description = $request->getStr('description');
      $subscribers = $request->getArr('subscribers');

      $invitees = $request->getArr('invitees');
      $new_invitees = $this->getNewInviteeList($invitees, $event);
      $status_attending = PhabricatorCalendarEventInvitee::STATUS_ATTENDING;
      if ($this->isCreate()) {
        $status = idx($new_invitees, $user->getPHID());
        if ($status) {
          $new_invitees[$user->getPHID()] = $status_attending;
        }
      }

      if ($start_time->getError()) {
        $errors[] = pht('Invalid start time; reset to default.');
      }
      if ($end_time->getError()) {
        $errors[] = pht('Invalid end time; reset to default.');
      }
      if (!$errors) {
        $xactions[] = id(new PhabricatorCalendarEventTransaction())
          ->setTransactionType(
            PhabricatorCalendarEventTransaction::TYPE_NAME)
          ->setNewValue($name);

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
          $error_name = $ex
            ->getShortMessage(PhabricatorCalendarEventTransaction::TYPE_NAME);
        }
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new PHUIInfoView())
        ->setTitle(pht('Status can not be set!'))
        ->setErrors($errors);
    }

    $name = id(new AphrontFormTextControl())
      ->setLabel(pht('Name'))
      ->setName('name')
      ->setValue($event->getName())
      ->setError($error_name);

    $status_select = id(new AphrontFormSelectControl())
      ->setLabel(pht('Status'))
      ->setName('status')
      ->setValue($event->getStatus())
      ->setOptions($event->getStatusOptions());

    $description = id(new AphrontFormTextAreaControl())
      ->setLabel(pht('Description'))
      ->setName('description')
      ->setValue($event->getDescription());

    $current_policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($event)
      ->execute();
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
      ->appendChild($start_time)
      ->appendChild($end_time)
      ->appendControl($view_policies)
      ->appendControl($edit_policies)
      ->appendControl($subscribers)
      ->appendControl($invitees)
      ->appendChild($description);

    $submit = id(new AphrontFormSubmitControl())
      ->setValue($submit_label);
    if ($this->isCreate()) {
      $submit->addCancelButton($this->getApplicationURI());
    } else {
      $submit->addCancelButton('/E'.$event->getID());
    }

    $form->appendChild($submit);

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($page_title)
      ->setFormErrors($errors)
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
