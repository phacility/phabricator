<?php

final class PhabricatorCalendarEditStatusController
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
      $status       = new PhabricatorUserStatus();
      $end_value    = $end_time->readValueFromRequest($request);
      $start_value  = $start_time->readValueFromRequest($request);
      $submit_label = pht('Create');
      $filter       = 'status/create/';
      $page_title   = pht('Create Status');
      $redirect     = 'created';
    } else {
      $status = id(new PhabricatorUserStatus())
        ->loadOneWhere('id = %d', $this->id);
      $end_time->setValue($status->getDateTo());
      $start_time->setValue($status->getDateFrom());
      $submit_label = pht('Update');
      $filter       = 'status/edit/'.$status->getID().'/';
      $page_title   = pht('Update Status');
      $redirect     = 'updated';

      if ($status->getUserPHID() != $user->getPHID()) {
        return new Aphront403Response();
      }
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
      } catch (PhabricatorUserStatusInvalidEpochException $e) {
        $errors[] = 'Start must be before end.';
      } catch (PhabricatorUserStatusOverlapException $e) {
        $errors[] = 'There is already a status within the specified '.
                    'timeframe. Edit or delete this existing status.';
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
          )
        );
        return id(new AphrontRedirectResponse())
          ->setURI($uri);
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Status can not be set!')
        ->setErrors($errors);
    }

    $status_select = id(new AphrontFormSelectControl())
      ->setLabel(pht('Status'))
      ->setName('status')
      ->setOptions($status->getStatusOptions());

    $description = id(new AphrontFormTextAreaControl())
      ->setLabel(pht('Description'))
      ->setName('description')
      ->setValue($status->getDescription());

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setFlexible(true)
      ->appendChild($status_select)
      ->appendChild($start_time)
      ->appendChild($end_time)
      ->appendChild($description);

    $submit = id(new AphrontFormSubmitControl())
      ->setValue($submit_label);
    if ($this->isCreate()) {
      $submit->addCancelButton($this->getApplicationURI());
    } else {
      $submit->addCancelButton(
        $this->getApplicationURI('status/delete/'.$status->getID().'/'),
        'Delete Status'
      );
    }
    $form->appendChild($submit);

    $nav = $this->buildSideNavView($status);
    $nav->selectFilter($filter);

    $nav->appendChild(
      array(
        id(new PhabricatorHeaderView())->setHeader($page_title),
        $error_view,
        $form,
      )
    );

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $page_title,
        'device' => true
      )
    );
  }

}
