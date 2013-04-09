<?php

/**
 * @group phriction
 */
final class PhrictionDeleteController extends PhrictionController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $document = id(new PhrictionDocument())->load($this->id);
    if (!$document) {
      return new Aphront404Response();
    }

    $e_text = null;
    $disallowed_states = array(
      PhrictionDocumentStatus::STATUS_DELETED => true, // Silly
      PhrictionDocumentStatus::STATUS_MOVED => true, // Makes no sense
      PhrictionDocumentStatus::STATUS_STUB => true, // How could they?
    );
    if (isset($disallowed_states[$document->getStatus()])) {
      $e_text = pht('An already moved or deleted document can not be deleted');
    }

    $document_uri = PhrictionDocument::getSlugURI($document->getSlug());

    if (!$e_text && $request->isFormPost()) {
        $editor = id(PhrictionDocumentEditor::newForSlug($document->getSlug()))
          ->setActor($user)
          ->delete();
        return id(new AphrontRedirectResponse())->setURI($document_uri);
    }

    if ($e_text) {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Can not delete document!'))
        ->appendChild($e_text)
        ->addCancelButton($document_uri);
    } else {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Delete document?'))
        ->appendChild(
          pht('Really delete this document? You can recover it later by '.
          'reverting to a previous version.'))
        ->addSubmitButton(pht('Delete'))
        ->addCancelButton($document_uri);
    }

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
