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
        ->setTitle('Can not move page!')
        ->appendChild(pht('An already moved or deleted document '.
          'can not be moved again.'))
        ->addCancelButton($cancel_uri);

      return id(new AphrontDialogResponse())->setDialog($error_dialog);
    }

    $content = $document->getContent();

    if ($request->isFormPost() && !count($errors)) {

      // NOTE: We use the ominpotent user because we can't let users overwrite
      // documents even if they can't see them.
      $target_document = id(new PhrictionDocumentQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withSlugs(array($target_slug))
        ->needContent(true)
        ->executeOne();

      // Considering to overwrite existing docs? Nuke this!
      $exists = PhrictionDocumentStatus::STATUS_EXISTS;
      if ($target_document && $target_document->getStatus() == $exists) {
        $errors[] = pht('Can not overwrite existing target document.');
        $e_url = pht('Already exists.');
      }

      if (!count($errors)) {

        $editor = id(new PhrictionTransactionEditor())
          ->setActor($user)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true)
          ->setDescription($request->getStr('description'));

        $xactions = array();
        $xactions[] = id(new PhrictionTransaction())
          ->setTransactionType(PhrictionTransaction::TYPE_MOVE_TO)
          ->setNewValue($document);
        if (!$target_document) {
          $target_document = PhrictionDocument::initializeNewDocument(
            $user,
            $target_slug);
        }
        $editor->applyTransactions($target_document, $xactions);

        $redir_uri = PhrictionDocument::getSlugURI(
          $target_document->getSlug());
        return id(new AphrontRedirectResponse())->setURI($redir_uri);
      }
    }

    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setErrors($errors);
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
        ->setValue($content->getDescription())
        ->setError(null)
        ->setName('description'));

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
