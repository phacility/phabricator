<?php

final class PhrictionDeleteController extends PhrictionController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $document = id(new PhrictionDocumentQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->needContent(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
      ->executeOne();
    if (!$document) {
      return new Aphront404Response();
    }

    $document_uri = PhrictionDocument::getSlugURI($document->getSlug());

    $e_text = null;
    if ($request->isFormPost()) {
        $xactions = array();
        $xactions[] = id(new PhrictionTransaction())
          ->setTransactionType(PhrictionTransaction::TYPE_DELETE)
          ->setNewValue(true);

        $editor = id(new PhrictionTransactionEditor())
          ->setActor($user)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true);
        try {
          $editor->applyTransactions($document, $xactions);
          return id(new AphrontRedirectResponse())->setURI($document_uri);
        } catch (PhabricatorApplicationTransactionValidationException $ex) {
          $e_text = phutil_implode_html("\n", $ex->getErrorMessages());
        }
    }

    if ($e_text) {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Can Not Delete Document!'))
        ->appendChild($e_text)
        ->addCancelButton($document_uri);
    } else {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Delete Document?'))
        ->appendChild(
          pht('Really delete this document? You can recover it later by '.
          'reverting to a previous version.'))
        ->addSubmitButton(pht('Delete'))
        ->addCancelButton($document_uri);
    }

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
