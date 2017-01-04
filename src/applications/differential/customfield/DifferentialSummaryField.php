<?php

final class DifferentialSummaryField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:summary';
  }

  public function getFieldName() {
    return pht('Summary');
  }

  public function getFieldDescription() {
    return pht('Stores a summary of the revision.');
  }

  protected function readValueFromRevision(
    DifferentialRevision $revision) {
    if (!$revision->getID()) {
      return null;
    }
    return $revision->getSummary();
  }

  public function shouldAppearInGlobalSearch() {
    return true;
  }

  public function updateAbstractDocument(
    PhabricatorSearchAbstractDocument $document) {
    if (strlen($this->getValue())) {
      $document->addField('body', $this->getValue());
    }
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function getStyleForPropertyView() {
    return 'block';
  }

  public function getIconForPropertyView() {
    return PHUIPropertyListView::ICON_SUMMARY;
  }

  public function renderPropertyViewValue(array $handles) {
    if (!strlen($this->getValue())) {
      return null;
    }

    return new PHUIRemarkupView($this->getViewer(), $this->getValue());
  }

  public function shouldAppearInTransactionMail() {
    return true;
  }

  public function updateTransactionMailBody(
    PhabricatorMetaMTAMailBody $body,
    PhabricatorApplicationTransactionEditor $editor,
    array $xactions) {

    if (!$editor->getIsNewObject()) {
      return;
    }

    $summary = $this->getValue();
    if (!strlen(trim($summary))) {
      return;
    }

    $body->addRemarkupSection(pht('REVISION SUMMARY'), $summary);
  }

}
