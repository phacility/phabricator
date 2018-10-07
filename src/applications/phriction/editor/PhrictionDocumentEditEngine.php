<?php

final class PhrictionDocumentEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'phriction.document';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Phriction Document');
  }

  public function getSummaryHeader() {
    return pht('Edit Phriction Document Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Phriction documents.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorPhrictionApplication';
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    return PhrictionDocument::initializeNewDocument(
      $viewer,
      '/');
  }

  protected function newObjectQuery() {
    return id(new PhrictionDocumentQuery())
      ->needContent(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Document');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Document');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Document: %s', $object->getContent()->getTitle());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Document');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Document');
  }

  protected function getObjectName() {
    return pht('Document');
  }

  protected function getEditorURI() {
    return '/phriction/document/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/phriction/document/';
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getCreateNewObjectPolicy() {
    // NOTE: For now, this engine is only to support commenting.
    return PhabricatorPolicies::POLICY_NOONE;
  }

  protected function buildCustomEditFields($object) {
    return array();
  }

}
