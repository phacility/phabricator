<?php

/**
 * @group phriction
 */
final class PhrictionMoveController
  extends PhrictionController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    if ($this->id) {
      $document = id(new PhrictionDocument())->load($this->id);
    } else {
      $slug = PhabricatorSlug::normalize(
        $request->getStr('slug'));
      if (!$slug) {
        return new Aphront404Response();
      }

      $document = id(new PhrictionDocument())->loadOneWhere(
        'slug = %s',
        $slug);
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

    $errors = array();
    $error_view = null;
    $e_url = null;

    $disallowed_statuses = array(
      PhrictionDocumentStatus::STATUS_DELETED => true, // Silly
      PhrictionDocumentStatus::STATUS_MOVED => true, // Plain silly
      PhrictionDocumentStatus::STATUS_STUB => true, // Utterly silly
    );
    if (isset($disallowed_statuses[$document->getStatus()])) {
      $error_dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle("Can not move page!")
        ->appendChild(pht('An already moved or deleted document '.
          'can not be moved again.'))
        ->addCancelButton($cancel_uri);

      return id(new AphrontDialogResponse())->setDialog($error_dialog);
    }

    $content = id(new PhrictionContent())->load($document->getContentID());

    if ($request->isFormPost() && !count($errors)) {
      if (!count($errors)) { // First check if the target document exists
        $target_document = id(new PhrictionDocument())->loadOneWhere(
          'slug = %s',
          $target_slug);

        // Considering to overwrite existing docs? Nuke this!
        if ($target_document && $target_document->getStatus() ==
          PhrictionDocumentStatus::STATUS_EXISTS) {

          $errors[] = pht('Can not overwrite existing target document.');
          $e_url = pht('Already exists.');
        }
      }

      if (!count($errors)) { // I like to move it, move it!
        $from_editor = id(PhrictionDocumentEditor::newForSlug($slug))
          ->setActor($user)
          ->setTitle($content->getTitle())
          ->setContent($content->getContent())
          ->setDescription($content->getDescription());

        $target_editor = id(PhrictionDocumentEditor::newForSlug(
          $target_slug))
          ->setActor($user)
          ->setTitle($content->getTitle())
          ->setContent($content->getContent())
          ->setDescription($content->getDescription());

        // Move it!
        $target_editor->moveHere($document->getID(), $document->getPHID());

        // Retrieve the target doc directly from the editor
        // No need to load it per Sql again
        $target_document = $target_editor->getDocument();
        $from_editor->moveAway($target_document->getID());

        $redir_uri = PhrictionDocument::getSlugURI($target_document->getSlug());
        return id(new AphrontRedirectResponse())->setURI($redir_uri);
      }
    }

    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('Form Errors'))
        ->setErrors($errors);
    }

    $descr_caption = $is_serious ? pht('A reason for the move.') :
      pht('You better give a good reason for this.');

    $form = id(new AphrontFormLayoutView())
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
          ->setValue($content->getDescription())
          ->setError(null)
          ->setName('description')
          ->setCaption($descr_caption));

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle(pht('Move Document'))
      ->appendChild($form)
      ->setSubmitURI($submit_uri)
      ->addSubmitButton(pht('Move Document'))
      ->addCancelButton($cancel_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
