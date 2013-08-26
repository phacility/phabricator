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

      // If no countdown is found
      if (!$countdown) {
        return new Aphront404Response();
      }
    } else {
      $page_title = pht('Create Countdown');
      $countdown = new PhabricatorCountdown();
      $countdown->setEpoch(time());
    }

    $error_view = null;
    $e_text = null;

    if ($request->isFormPost()) {
      $errors = array();
      $title = $request->getStr('title');
      $epoch = $request->getStr('epoch');

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
        $countdown->setAuthorPHID($user->getPHID());
        $countdown->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/countdown/'.$countdown->getID().'/');
      } else {
        $error_view = id(new AphrontErrorView())
          ->setErrors($errors)
          ->setTitle(pht('It\'s not The Final Countdown (du nu nuuu nun)' .
            ' until you fix these problem'));
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
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName('C'.$countdown->getID())
          ->setHref($cancel_uri));
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Edit')));
      $submit_label = pht('Save Changes');
    } else {
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Create Countdown')));
      $submit_label = pht('Create Countdown');
    }


    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction($request->getRequestURI()->getPath())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Title'))
          ->setValue($countdown->getTitle())
          ->setName('title'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('End Date'))
          ->setValue($display_epoch)
          ->setName('epoch')
          ->setCaption(pht('Examples: '.
            '2011-12-25 or 3 hours or '.
            'June 8 2011, 5 PM.')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($submit_label));

    $form_box = id(new PHUIFormBoxView())
      ->setHeaderText($page_title)
      ->setFormError($error_view)
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
