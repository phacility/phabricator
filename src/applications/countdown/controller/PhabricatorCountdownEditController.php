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
    $action_label = 'Create Timer';

    if ($this->id) {
      $timer = id(new PhabricatorTimer())->load($this->id);
      // If no timer is found
      if (!$timer) {
        return new Aphront404Response();
      }

      if (($timer->getAuthorPHID() != $user->getPHID())
          && $user->getIsAdmin() == false) {
        return new Aphront403Response();
      }

      $action_label = 'Update Timer';
    } else {
      $timer = new PhabricatorTimer();
      $timer->setDatePoint(time());
    }

    $error_view = null;
    $e_text = null;

    if ($request->isFormPost()) {
      $errors = array();
      $title = $request->getStr('title');
      $datepoint = $request->getStr('datepoint');

      $e_text = null;
      if (!strlen($title)) {
        $e_text = 'Required';
        $errors[] = 'You must give it a name.';
      }

      // If the user types something like "5 PM", convert it to a timestamp
      // using their local time, not the server time.
      $timezone = new DateTimeZone($user->getTimezoneIdentifier());

      try {
        $date = new DateTime($datepoint, $timezone);
        $timestamp = $date->format('U');
      } catch (Exception $e) {
        $errors[] = 'You entered an incorrect date. You can enter date like'.
          ' \'2011-06-26 13:33:37\' to create an event at'.
          ' 13:33:37 on the 26th of June 2011.';
        $timestamp = null;
      }

      $timer->setTitle($title);
      $timer->setDatePoint($timestamp);

      if (!count($errors)) {
        $timer->setAuthorPHID($user->getPHID());
        $timer->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/countdown/'.$timer->getID().'/');
      } else {
        $error_view = id(new AphrontErrorView())
          ->setErrors($errors)
          ->setTitle('It\'s not The Final Countdown (du nu nuuu nun)' .
            ' until you fix these problem');
      }
    }

    if ($timer->getDatePoint()) {
      $display_datepoint = phabricator_datetime(
        $timer->getDatePoint(),
        $user);
    } else {
      $display_datepoint = $request->getStr('datepoint');
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction($request->getRequestURI()->getPath())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Title')
          ->setValue($timer->getTitle())
          ->setName('title'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('End date')
          ->setValue($display_datepoint)
          ->setName('datepoint')
          ->setCaption(
            'Examples: '.
            '<tt>2011-12-25</tt> or '.
            '<tt>3 hours</tt> or '.
            '<tt>June 8 2011, 5 PM</tt>.'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/countdown/')
          ->setValue($action_label));

    $panel = id(new AphrontPanelView())
      ->setWidth(AphrontPanelView::WIDTH_FORM)
      ->setHeader($action_label)
      ->appendChild($form);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => 'Edit Countdown',
      ));
  }
}
