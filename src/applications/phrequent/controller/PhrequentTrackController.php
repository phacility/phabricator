<?php

final class PhrequentTrackController
  extends PhrequentController {

  private $verb;
  private $phid;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
    $this->verb = $data['verb'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $phid = $this->phid;
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();
    $done_uri = $handle->getURI();

    $current_timer = null;
    switch ($this->verb) {
      case 'start':
        $button_text = pht('Start Tracking');
        $title_text = pht('Start Tracking Time');
        $inner_text = pht('What time did you start working?');
        $action_text = pht('Start Timer');
        $label_text = pht('Start Time');
        break;
      case 'stop':
        $button_text = pht('Stop Tracking');
        $title_text = pht('Stop Tracking Time');
        $inner_text = pht('What time did you stop working?');
        $action_text = pht('Stop Timer');
        $label_text = pht('Stop Time');


        $current_timer = id(new PhrequentUserTimeQuery())
          ->setViewer($viewer)
          ->withUserPHIDs(array($viewer->getPHID()))
          ->withObjectPHIDs(array($phid))
          ->withEnded(PhrequentUserTimeQuery::ENDED_NO)
          ->executeOne();
        if (!$current_timer) {
          return $this->newDialog()
            ->setTitle(pht('Not Tracking Time'))
            ->appendParagraph(
              pht(
                'You are not currently tracking time on this object.'))
            ->addCancelButton($done_uri);
        }
        break;
      default:
        return new Aphront404Response();
    }

    $errors = array();
    $v_note = null;
    $e_date = null;

    $epoch_control = id(new AphrontFormDateControl())
      ->setUser($viewer)
      ->setName('epoch')
      ->setLabel($action_text)
      ->setValue(time());

    if ($request->isDialogFormPost()) {
      $v_note = $request->getStr('note');
      $timestamp = $epoch_control->readValueFromRequest($request);

      if (!$epoch_control->isValid()) {
        $errors[] = pht('Please choose an valid date.');
        $e_date = pht('Invalid');
      } else {
        $max_time = PhabricatorTime::getNow();
        if ($timestamp > $max_time) {
          if ($this->isStoppingTracking()) {
            $errors[] = pht(
              'You can not stop tracking time at a future time. Enter the '.
              'current time, or a time in the past.');
          } else {
            $errors[] = pht(
              'You can not start tracking time at a future time. Enter the '.
              'current time, or a time in the past.');
          }
          $e_date = pht('Invalid');
        }

        if ($this->isStoppingTracking()) {
          $min_time = $current_timer->getDateStarted();
          if ($min_time > $timestamp) {
            $errors[] = pht(
              'Stop time must be after start time.');
            $e_date = pht('Invalid');
          }
        }
      }

      if (!$errors) {
        $editor = new PhrequentTrackingEditor();
        if ($this->isStartingTracking()) {
          $editor->startTracking($viewer, $this->phid, $timestamp);
        } else if ($this->isStoppingTracking()) {
          $editor->stopTracking($viewer, $this->phid, $timestamp, $v_note);
        }

        return id(new AphrontRedirectResponse())->setURI($done_uri);
      }

    }

    $epoch_control->setError($e_date);

    $dialog = $this->newDialog()
      ->setTitle($title_text)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setErrors($errors)
      ->appendParagraph($inner_text);

    $form = new PHUIFormLayoutView();

    if ($this->isStoppingTracking()) {
      $start_time = $current_timer->getDateStarted();
      $start_string = pht(
        '%s (%s ago)',
        phabricator_datetime($start_time, $viewer),
        phutil_format_relative_time(PhabricatorTime::getNow() - $start_time));

      $form->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Started At'))
          ->setValue($start_string));
    }

    $form->appendChild($epoch_control);

    if ($this->isStoppingTracking()) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Note'))
          ->setName('note')
          ->setValue($v_note));
    }

    $dialog->appendChild($form);

    $dialog->addCancelButton($done_uri);

    $dialog->addSubmitButton($action_text);

    return $dialog;
  }

  private function isStartingTracking() {
    return $this->verb === 'start';
  }

  private function isStoppingTracking() {
    return $this->verb === 'stop';
  }
}
