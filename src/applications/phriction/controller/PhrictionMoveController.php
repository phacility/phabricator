<?php

final class PhrictionMoveController extends PhrictionController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($this->id) {
      $document = id(new PhrictionDocumentQuery())
        ->setViewer($user)
        ->withIDs(array($this->id))
        ->needContent(true)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
    } else {
      $slug = PhabricatorSlug::normalize(
        $request->getStr('slug'));
      if (!$slug) {
        return new Aphront404Response();
      }

      $document = id(new PhrictionDocumentQuery())
        ->setViewer($user)
        ->withSlugs(array($slug))
        ->needContent(true)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
    }

    if (!$document) {
      return new Aphront404Response();
    }

    if (!isset($slug)) {
      $slug = $document->getSlug();
    }

    $target_slug = PhabricatorSlug::normalize(
      $request->getStr('new-slug', $slug));

    $submit_uri = $request->getRequestURI()->getPath();
    $cancel_uri = PhrictionDocument::getSlugURI($slug);

    $e_url = true;
    $validation_exception = null;
    $content = $document->getContent();

    if ($request->isFormPost()) {

      $editor = id(new PhrictionTransactionEditor())
        ->setActor($user)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setDescription($request->getStr('description'));

      $xactions = array();
      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(PhrictionTransaction::TYPE_MOVE_TO)
        ->setNewValue($document);
      $target_document = PhrictionDocument::initializeNewDocument(
        $user,
        $target_slug);
      try {
        $editor->applyTransactions($target_document, $xactions);
        $redir_uri = PhrictionDocument::getSlugURI(
          $target_document->getSlug());
        return id(new AphrontRedirectResponse())->setURI($redir_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $e_url = $ex->getShortMessage(PhrictionTransaction::TYPE_MOVE_TO);
      }
    }

    $form = id(new PHUIFormLayoutView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormStaticControl())
        ->setLabel(pht('Title'))
        ->setValue($content->getTitle()))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('New URI'))
        ->setValue($target_slug)
        ->setError($e_url)
        ->setName('new-slug')
        ->setCaption(pht('The new location of the document.')))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Edit Notes'))
        ->setValue(pht('Moving document to a new location.'))
        ->setError(null)
        ->setName('description'));

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setValidationException($validation_exception)
      ->setTitle(pht('Move Document'))
      ->appendChild($form)
      ->setSubmitURI($submit_uri)
      ->addSubmitButton(pht('Move Document'))
      ->addCancelButton($cancel_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
