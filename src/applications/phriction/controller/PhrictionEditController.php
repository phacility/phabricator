<?php

final class PhrictionEditController
  extends PhrictionController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $current_version = null;
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
      if (!$document) {
        return new Aphront404Response();
      }

      $current_version = $document->getContent()->getVersion();

      $revert = $request->getInt('revert');
      if ($revert) {
        $content = id(new PhrictionContent())->loadOneWhere(
          'documentID = %d AND version = %d',
          $document->getID(),
          $revert);
        if (!$content) {
          return new Aphront404Response();
        }
      } else {
        $content = $document->getContent();
      }

    } else {
      $slug = $request->getStr('slug');
      $slug = PhabricatorSlug::normalize($slug);
      if (!$slug) {
        return new Aphront404Response();
      }

      $document = id(new PhrictionDocumentQuery())
        ->setViewer($user)
        ->withSlugs(array($slug))
        ->needContent(true)
        ->executeOne();

      if ($document) {
        $content = $document->getContent();
        $current_version = $content->getVersion();
      } else {
        $document = PhrictionDocument::initializeNewDocument($user, $slug);
        $content = $document->getContent();
      }
    }

    if ($request->getBool('nodraft')) {
      $draft = null;
      $draft_key = null;
    } else {
      if ($document->getPHID()) {
        $draft_key = $document->getPHID().':'.$content->getVersion();
      } else {
        $draft_key = 'phriction:'.$content->getSlug();
      }
      $draft = id(new PhabricatorDraft())->loadOneWhere(
        'authorPHID = %s AND draftKey = %s',
        $user->getPHID(),
        $draft_key);
    }

    if ($draft &&
      strlen($draft->getDraft()) &&
      ($draft->getDraft() != $content->getContent())) {
      $content_text = $draft->getDraft();

      $discard = phutil_tag(
        'a',
        array(
          'href' => $request->getRequestURI()->alter('nodraft', true),
        ),
        pht('discard this draft'));

      $draft_note = new PHUIInfoView();
      $draft_note->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
      $draft_note->setTitle(pht('Recovered Draft'));
      $draft_note->appendChild(
        hsprintf(
          '<p>%s</p>',
          pht(
            'Showing a saved draft of your edits, you can %s.',
            $discard)));
    } else {
      $content_text = $content->getContent();
      $draft_note = null;
    }

    require_celerity_resource('phriction-document-css');

    $e_title = true;
    $e_content = true;
    $validation_exception = null;
    $notes = null;
    $title = $content->getTitle();
    $overwrite = false;

    if ($request->isFormPost()) {

      $title = $request->getStr('title');
      $content_text = $request->getStr('content');
      $notes = $request->getStr('description');
      $current_version = $request->getInt('contentVersion');
      $v_view = $request->getStr('viewPolicy');
      $v_edit = $request->getStr('editPolicy');

      $xactions = array();
      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(PhrictionTransaction::TYPE_TITLE)
        ->setNewValue($title);
      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(PhrictionTransaction::TYPE_CONTENT)
        ->setNewValue($content_text);
      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
        ->setNewValue($v_view);
      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
        ->setNewValue($v_edit);

      $editor = id(new PhrictionTransactionEditor())
        ->setActor($user)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setDescription($notes)
        ->setProcessContentVersionError(!$request->getBool('overwrite'))
        ->setContentVersion($current_version);

      try {
        $editor->applyTransactions($document, $xactions);

        if ($draft) {
          $draft->delete();
        }

        $uri = PhrictionDocument::getSlugURI($document->getSlug());
        return id(new AphrontRedirectResponse())->setURI($uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $e_title = nonempty(
          $ex->getShortMessage(PhrictionTransaction::TYPE_TITLE),
          true);
        $e_content = nonempty(
          $ex->getShortMessage(PhrictionTransaction::TYPE_CONTENT),
          true);

        // if we're not supposed to process the content version error, then
        // overwrite that content...!
        if (!$editor->getProcessContentVersionError()) {
          $overwrite = true;
        }

        $document->setViewPolicy($v_view);
        $document->setEditPolicy($v_edit);
      }
    }

    if ($document->getID()) {
      $panel_header = pht('Edit Phriction Document');
      $page_title = pht('Edit Document');
      if ($overwrite) {
        $submit_button = pht('Overwrite Changes');
      } else {
        $submit_button = pht('Save Changes');
      }
    } else {
      $panel_header = pht('Create New Phriction Document');
      $submit_button = pht('Create Document');
      $page_title = pht('Create Document');
    }

    $uri = $document->getSlug();
    $uri = PhrictionDocument::getSlugURI($uri);
    $uri = PhabricatorEnv::getProductionURI($uri);

    $cancel_uri = PhrictionDocument::getSlugURI($document->getSlug());

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($document)
      ->execute();
    $view_capability = PhabricatorPolicyCapability::CAN_VIEW;
    $edit_capability = PhabricatorPolicyCapability::CAN_EDIT;

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->addHiddenInput('slug', $document->getSlug())
      ->addHiddenInput('nodraft', $request->getBool('nodraft'))
      ->addHiddenInput('contentVersion', $current_version)
      ->addHiddenInput('overwrite', $overwrite)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Title'))
          ->setValue($title)
          ->setError($e_title)
          ->setName('title'))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('URI'))
          ->setValue($uri))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setLabel(pht('Content'))
          ->setValue($content_text)
          ->setError($e_content)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setName('content')
          ->setID('document-textarea')
          ->setUser($user))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($document)
          ->setCapability($view_capability)
          ->setPolicies($policies)
          ->setCaption(
            $document->describeAutomaticCapability($view_capability)))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($document)
          ->setCapability($edit_capability)
          ->setPolicies($policies)
          ->setCaption(
            $document->describeAutomaticCapability($edit_capability)))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Edit Notes'))
          ->setValue($notes)
          ->setError(null)
          ->setName('description'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($submit_button));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($panel_header)
      ->setValidationException($validation_exception)
      ->setForm($form);

    $preview = id(new PHUIRemarkupPreviewPanel())
      ->setHeader(pht('Document Preview'))
      ->setPreviewURI('/phriction/preview/')
      ->setControlID('document-textarea')
      ->setSkin('document');

    $crumbs = $this->buildApplicationCrumbs();
    if ($document->getID()) {
      $crumbs->addTextCrumb(
        $content->getTitle(),
        PhrictionDocument::getSlugURI($document->getSlug()));
      $crumbs->addTextCrumb(pht('Edit'));
    } else {
      $crumbs->addTextCrumb(pht('Create'));
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $draft_note,
        $form_box,
        $preview,
      ),
      array(
        'title'   => $page_title,
      ));
  }

}
