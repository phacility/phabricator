<?php

final class PhrictionEditController
  extends PhrictionController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $max_version = null;
    if ($id) {
      $is_new = false;
      $document = id(new PhrictionDocumentQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
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

      $max_version = $document->getMaxVersion();

      $revert = $request->getInt('revert');
      if ($revert) {
        $content = id(new PhrictionContentQuery())
          ->setViewer($viewer)
          ->withDocumentPHIDs(array($document->getPHID()))
          ->withVersions(array($revert))
          ->executeOne();
        if (!$content) {
          return new Aphront404Response();
        }
      } else {
        $content = id(new PhrictionContentQuery())
          ->setViewer($viewer)
          ->withDocumentPHIDs(array($document->getPHID()))
          ->setLimit(1)
          ->executeOne();
      }
    } else {
      $slug = $request->getStr('slug');
      $slug = PhabricatorSlug::normalize($slug);
      if (!$slug) {
        return new Aphront404Response();
      }

      $document = id(new PhrictionDocumentQuery())
        ->setViewer($viewer)
        ->withSlugs(array($slug))
        ->needContent(true)
        ->executeOne();

      if ($document) {
        $content = id(new PhrictionContentQuery())
          ->setViewer($viewer)
          ->withDocumentPHIDs(array($document->getPHID()))
          ->setLimit(1)
          ->executeOne();

        $max_version = $document->getMaxVersion();
        $is_new = false;
      } else {
        $document = PhrictionDocument::initializeNewDocument($viewer, $slug);
        $content = $document->getContent();
        $is_new = true;
      }
    }

    require_celerity_resource('phriction-document-css');

    $e_title = true;
    $e_content = true;
    $validation_exception = null;
    $notes = null;
    $title = $content->getTitle();
    $overwrite = false;
    $v_cc = PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $document->getPHID());

    if ($is_new) {
      $v_projects = array();
    } else {
      $v_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $document->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $v_projects = array_reverse($v_projects);
    }

    $v_space = $document->getSpacePHID();

    $content_text = $content->getContent();
    $is_draft_mode = ($document->getContent()->getVersion() != $max_version);

    $default_view = $document->getViewPolicy();
    $default_edit = $document->getEditPolicy();
    $default_space = $document->getSpacePHID();

    if ($request->isFormPost()) {
      if ($is_new) {
        $save_as_draft = false;
      } else {
        $save_as_draft = ($is_draft_mode || $request->getExists('draft'));
      }

      $title = $request->getStr('title');
      $content_text = $request->getStr('content');
      $notes = $request->getStr('description');
      $max_version = $request->getInt('contentVersion');
      $v_view = $request->getStr('viewPolicy');
      $v_edit = $request->getStr('editPolicy');
      $v_cc = $request->getArr('cc');
      $v_projects = $request->getArr('projects');
      $v_space = $request->getStr('spacePHID');

      if ($save_as_draft) {
        $edit_type = PhrictionDocumentDraftTransaction::TRANSACTIONTYPE;
      } else {
        $edit_type = PhrictionDocumentContentTransaction::TRANSACTIONTYPE;
      }

      $xactions = array();

      if ($is_new) {
        $xactions[] = id(new PhrictionTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_CREATE);
      }

      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(PhrictionDocumentTitleTransaction::TRANSACTIONTYPE)
        ->setNewValue($title);
      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType($edit_type)
        ->setNewValue($content_text);
      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
        ->setNewValue($v_view)
        ->setIsDefaultTransaction($is_new && ($v_view === $default_view));
      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
        ->setNewValue($v_edit)
        ->setIsDefaultTransaction($is_new && ($v_edit === $default_edit));
      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_SPACE)
        ->setNewValue($v_space)
        ->setIsDefaultTransaction($is_new && ($v_space === $default_space));
      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
        ->setNewValue(array('=' => $v_cc));

      $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $proj_edge_type)
        ->setNewValue(array('=' => array_fuse($v_projects)));

      $editor = id(new PhrictionTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setDescription($notes)
        ->setProcessContentVersionError(!$request->getBool('overwrite'))
        ->setContentVersion($max_version);

      try {
        $editor->applyTransactions($document, $xactions);

        $uri = PhrictionDocument::getSlugURI($document->getSlug());
        $uri = new PhutilURI($uri);

        // If the user clicked "Save as Draft", take them to the draft, not
        // to the current published page.
        if ($save_as_draft) {
          $uri = $uri->alter('v', $document->getMaxVersion());
        }

        return id(new AphrontRedirectResponse())->setURI($uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $e_title = nonempty(
          $ex->getShortMessage(
            PhrictionDocumentTitleTransaction::TRANSACTIONTYPE),
          true);
        $e_content = nonempty(
          $ex->getShortMessage(
            PhrictionDocumentContentTransaction::TRANSACTIONTYPE),
          true);

        // if we're not supposed to process the content version error, then
        // overwrite that content...!
        if (!$editor->getProcessContentVersionError()) {
          $overwrite = true;
        }

        $document->setViewPolicy($v_view);
        $document->setEditPolicy($v_edit);
        $document->setSpacePHID($v_space);
      }
    }

    if ($document->getID()) {
      $page_title = pht('Edit Document: %s', $content->getTitle());
      if ($overwrite) {
        $submit_button = pht('Overwrite Changes');
      } else {
        $submit_button = pht('Save and Publish');
      }
    } else {
      $submit_button = pht('Create Document');
      $page_title = pht('Create Document');
    }

    $uri = $document->getSlug();
    $uri = PhrictionDocument::getSlugURI($uri);
    $uri = PhabricatorEnv::getProductionURI($uri);

    $cancel_uri = PhrictionDocument::getSlugURI($document->getSlug());

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($document)
      ->execute();
    $view_capability = PhabricatorPolicyCapability::CAN_VIEW;
    $edit_capability = PhabricatorPolicyCapability::CAN_EDIT;

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('slug', $document->getSlug())
      ->addHiddenInput('contentVersion', $max_version)
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
          ->setUser($viewer))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Tags'))
          ->setName('projects')
          ->setValue($v_projects)
          ->setDatasource(new PhabricatorProjectDatasource()))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Subscribers'))
          ->setName('cc')
          ->setValue($v_cc)
          ->setUser($viewer)
          ->setDatasource(new PhabricatorMetaMTAMailableDatasource()))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setViewer($viewer)
          ->setName('viewPolicy')
          ->setSpacePHID($v_space)
          ->setPolicyObject($document)
          ->setCapability($view_capability)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($document)
          ->setCapability($edit_capability)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Edit Notes'))
          ->setValue($notes)
          ->setError(null)
          ->setName('description'));

    if ($is_draft_mode) {
      $form->appendControl(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue(pht('Save Draft')));
    } else {
      $submit = id(new AphrontFormSubmitControl());

      if (!$is_new) {
        $draft_button = id(new PHUIButtonView())
          ->setTag('input')
          ->setName('draft')
          ->setText(pht('Save as Draft'))
          ->setColor(PHUIButtonView::GREEN);
        $submit->addButton($draft_button);
      }

      $submit
        ->addCancelButton($cancel_uri)
        ->setValue($submit_button);

      $form->appendControl($submit);
    }

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($page_title)
      ->setValidationException($validation_exception)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setForm($form);

    $preview_uri = '/phriction/preview/';
    $preview_uri = new PhutilURI(
      $preview_uri,
      array(
        'slug' => $document->getSlug(),
      ));
    $preview_uri = phutil_string_cast($preview_uri);

    $preview = id(new PHUIRemarkupPreviewPanel())
      ->setHeader($content->getTitle())
      ->setPreviewURI($preview_uri)
      ->setControlID('document-textarea')
      ->setPreviewType(PHUIRemarkupPreviewPanel::DOCUMENT);

    $crumbs = $this->buildApplicationCrumbs();
    if ($document->getID()) {
      $crumbs->addTextCrumb(
        $content->getTitle(),
        PhrictionDocument::getSlugURI($document->getSlug()));
      $crumbs->addTextCrumb(pht('Edit'));
    } else {
      $crumbs->addTextCrumb(pht('Create'));
    }
    $crumbs->setBorder(true);

    $view = id(new PHUITwoColumnView())
      ->setFooter(
        array(
          $form_box,
          $preview,
        ));

    return $this->newPage()
      ->setTitle($page_title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
