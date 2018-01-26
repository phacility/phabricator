<?php

abstract class PhabricatorEditType extends Phobject {

  private $editType;
  private $editField;
  private $transactionType;
  private $label;
  private $metadata = array();

  private $conduitDescription;
  private $conduitDocumentation;
  private $conduitTypeDescription;
  private $conduitParameterType;

  private $bulkParameterType;
  private $bulkEditLabel;
  private $bulkEditGroupKey;

  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  public function getLabel() {
    return $this->label;
  }

  public function setBulkEditLabel($bulk_edit_label) {
    $this->bulkEditLabel = $bulk_edit_label;
    return $this;
  }

  public function getBulkEditLabel() {
    if ($this->bulkEditLabel !== null) {
      return $this->bulkEditLabel;
    }

    return $this->getEditField()->getBulkEditLabel();
  }

  public function setBulkEditGroupKey($key) {
    $this->bulkEditGroupKey = $key;
    return $this;
  }

  public function getBulkEditGroupKey() {
    if ($this->bulkEditGroupKey !== null) {
      return $this->bulkEditGroupKey;
    }

    return $this->getEditField()->getBulkEditGroupKey();
  }

  public function setEditType($edit_type) {
    $this->editType = $edit_type;
    return $this;
  }

  public function getEditType() {
    return $this->editType;
  }

  public function setMetadata($metadata) {
    $this->metadata = $metadata;
    return $this;
  }

  public function getMetadata() {
    return $this->metadata;
  }

  public function setTransactionType($transaction_type) {
    $this->transactionType = $transaction_type;
    return $this;
  }

  public function getTransactionType() {
    return $this->transactionType;
  }

  abstract public function generateTransactions(
    PhabricatorApplicationTransaction $template,
    array $spec);

  protected function newTransaction(
    PhabricatorApplicationTransaction $template) {

    $xaction = id(clone $template)
      ->setTransactionType($this->getTransactionType());

    foreach ($this->getMetadata() as $key => $value) {
      $xaction->setMetadataValue($key, $value);
    }

    return $xaction;
  }

  public function setEditField(PhabricatorEditField $edit_field) {
    $this->editField = $edit_field;
    return $this;
  }

  public function getEditField() {
    return $this->editField;
  }

  protected function getTransactionValueFromValue($value) {
    return $value;
  }


/* -(  Bulk  )--------------------------------------------------------------- */


  protected function newBulkParameterType() {
    if ($this->bulkParameterType) {
      return clone $this->bulkParameterType;
    }

    return null;
  }


  public function setBulkParameterType(BulkParameterType $type) {
    $this->bulkParameterType = $type;
    return $this;
  }


  public function getBulkParameterType() {
    return $this->newBulkParameterType();
  }

  public function getTransactionValueFromBulkEdit($value) {
    return $this->getTransactionValueFromValue($value);
  }


/* -(  Conduit  )------------------------------------------------------------ */


  protected function newConduitParameterType() {
    if ($this->conduitParameterType) {
      return clone $this->conduitParameterType;
    }

    return null;
  }

  public function setConduitParameterType(ConduitParameterType $type) {
    $this->conduitParameterType = $type;
    return $this;
  }

  public function getConduitParameterType() {
    return $this->newConduitParameterType();
  }

  public function getConduitType() {
    $parameter_type = $this->getConduitParameterType();
    if (!$parameter_type) {
      throw new Exception(
        pht(
          'Edit type (with key "%s") is missing a Conduit parameter type.',
          $this->getEditType()));
    }

    return $parameter_type->getTypeName();
  }

  public function setConduitTypeDescription($conduit_type_description) {
    $this->conduitTypeDescription = $conduit_type_description;
    return $this;
  }

  public function getConduitTypeDescription() {
    if ($this->conduitTypeDescription === null) {
      if ($this->getEditField()) {
        return $this->getEditField()->getConduitTypeDescription();
      }
    }

    return $this->conduitTypeDescription;
  }

  public function setConduitDescription($conduit_description) {
    $this->conduitDescription = $conduit_description;
    return $this;
  }

  public function getConduitDescription() {
    if ($this->conduitDescription === null) {
      if ($this->getEditField()) {
        return $this->getEditField()->getConduitDescription();
      }
    }

    return $this->conduitDescription;
  }

  public function setConduitDocumentation($conduit_documentation) {
    $this->conduitDocumentation = $conduit_documentation;
    return $this;
  }

  public function getConduitDocumentation() {
    if ($this->conduitDocumentation === null) {
      if ($this->getEditField()) {
        return $this->getEditField()->getConduitDocumentation();
      }
    }

    return $this->conduitDocumentation;
  }

  public function getTransactionValueFromConduit($value) {
    return $this->getTransactionValueFromValue($value);
  }

}
