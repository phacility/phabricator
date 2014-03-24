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
    $request  = $this->getRequest();
    $user     = $request->getUser();

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
      $status       = new PhabricatorCalendarEvent();
      $end_value    = $end_time->readValueFromRequest($request);
      $start_value  = $start_time->readValueFromRequest($request);
      $submit_label = pht('Create');
      $filter       = 'status/create/';
      $page_title   = pht('Create Event');
      $redirect     = 'created';
    } else {
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

      $end_time->setValue($status->getDateTo());
      $start_time->setValue($status->getDateFrom());
      $submit_label = pht('Update');
      $filter       = 'event/edit/'.$status->getID().'/';
      $page_title   = pht('Update Event');
      $redirect     = 'updated';
    }

    $errors = array();
    if ($request->isFormPost()) {
      $type        = $request->getInt('status');
      $start_value = $start_time->readValueFromRequest($request);
      $end_value   = $end_time->readValueFromRequest($request);
      $description = $request->getStr('description');

      try {
        $status
          ->setUserPHID($user->getPHID())
          ->setStatus($type)
          ->setDateFrom($start_value)
          ->setDateTo($end_value)
          ->setDescription($description)
          ->save();
      } catch (PhabricatorCalendarEventInvalidEpochException $e) {
        $errors[] = pht('Start must be before end.');
      }

      if (!$errors) {
        $uri = new PhutilURI($this->getApplicationURI());
        $uri->setQueryParams(
          array(
            'month'   => phabricator_format_local_time($status->getDateFrom(),
                                                       $user,
                                                       'm'),
            'year'    => phabricator_format_local_time($status->getDateFrom(),
                                                       $user,
                                                       'Y'),
            $redirect => true,
          ));
        if ($request->isAjax()) {
          $response = id(new AphrontAjaxResponse())
            ->setContent(array('redirect_uri' => $uri));
        } else {
          $response = id(new AphrontRedirectResponse())
            ->setURI($uri);
        }
        return $response;
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('Status can not be set!'))
        ->setErrors($errors);
    }

    $status_select = id(new AphrontFormSelectControl())
      ->setLabel(pht('Status'))
      ->setName('status')
      ->setValue($status->getStatus())
      ->setOptions($status->getStatusOptions());

    $description = id(new AphrontFormTextAreaControl())
      ->setLabel(pht('Description'))
      ->setName('description')
      ->setValue($status->getDescription());

    if ($request->isAjax()) {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle($page_title)
        ->setWidth(AphrontDialogView::WIDTH_FORM);
      if ($this->isCreate()) {
        $dialog->setSubmitURI($this->getApplicationURI('event/create/'));
      } else {
        $dialog->setSubmitURI(
          $this->getApplicationURI('event/edit/'.$status->getID().'/'));
      }
      $form = new PHUIFormLayoutView();
      if ($error_view) {
        $form->appendChild($error_view);
      }
    } else {
      $form = id(new AphrontFormView())
        ->setUser($user);
    }

    $form
      ->appendChild($status_select)
      ->appendChild($start_time)
      ->appendChild($end_time)
      ->appendChild($description);

    if ($request->isAjax()) {
      $dialog->addSubmitButton($submit_label);
      $submit = $dialog;
    } else {
      $submit = id(new AphrontFormSubmitControl())
        ->setValue($submit_label);
    }
    if ($this->isCreate()) {
      $submit->addCancelButton($this->getApplicationURI());
    } else {
      $submit->addCancelButton(
        $this->getApplicationURI('event/view/'.$status->getID().'/'));
    }

    if ($request->isAjax()) {
      $dialog->appendChild($form);
      return id(new AphrontDialogResponse())
        ->setDialog($dialog);
    }
    $form->appendChild($submit);



    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($page_title)
      ->setFormErrors($errors)
      ->setForm($form);

    $nav = $this->buildSideNavView($status);
    $nav->selectFilter($filter);

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb($page_title);

    $nav->appendChild(
      array(
        $crumbs,
        $form_box,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $page_title,
        'device' => true
      ));
  }

}
