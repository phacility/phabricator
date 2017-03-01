<?php

final class PhrictionMoveController extends PhrictionController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $document = id(new PhrictionDocumentQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->needContent(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$document) {
      return new Aphront404Response();
    }

    $slug = $document->getSlug();
    $cancel_uri = PhrictionDocument::getSlugURI($slug);

    $v_slug = $slug;
    $e_slug = null;

    $v_note = '';

    $validation_exception = null;
    if ($request->isFormPost()) {
      $v_note = $request->getStr('description');
      $v_slug = $request->getStr('slug');
      $normal_slug = PhabricatorSlug::normalize($v_slug);

      // If what the user typed isn't what we're actually using, warn them
      // about it.
      if (strlen($v_slug)) {
        $no_slash_slug = rtrim($normal_slug, '/');
        if ($normal_slug !== $v_slug && $no_slash_slug !== $v_slug) {
          return $this->newDialog()
            ->setTitle(pht('Adjust Path'))
            ->appendParagraph(
              pht(
                'The path you entered (%s) is not a valid wiki document '.
                'path. Paths may not contain spaces or special characters.',
                phutil_tag('strong', array(), $v_slug)))
            ->appendParagraph(
              pht(
                'Would you like to use the path %s instead?',
                phutil_tag('strong', array(), $normal_slug)))
            ->addHiddenInput('slug', $normal_slug)
            ->addHiddenInput('description', $v_note)
            ->addCancelButton($cancel_uri)
            ->addSubmitButton(pht('Accept Path'));
        }
      }

      $editor = id(new PhrictionTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setDescription($v_note);

      $xactions = array();
      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(PhrictionTransaction::TYPE_MOVE_TO)
        ->setNewValue($document);
      $target_document = id(new PhrictionDocumentQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withSlugs(array($normal_slug))
        ->needContent(true)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$target_document) {
        $target_document = PhrictionDocument::initializeNewDocument(
          $viewer,
          $v_slug);
      }
      try {
        $editor->applyTransactions($target_document, $xactions);
        $redir_uri = PhrictionDocument::getSlugURI(
          $target_document->getSlug());
        return id(new AphrontRedirectResponse())->setURI($redir_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $e_slug = $ex->getShortMessage(PhrictionTransaction::TYPE_MOVE_TO);
      }
    }


    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Title'))
          ->setValue($document->getContent()->getTitle()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Current Path'))
          ->setDisabled(true)
          ->setValue($slug))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('New Path'))
          ->setValue($v_slug)
          ->setError($e_slug)
          ->setName('slug'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Edit Notes'))
          ->setValue($v_note)
          ->setName('description'));

    return $this->newDialog()
      ->setTitle(pht('Move Document'))
      ->setValidationException($validation_exception)
      ->appendForm($form)
      ->addSubmitButton(pht('Move Document'))
      ->addCancelButton($cancel_uri);
  }

}
