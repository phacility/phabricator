<?php

/**
 * @group countdown
 */
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
    } else {
      $page_title = pht('Create Countdown');
      $countdown = PhabricatorCountdown::initializeNewCountdown($user);
    }

    $e_text = true;
    $e_epoch = null;

    $errors = array();
    if ($request->isFormPost()) {
      $title = $request->getStr('title');
      $epoch = $request->getStr('epoch');
      $view_policy = $request->getStr('viewPolicy');

      $e_text = null;
      if (!strlen($title)) {
        $e_text = pht('Required');
        $errors[] = pht('You must give the countdown a name.');
      }

      if (strlen($epoch)) {
        $timestamp = PhabricatorTime::parseLocalTime($epoch, $user);
        if (!$timestamp) {
          $errors[] = pht(
            'You entered an incorrect date. You can enter date '.
            'like \'2011-06-26 13:33:37\' to create an event at '.
            '13:33:37 on the 26th of June 2011.');
        }
      } else {
        $e_epoch = pht('Required');
        $errors[] = pht('You must specify the end date for a countdown.');
      }

      if (!count($errors)) {
        $countdown->setTitle($title);
        $countdown->setEpoch($timestamp);
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
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('End Date'))
          ->setValue($display_epoch)
          ->setName('epoch')
          ->setError($e_epoch)
          ->setCaption(pht('Examples: '.
            '2011-12-25 or 3 hours or '.
            'June 8 2011, 5 PM.')))
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
        'device' => true,
      ));
  }
}
