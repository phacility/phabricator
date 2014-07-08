<?php

final class LegalpadDocumentEditController extends LegalpadController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if (!$this->id) {
      $is_create = true;

      $this->requireApplicationCapability(
        LegalpadCapabilityCreateDocuments::CAPABILITY);

      $document = LegalpadDocument::initializeNewDocument($user);
      $body = id(new LegalpadDocumentBody())
        ->setCreatorPHID($user->getPHID());
      $document->attachDocumentBody($body);
      $document->setDocumentBodyPHID(PhabricatorPHIDConstants::PHID_VOID);
    } else {
      $is_create = false;

      $document = id(new LegalpadDocumentQuery())
        ->setViewer($user)
        ->needDocumentBodies(true)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->withIDs(array($this->id))
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
          ->setTransactionType(LegalpadTransactionType::TYPE_TITLE)
          ->setNewValue($title);
      }

      $text = $request->getStr('text');
      if (!strlen($text)) {
        $e_text = pht('Required');
        $errors[] = pht('The document may not be blank.');
      } else {
        $xactions[] = id(new LegalpadTransaction())
          ->setTransactionType(LegalpadTransactionType::TYPE_TEXT)
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
          ->setTransactionType(LegalpadTransactionType::TYPE_SIGNATURE_TYPE)
          ->setNewValue($v_signature_type);
      }

      $v_preamble = $request->getStr('preamble');
      $xactions[] = id(new LegalpadTransaction())
        ->setTransactionType(LegalpadTransactionType::TYPE_PREAMBLE)
        ->setNewValue($v_preamble);

      if (!$errors) {
        $editor = id(new LegalpadDocumentEditor())
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true)
          ->setActor($user);

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
      ->setUser($user)
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
    } else {
      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Who Should Sign?'))
          ->setValue($document->getSignatureTypeName()));
    }

    $form
      ->appendChild(
        id(new PhabricatorRemarkupControl())
        ->setID('preamble')
        ->setLabel(pht('Preamble'))
        ->setValue($v_preamble)
        ->setName('preamble')
        ->setCaption(
          pht('Optional help text for users signing this document.')))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
        ->setID('document-text')
        ->setLabel(pht('Document Body'))
        ->setError($e_text)
        ->setValue($text)
        ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
        ->setName('text'));

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($document)
      ->execute();

    $form
      ->appendChild(
        id(new AphrontFormPolicyControl())
        ->setUser($user)
        ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
        ->setPolicyObject($document)
        ->setPolicies($policies)
        ->setName('can_view'))
      ->appendChild(
        id(new AphrontFormPolicyControl())
        ->setUser($user)
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
    } else {
      $submit->setValue(pht('Edit Document'));
      $submit->addCancelButton(
          $this->getApplicationURI('view/'.$document->getID()));
      $title = pht('Edit Document');
      $short = pht('Edit');

      $crumbs->addTextCrumb(
        $document->getMonogram(),
        $this->getApplicationURI('view/'.$document->getID()));
    }

    $form
      ->appendChild($submit);

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    $crumbs->addTextCrumb($short);

    $preview = id(new PHUIRemarkupPreviewPanel())
      ->setHeader(pht('Document Preview'))
      ->setPreviewURI($this->getApplicationURI('document/preview/'))
      ->setControlID('document-text')
      ->setSkin('document');

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
        $preview
      ),
      array(
        'title' => $title,
      ));
  }

}
