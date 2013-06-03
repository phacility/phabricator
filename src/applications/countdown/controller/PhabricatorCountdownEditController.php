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
    $action_label = pht('Create Countdown');

    if ($this->id) {
      $countdown = id(new CountdownQuery())
        ->setViewer($user)
        ->withIDs(array($this->id))
        ->executeOne();

      // If no countdown is found
      if (!$countdown) {
        return new Aphront404Response();
      }

      if (($countdown->getAuthorPHID() != $user->getPHID())
          && $user->getIsAdmin() == false) {
        return new Aphront403Response();
      }

      $action_label = pht('Update Countdown');
    } else {
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
          ->setLabel(pht('End date'))
          ->setValue($display_epoch)
          ->setName('epoch')
          ->setCaption(pht('Examples: '.
            '2011-12-25 or 3 hours or '.
            'June 8 2011, 5 PM.')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/countdown/')
          ->setValue($action_label));

    $panel = id(new AphrontPanelView())
      ->setWidth(AphrontPanelView::WIDTH_FORM)
      ->setHeader($action_label)
      ->setNoBackground()
      ->appendChild($form);

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($action_label)
          ->setHref($this->getApplicationURI('edit/')));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $error_view,
        $panel,
      ),
      array(
        'title' => pht('Edit Countdown'),
        'device' => true,
      ));
  }
}
