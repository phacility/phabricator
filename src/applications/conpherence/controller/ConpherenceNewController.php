<?php

final class ConpherenceNewController extends ConpherenceController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $title = pht('New Message');
    $participants = array();
    $participant_prefill = null;
    $message = '';
    $e_participants = null;
    $e_message = null;

    // this comes from ajax requests from all over. should be a single phid.

    if ($request->isFormPost()) {
      $participants = $request->getArr('participants');
      $message = $request->getStr('message');
      list($error_codes, $conpherence) = ConpherenceEditor::createConpherence(
        $user,
        $participants,
        $conpherence_title = null,
        $message,
        PhabricatorContentSource::newFromRequest($request));

      if ($error_codes) {
        foreach ($error_codes as $error_code) {
          switch ($error_code) {
            case ConpherenceEditor::ERROR_EMPTY_MESSAGE:
              $e_message = true;
              break;
            case ConpherenceEditor::ERROR_EMPTY_PARTICIPANTS:
              $e_participants = true;
              break;
          }
        }
      } else {
        $uri = $this->getApplicationURI($conpherence->getID());
        return id(new AphrontRedirectResponse())
          ->setURI($uri);
      }
    } else {
      $participant_prefill = $request->getStr('participant');
      if ($participant_prefill) {
        $participants[] = $participant_prefill;
      }
    }


    $participant_handles = array();
    if ($participants) {
      $participant_handles = id(new PhabricatorHandleQuery())
        ->setViewer($user)
        ->withPHIDs($participants)
        ->execute();
    }

    $submit_uri = $this->getApplicationURI('new/');
    $cancel_uri = $this->getApplicationURI();

    // TODO - we can get a better cancel_uri once we get better at crazy
    // ajax jonx T2086
    if ($participant_prefill) {
      $handle = $participant_handles[$participant_prefill];
      $cancel_uri = $handle->getURI();
    }

    $dialog = id(new AphrontDialogView())
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setUser($user)
      ->setTitle($title)
      ->addCancelButton($cancel_uri)
      ->addSubmitButton(pht('Send Message'));

    $form = id(new PHUIFormLayoutView())
      ->setUser($user)
      ->setFullWidth(true)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
        ->setName('participants')
        ->setValue($participant_handles)
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

    $dialog->appendChild($form);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
