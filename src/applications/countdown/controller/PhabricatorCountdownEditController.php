<?php

final class PhabricatorCountdownEditController
  extends PhabricatorCountdownController {

  private $id;
  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $epoch_control = id(new AphrontFormDateControl())
      ->setUser($user)
      ->setName('epoch')
      ->setLabel(pht('End Date'))
      ->setInitialTime(AphrontFormDateControl::TIME_END_OF_DAY);

    if ($this->id) {
      $page_title = pht('Edit Countdown');
      $countdown = id(new PhabricatorCountdownQuery())
        ->setViewer($user)
        ->withIDs(array($this->id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$countdown) {
        return new Aphront404Response();
      }
    } else {
      $page_title = pht('Create Countdown');
      $countdown = PhabricatorCountdown::initializeNewCountdown($user);
    }
    $epoch_control->setValue($countdown->getEpoch());

    $e_text = true;
    $errors = array();
    if ($request->isFormPost()) {
      $title = $request->getStr('title');
      $epoch = $epoch_control->readValueFromRequest($request);
      $view_policy = $request->getStr('viewPolicy');

      $e_text = null;
      if (!strlen($title)) {
        $e_text = pht('Required');
        $errors[] = pht('You must give the countdown a name.');
      }
      if (!$epoch) {
        $errors[] = pht('You must give the countdown a valid end date.');
      }

      if (!count($errors)) {
        $countdown->setTitle($title);
        $countdown->setEpoch($epoch);
        $countdown->setViewPolicy($view_policy);
        $countdown->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/countdown/'.$countdown->getID().'/');
      }
    }

    if ($countdown->getEpoch()) {
      $display_epoch = phabricator_datetime($countdown->getEpoch(), $user);
    } else {
      $display_epoch = $request->getStr('epoch');
    }

    $crumbs = $this->buildApplicationCrumbs();

    $cancel_uri = '/countdown/';
    if ($countdown->getID()) {
      $cancel_uri = '/countdown/'.$countdown->getID().'/';
      $crumbs->addTextCrumb('C'.$countdown->getID(), $cancel_uri);
      $crumbs->addTextCrumb(pht('Edit'));
      $submit_label = pht('Save Changes');
    } else {
      $crumbs->addTextCrumb(pht('Create Countdown'));
      $submit_label = pht('Create Countdown');
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($countdown)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction($request->getRequestURI()->getPath())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Title'))
          ->setValue($countdown->getTitle())
          ->setName('title')
          ->setError($e_text))
      ->appendChild($epoch_control)
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($user)
          ->setName('viewPolicy')
          ->setPolicyObject($countdown)
          ->setPolicies($policies)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($submit_label));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($page_title)
      ->setFormErrors($errors)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => $page_title,
      ));
  }

}
