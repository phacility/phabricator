<?php

final class DifferentialProjectsField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'phabricator:projects';
  }

  public function getFieldName() {
    return pht('Tags');
  }

  public function getFieldDescription() {
    return pht('Tag projects.');
  }

  public function shouldAppearInPropertyView() {
    return false;
  }

  public function shouldAppearInEditView() {
    return true;
  }

  public function shouldAppearInApplicationTransactions() {
    return true;
  }

  protected function readValueFromRevision(
    DifferentialRevision $revision) {
    if (!$revision->getPHID()) {
      return array();
    }

    $projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $revision->getPHID(),
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
    $projects = array_reverse($projects);

    return $projects;
  }

  public function getNewValueForApplicationTransactions() {
    return array('=' => array_fuse($this->getValue()));
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $this->setValue($request->getArr($this->getFieldKey()));
  }

  public function renderEditControl(array $handles) {
    return id(new AphrontFormTokenizerControl())
      ->setUser($this->getViewer())
      ->setName($this->getFieldKey())
      ->setDatasource(new PhabricatorProjectDatasource())
      ->setValue($this->getValue())
      ->setLabel($this->getFieldName());
  }

  public function getApplicationTransactionType() {
    return PhabricatorTransactions::TYPE_EDGE;
  }

  public function shouldAppearInCommitMessage() {
    return true;
  }

  public function shouldAllowEditInCommitMessage() {
    return true;
  }

  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return true;
  }

  public function getCommitMessageLabels() {
    return array(
      'Tags',
      'Project',
      'Projects',
    );
  }

  public function getRequiredHandlePHIDsForCommitMessage() {
    return $this->getValue();
  }

  public function renderCommitMessageValue(array $handles) {
    return $this->renderObjectList($handles);
  }

  public function shouldAppearInConduitDictionary() {
    return true;
  }

  public function getApplicationTransactionMetadata() {
    return array(
      'edge:type' => PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
    );
  }

  public function parseValueFromCommitMessage($value) {
    return $this->parseObjectList(
      $value,
      array(
        PhabricatorProjectProjectPHIDType::TYPECONST,
      ));
  }

}
