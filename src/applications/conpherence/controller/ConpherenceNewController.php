<?php

final class ConpherenceNewController extends ConpherenceController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $title = pht('New Message');
    $participants = array();
    $participant_prefill = null;
    $message = '';
    $e_participants = null;
    $e_message = null;
    $errors = array();

    // this comes from ajax requests from all over. should be a single phid.

    if ($request->isFormPost()) {
      $participants = $request->getArr('participants');
      $message = $request->getStr('message');
      list($error_codes, $conpherence) = ConpherenceEditor::createThread(
        $user,
        $participants,
        $conpherence_title = null,
        $message,
        PhabricatorContentSource::newFromRequest($request));

      if ($error_codes) {
        foreach ($error_codes as $error_code) {
          switch ($error_code) {
            case ConpherenceEditor::ERROR_EMPTY_MESSAGE:
              $e_message = pht('Required');
              $errors[] = pht(
                'You can not send an empty message.');
              break;
            case ConpherenceEditor::ERROR_EMPTY_PARTICIPANTS:
              $e_participants = pht('Required');
              $errors[] = pht(
                'You must choose at least one recipient for your '.
                'message.');
              break;
          }
        }
      } else {
        return id(new AphrontRedirectResponse())
          ->setURI('/'.$conpherence->getMonogram());
      }
    } else {
      $participant_prefill = $request->getStr('participant');
      if ($participant_prefill) {
        $participants[] = $participant_prefill;
      }
    }

    $submit_uri = $this->getApplicationURI('new/');
    $cancel_uri = $this->getApplicationURI();

    $dialog = id(new AphrontDialogView())
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setErrors($errors)
      ->setUser($user)
      ->setTitle($title)
      ->addCancelButton($cancel_uri)
      ->addSubmitButton(pht('Send Message'));

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setFullWidth(true)
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setName('participants')
          ->setValue($participants)
          ->setUser($user)
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setLabel(pht('To'))
          ->setError($e_participants))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($user)
          ->setName('message')
          ->setValue($message)
          ->setLabel(pht('Message'))
          ->setError($e_message));

    $dialog->appendForm($form);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
