<?php

/**
 * @group conpherence
 */
final class ConpherenceNewController extends ConpherenceController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $conpherence = id(new ConpherenceThread())
      ->attachParticipants(array())
      ->attachFilePHIDs(array())
      ->setMessageCount(0);
    $title = pht('New Message');
    $participants = array();
    $message = '';
    $files = array();
    $errors = array();
    $e_participants = null;
    $e_message = null;

    // this comes from ajax requests from all over. should be a single phid.
    $participant_prefill = $request->getStr('participant');
    if ($participant_prefill) {
      $participants[] = $participant_prefill;
    }

    if ($request->isFormPost()) {
      $participants = $request->getArr('participants');
      if (empty($participants)) {
        $e_participants = true;
        $errors[] = pht('You must specify participants.');
      } else {
        $participants[] = $user->getPHID();
        $participants = array_unique($participants);
        $conpherence->setRecentParticipantPHIDs(
          array_slice($participants, 0, 10));
      }

      $message = $request->getStr('message');
      if (empty($message)) {
        $e_message = true;
        $errors[] = pht('You must write a message.');
      }

      $file_phids =
        PhabricatorMarkupEngine::extractFilePHIDsFromEmbeddedFiles(
          array($message));
      if ($file_phids) {
        $files = id(new PhabricatorFileQuery())
          ->setViewer($user)
          ->withPHIDs($file_phids)
          ->execute();
      }

      if (!$errors) {
        $conpherence->openTransaction();
        $conpherence->save();
        $xactions = array();
        $xactions[] = id(new ConpherenceTransaction())
          ->setTransactionType(ConpherenceTransactionType::TYPE_PARTICIPANTS)
          ->setNewValue(array('+' => $participants));
        if ($files) {
          $xactions[] = id(new ConpherenceTransaction())
            ->setTransactionType(ConpherenceTransactionType::TYPE_FILES)
            ->setNewValue(array('+' => mpull($files, 'getPHID')));
        }
        $xactions[] = id(new ConpherenceTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
          ->attachComment(
            id(new ConpherenceTransactionComment())
            ->setContent($message)
            ->setConpherencePHID($conpherence->getPHID()));
        $content_source = PhabricatorContentSource::newForSource(
          PhabricatorContentSource::SOURCE_WEB,
          array(
            'ip' => $request->getRemoteAddr()
          ));
        id(new ConpherenceEditor())
          ->setContentSource($content_source)
          ->setContinueOnNoEffect(true)
          ->setActor($user)
          ->applyTransactions($conpherence, $xactions);

        $conpherence->saveTransaction();

        $uri = $this->getApplicationURI($conpherence->getID());
        return id(new AphrontRedirectResponse())
          ->setURI($uri);
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('Conpherence Errors'))
        ->setErrors($errors);
    }

    $participant_handles = array();
    if ($participants) {
      $handles = id(new PhabricatorObjectHandleData($participants))
        ->setViewer($user)
        ->loadHandles();
      $participant_handles = mpull($handles, 'getFullName', 'getPHID');
    }

    $submit_uri = $this->getApplicationURI('new/');
    $cancel_uri = $this->getApplicationURI();

    // TODO - we can get a better cancel_uri once we get better at crazy
    // ajax jonx T2086
    if ($participant_prefill) {
      $handle = $handles[$participant_prefill];
      $cancel_uri = $handle->getURI();
    }

    $dialog = id(new AphrontDialogView())
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setUser($user)
      ->setTitle($title)
      ->addCancelButton($cancel_uri)
      ->addSubmitButton(pht('Send Message'));

    $form = id(new AphrontFormLayoutView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
        ->setName('participants')
        ->setValue($participant_handles)
        ->setUser($user)
        ->setDatasource('/typeahead/common/users/')
        ->setLabel(pht('To'))
        ->setError($e_participants))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
        ->setName('message')
        ->setValue($message)
        ->setLabel(pht('Message'))
        ->setError($e_message));

    $dialog->appendChild($form);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
