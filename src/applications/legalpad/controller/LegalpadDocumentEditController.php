<?php

/**
 * @group legalpad
 */
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

      $document = id(new LegalpadDocument())
        ->setVersions(0)
        ->setCreatorPHID($user->getPHID())
        ->setContributorCount(0)
        ->setRecentContributorPHIDs(array())
        ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
        ->setEditPolicy(PhabricatorPolicies::POLICY_USER);
      $body = id(new LegalpadDocumentBody())
        ->setCreatorPHID($user->getPHID());
      $document->attachDocumentBody($body);
      $document->setDocumentBodyPHID(PhabricatorPHIDConstants::PHID_VOID);
      $title = null;
      $text = null;
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
      $title = $document->getDocumentBody()->getTitle();
      $text = $document->getDocumentBody()->getText();
    }

    $e_title = true;
    $e_text = true;
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

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('A Fatal Omission!'))
        ->setErrors($errors);
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
        ->setName('title'))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
        ->setID('document-text')
        ->setLabel(pht('Text'))
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

    $submit = new AphrontFormSubmitControl();
    if ($is_create) {
      $submit->setValue(pht('Create Document'));
      $title = pht('Create Document');
      $short = pht('Create');
    } else {
      $submit->setValue(pht('Update Document'));
      $submit->addCancelButton(
          $this->getApplicationURI('view/'.$document->getID()));
      $title = pht('Update Document');
      $short = pht('Update');
    }

    $form
      ->appendChild($submit);

    $form_box = id(new PHUIFormBoxView())
      ->setHeaderText($title)
      ->setFormError($error_view)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNav());
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())->setName($short));


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
        'device' => true,
      ));
  }

}
