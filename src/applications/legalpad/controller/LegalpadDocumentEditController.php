<?php

final class LegalpadDocumentEditController extends LegalpadController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if (!$id) {
      $is_create = true;

      $this->requireApplicationCapability(
        LegalpadCreateDocumentsCapability::CAPABILITY);

      $document = LegalpadDocument::initializeNewDocument($viewer);
      $body = id(new LegalpadDocumentBody())
        ->setCreatorPHID($viewer->getPHID());
      $document->attachDocumentBody($body);
      $document->setDocumentBodyPHID(PhabricatorPHIDConstants::PHID_VOID);
    } else {
      $is_create = false;

      $document = id(new LegalpadDocumentQuery())
        ->setViewer($viewer)
        ->needDocumentBodies(true)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->withIDs(array($id))
        ->executeOne();
      if (!$document) {
        return new Aphront404Response();
      }
    }

    $e_title = true;
    $e_text = true;

    $title = $document->getDocumentBody()->getTitle();
    $text = $document->getDocumentBody()->getText();
    $v_signature_type = $document->getSignatureType();
    $v_preamble = $document->getPreamble();
    $v_require_signature = $document->getRequireSignature();

    $errors = array();
    $can_view = null;
    $can_edit = null;
    if ($request->isFormPost()) {

      $xactions = array();

      $title = $request->getStr('title');
      if (!strlen($title)) {
        $e_title = pht('Required');
        $errors[] = pht('The document title may not be blank.');
      } else {
        $xactions[] = id(new LegalpadTransaction())
          ->setTransactionType(LegalpadTransaction::TYPE_TITLE)
          ->setNewValue($title);
      }

      $text = $request->getStr('text');
      if (!strlen($text)) {
        $e_text = pht('Required');
        $errors[] = pht('The document may not be blank.');
      } else {
        $xactions[] = id(new LegalpadTransaction())
          ->setTransactionType(LegalpadTransaction::TYPE_TEXT)
          ->setNewValue($text);
      }

      $can_view = $request->getStr('can_view');
      $xactions[] = id(new LegalpadTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
        ->setNewValue($can_view);
      $can_edit = $request->getStr('can_edit');
      $xactions[] = id(new LegalpadTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
        ->setNewValue($can_edit);

      if ($is_create) {
        $v_signature_type = $request->getStr('signatureType');
        $xactions[] = id(new LegalpadTransaction())
          ->setTransactionType(LegalpadTransaction::TYPE_SIGNATURE_TYPE)
          ->setNewValue($v_signature_type);
      }

      $v_preamble = $request->getStr('preamble');
      $xactions[] = id(new LegalpadTransaction())
        ->setTransactionType(LegalpadTransaction::TYPE_PREAMBLE)
        ->setNewValue($v_preamble);

      $v_require_signature = $request->getBool('requireSignature', 0);
      if ($v_require_signature) {
        if (!$viewer->getIsAdmin()) {
          $errors[] = pht('Only admins may require signature.');
        }
        $individual = LegalpadDocument::SIGNATURE_TYPE_INDIVIDUAL;
        if ($v_signature_type != $individual) {
          $errors[] = pht(
            'Only documents with signature type "individual" may require '.
            'signing to use Phabricator.');
        }
      }
      if ($viewer->getIsAdmin()) {
        $xactions[] = id(new LegalpadTransaction())
          ->setTransactionType(LegalpadTransaction::TYPE_REQUIRE_SIGNATURE)
          ->setNewValue($v_require_signature);
      }

      if (!$errors) {
        $editor = id(new LegalpadDocumentEditor())
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true)
          ->setActor($viewer);

        $xactions = $editor->applyTransactions($document, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI($this->getApplicationURI('view/'.$document->getID()));
      }
    }

    if ($errors) {
      // set these to what was specified in the form on post
      $document->setViewPolicy($can_view);
      $document->setEditPolicy($can_edit);
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setID('document-title')
        ->setLabel(pht('Title'))
        ->setError($e_title)
        ->setValue($title)
        ->setName('title'));

    if ($is_create) {
      $form->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Who Should Sign?'))
          ->setName(pht('signatureType'))
          ->setValue($v_signature_type)
          ->setOptions(LegalpadDocument::getSignatureTypeMap()));
      $show_require = true;
      $caption = pht('Applies only to documents individuals sign.');
    } else {
      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Who Should Sign?'))
          ->setValue($document->getSignatureTypeName()));
      $individual = LegalpadDocument::SIGNATURE_TYPE_INDIVIDUAL;
      $show_require = $document->getSignatureType() == $individual;
      $caption = null;
    }

    if ($show_require) {
      $form
        ->appendChild(
          id(new AphrontFormCheckboxControl())
          ->setDisabled(!$viewer->getIsAdmin())
          ->setLabel(pht('Require Signature'))
          ->addCheckbox(
            'requireSignature',
            'requireSignature',
            pht('Should signing this document be required to use Phabricator?'),
            $v_require_signature)
          ->setCaption($caption));
    }

    $form
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setID('preamble')
          ->setLabel(pht('Preamble'))
          ->setValue($v_preamble)
          ->setName('preamble')
          ->setCaption(
            pht('Optional help text for users signing this document.')))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setID('document-text')
          ->setLabel(pht('Document Body'))
          ->setError($e_text)
          ->setValue($text)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setName('text'));

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($document)
      ->execute();

    $form
      ->appendChild(
        id(new AphrontFormPolicyControl())
        ->setUser($viewer)
        ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
        ->setPolicyObject($document)
        ->setPolicies($policies)
        ->setName('can_view'))
      ->appendChild(
        id(new AphrontFormPolicyControl())
        ->setUser($viewer)
        ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
        ->setPolicyObject($document)
        ->setPolicies($policies)
        ->setName('can_edit'));

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNav());
    $submit = new AphrontFormSubmitControl();
    if ($is_create) {
      $submit->setValue(pht('Create Document'));
      $submit->addCancelButton($this->getApplicationURI());
      $title = pht('Create Document');
      $short = pht('Create');
      $header_icon = 'fa-plus-square';
    } else {
      $submit->setValue(pht('Save Document'));
      $submit->addCancelButton(
          $this->getApplicationURI('view/'.$document->getID()));
      $title = pht('Edit Document: %s', $document->getTitle());
      $short = pht('Edit');
      $header_icon = 'fa-pencil';

      $crumbs->addTextCrumb(
        $document->getMonogram(),
        $this->getApplicationURI('view/'.$document->getID()));
    }

    $form->appendChild($submit);

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Document'))
      ->setFormErrors($errors)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $crumbs->addTextCrumb($short);
    $crumbs->setBorder(true);

    $preview = id(new PHUIRemarkupPreviewPanel())
      ->setHeader($document->getTitle())
      ->setPreviewURI($this->getApplicationURI('document/preview/'))
      ->setControlID('document-text')
      ->setPreviewType(PHUIRemarkupPreviewPanel::DOCUMENT);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon($header_icon);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $form_box,
        $preview,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
