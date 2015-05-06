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
      $date_value = AphrontFormDateControlValue::newFromEpoch(
        $user,
        $countdown->getEpoch());
    } else {
      $page_title = pht('Create Countdown');
      $countdown = PhabricatorCountdown::initializeNewCountdown($user);
      $date_value = AphrontFormDateControlValue::newFromEpoch($user, time());
    }

    $errors = array();
    $e_text = true;
    $e_epoch = null;

    $v_text = $countdown->getTitle();

    if ($request->isFormPost()) {
      $v_text = $request->getStr('title');
      $date_value = AphrontFormDateControlValue::newFromRequest(
        $request,
        'epoch');
      $view_policy = $request->getStr('viewPolicy');

      $e_text = null;
      if (!strlen($v_text)) {
        $e_text = pht('Required');
        $errors[] = pht('You must give the countdown a name.');
      }
      if (!$date_value->isValid()) {
        $e_epoch = pht('Invalid');
        $errors[] = pht('You must give the countdown a valid end date.');
      }

      if (!count($errors)) {
        $countdown->setTitle($v_text);
        $countdown->setEpoch($date_value->getEpoch());
        $countdown->setViewPolicy($view_policy);
        $countdown->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/countdown/'.$countdown->getID().'/');
      }
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
          ->setValue($v_text)
          ->setName('title')
          ->setError($e_text))
      ->appendChild(
        id(new AphrontFormDateControl())
          ->setUser($user)
          ->setName('epoch')
          ->setLabel(pht('End Date'))
          ->setError($e_epoch)
          ->setValue($date_value))
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
