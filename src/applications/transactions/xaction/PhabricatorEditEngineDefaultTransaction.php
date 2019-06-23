<?php

final class PhabricatorEditEngineDefaultTransaction
  extends PhabricatorEditEngineTransactionType {

  const TRANSACTIONTYPE = 'editengine.config.default';

  public function generateOldValue($object) {
    $field_key = $this->getMetadataValue('field.key');
    return $object->getFieldDefault($field_key);
  }

  public function applyInternalEffects($object, $value) {
    $field_key = $this->getMetadataValue('field.key');
    $object->setFieldDefault($field_key, $value);
  }

  public function getTitle() {
    $key = $this->getMetadataValue('field.key');
    $object = $this->getObject();
    $engine = $object->getEngine();
    $fields = $engine->getFieldsForConfig($object);
    $field = idx($fields, $key);

    if (!$field) {
      return pht(
        '%s changed the default values for field %s.',
        $this->renderAuthor(),
        $this->renderValue($key));
    }

    return pht(
      '%s changed the default value for field %s.',
      $this->renderAuthor(),
      $this->renderValue($field->getLabel()));
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function newChangeDetailView() {
    $viewer = $this->getViewer();
    $old = $this->renderDefaultValueAsFallbackText($this->getOldValue());
    $new = $this->renderDefaultValueAsFallbackText($this->getNewValue());

    return id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setViewer($viewer)
      ->setOldText($old)
      ->setNewText($new);
  }

  private function renderDefaultValueAsFallbackText($default_value) {
    // See T13319. When rendering an "alice changed the default value for
    // field X." story on custom forms, we may fall back to a generic
    // rendering. Try to present the value change in a comprehensible way
    // even if it isn't especially human readable (for example, it may
    // contain PHIDs or other internal identifiers).

    if (is_scalar($default_value) || is_null($default_value)) {
      return $default_value;
    }

    if (phutil_is_natural_list($default_value)) {
      return id(new PhutilJSON())->encodeAsList($default_value);
    }

    return id(new PhutilJSON())->encodeAsObject($default_value);
  }

}
