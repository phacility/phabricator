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

    $error_view = null;
    $disallowed_states = array(
      PhrictionDocumentStatus::STATUS_DELETED, // Stupid
      PhrictionDocumentStatus::STATUS_MOVED, // Makes no sense
    );
    if (in_array($document->getStatus(), $disallowed_states)) {
      $is_serious =
        PhabricatorEnv::getEnvConfig('phabricator.serious-business');

      if ($is_serious) {
        $e_text = pht('An already moved or deleted document can not be '.
          'deleted');
      } else {
        $e_text = pht('I\'m not sure if you got the notice, but you can\'t '.
          'delete an already deleted or moved document.');
      }

      $error_view = new AphrontErrorView();
      $error_view->setSeverity(AphrontErrorView::SEVERITY_ERROR);
      $error_view->setTitle(pht('Can not delete page'));
      $error_view->appendChild($e_text);

      $error_view = $error_view->render();
    }

    $document_uri = PhrictionDocument::getSlugURI($document->getSlug());

    if (!$error_view && $request->isFormPost()) {
        $editor = id(PhrictionDocumentEditor::newForSlug($document->getSlug()))
          ->setActor($user)
          ->delete();
        return id(new AphrontRedirectResponse())->setURI($document_uri);
    }

    if ($error_view) {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Error!'))
        ->appendChild($error_view)
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
