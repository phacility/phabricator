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

    $document_uri = PhrictionDocument::getSlugURI($document->getSlug());

    if ($request->isFormPost()) {
        $editor = id(PhrictionDocumentEditor::newForSlug($document->getSlug()))
          ->setActor($user)
          ->delete();
        return id(new AphrontRedirectResponse())->setURI($document_uri);
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle('Delete document?')
      ->appendChild(
        'Really delete this document? You can recover it later by reverting '.
        'to a previous version.')
      ->addSubmitButton('Delete')
      ->addCancelButton($document_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
