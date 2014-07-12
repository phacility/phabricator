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
    $user = $request->getUser();
    $editor = new PhrequentTrackingEditor();

    $phid = $this->phid;
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($phid))
      ->executeOne();

    if (!$this->isStartingTracking() &&
        !$this->isStoppingTracking()) {
      throw new Exception('Unrecognized verb: '.$this->verb);
    }

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
        break;
    }

    $epoch_control = id(new AphrontFormDateControl())
      ->setUser($user)
      ->setName('epoch')
      ->setLabel($action_text)
      ->setValue(time());

    $err = array();

    if ($request->isDialogFormPost()) {
      $timestamp = $epoch_control->readValueFromRequest($request);
      $note = $request->getStr('note');

      if (!$epoch_control->isValid() || $timestamp > time()) {
        $err[] = pht('Invalid date, please enter a valid non-future date');
      }

      if (!$err) {
        if ($this->isStartingTracking()) {
          $editor->startTracking($user, $this->phid, $timestamp);
        } else if ($this->isStoppingTracking()) {
          $editor->stopTracking($user, $this->phid, $timestamp, $note);
        }
        return id(new AphrontRedirectResponse());
      }

    }

    $dialog = $this->newDialog()
      ->setTitle($title_text)
      ->setWidth(AphrontDialogView::WIDTH_FORM);

    if ($err) {
      $dialog->setErrors($err);
    }

    $form = new PHUIFormLayoutView();
    $form
      ->appendChild(hsprintf(
        '<p>%s</p><br />', $inner_text));

    $form->appendChild($epoch_control);

    if ($this->isStoppingTracking()) {
      $form
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setLabel(pht('Note'))
            ->setName('note'));
    }

    $dialog->appendChild($form);

    $dialog->addCancelButton($handle->getURI());
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
