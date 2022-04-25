<?php

final class LegalpadDocumentEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'legalpad.document';

  public function getEngineName() {
    return pht('Legalpad');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorLegalpadApplication';
  }

  public function getSummaryHeader() {
    return pht('Configure Legalpad Forms');
  }

  public function getSummaryText() {
    return pht('Configure creation and editing documents in Legalpad.');
  }

  public function isEngineConfigurable() {
    return false;
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();

    $document = LegalpadDocument::initializeNewDocument($viewer);
    $body = id(new LegalpadDocumentBody())
      ->setCreatorPHID($viewer->getPHID());
    $document->attachDocumentBody($body);
    $document->setDocumentBodyPHID(PhabricatorPHIDConstants::PHID_VOID);

    return $document;
  }

  protected function newObjectQuery() {
    return id(new LegalpadDocumentQuery())
      ->needDocumentBodies(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Document');
  }

  protected function getObjectEditTitleText($object) {
    $body = $object->getDocumentBody();
    $title = $body->getTitle();
    return pht('Edit Document: %s', $title);
  }

  protected function getObjectEditShortText($object) {
    $body = $object->getDocumentBody();
    return $body->getTitle();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Document');
  }

  protected function getObjectName() {
    return pht('Document');
  }

  protected function getObjectCreateCancelURI($object) {
    return $this->getApplication()->getApplicationURI('/');
  }

  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('edit/');
  }

  protected function getObjectViewURI($object) {
    $id = $object->getID();
    return $this->getApplication()->getApplicationURI('view/'.$id.'/');
  }


  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      LegalpadCreateDocumentsCapability::CAPABILITY);
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();

    $body = $object->getDocumentBody();
    $document_body = $body->getText();

    $is_create = $this->getIsCreate();
    $is_admin = $viewer->getIsAdmin();

    $fields = array();
    $fields[] =
      id(new PhabricatorTextEditField())
        ->setKey('title')
        ->setLabel(pht('Title'))
        ->setDescription(pht('Document Title.'))
        ->setConduitTypeDescription(pht('New document title.'))
        ->setValue($object->getTitle())
        ->setIsRequired(true)
        ->setTransactionType(
          LegalpadDocumentTitleTransaction::TRANSACTIONTYPE);

    if ($is_create) {
      $fields[] =
        id(new PhabricatorSelectEditField())
          ->setKey('signatureType')
          ->setLabel(pht('Who Should Sign?'))
          ->setDescription(pht('Type of signature required'))
          ->setConduitTypeDescription(pht('New document signature type.'))
          ->setValue($object->getSignatureType())
          ->setOptions(LegalpadDocument::getSignatureTypeMap())
          ->setTransactionType(
            LegalpadDocumentSignatureTypeTransaction::TRANSACTIONTYPE);
      $show_require = true;
    } else {
      $fields[] = id(new PhabricatorStaticEditField())
        ->setLabel(pht('Who Should Sign?'))
        ->setValue($object->getSignatureTypeName());
      $individual = LegalpadDocument::SIGNATURE_TYPE_INDIVIDUAL;
      $show_require = $object->getSignatureType() == $individual;
    }

    if ($show_require && $is_admin) {
      $fields[] =
        id(new PhabricatorBoolEditField())
          ->setKey('requireSignature')
          ->setOptions(
            pht('No Signature Required'),
            pht('Signature Required to Log In'))
          ->setAsCheckbox(true)
          ->setTransactionType(
            LegalpadDocumentRequireSignatureTransaction::TRANSACTIONTYPE)
          ->setDescription(pht('Marks this document as required signing.'))
          ->setConduitDescription(
            pht('Marks this document as required signing.'))
          ->setValue($object->getRequireSignature());
    }

    $fields[] =
      id(new PhabricatorRemarkupEditField())
        ->setKey('preamble')
        ->setLabel(pht('Preamble'))
        ->setDescription(pht('The preamble of the document.'))
        ->setConduitTypeDescription(pht('New document preamble.'))
        ->setValue($object->getPreamble())
        ->setTransactionType(
          LegalpadDocumentPreambleTransaction::TRANSACTIONTYPE);

    $fields[] =
      id(new PhabricatorRemarkupEditField())
        ->setKey('text')
        ->setLabel(pht('Document Body'))
        ->setDescription(pht('The body of text of the document.'))
        ->setConduitTypeDescription(pht('New document body.'))
        ->setValue($document_body)
        ->setIsRequired(true)
        ->setTransactionType(
          LegalpadDocumentTextTransaction::TRANSACTIONTYPE);

    return $fields;

  }

}
