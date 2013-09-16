<?php

final class PhabricatorStandardCustomFieldRemarkup
  extends PhabricatorStandardCustomField {

  public function getFieldType() {
    return 'remarkup';
  }

  public function renderEditControl() {
    return id(new PhabricatorRemarkupControl())
      ->setLabel($this->getFieldName())
      ->setName($this->getFieldKey())
      ->setValue($this->getFieldValue());
  }

  public function getStyleForPropertyView() {
    return 'block';
  }

  public function renderPropertyViewValue() {
    $value = $this->getFieldValue();

    if (!strlen($value)) {
      return null;
    }

    // TODO: Once this stabilizes, it would be nice to let fields batch this.
    // For now, an extra query here and there on object detail pages isn't the
    // end of the world.

    $viewer = $this->getViewer();
    return PhabricatorMarkupEngine::renderOneObject(
      id(new PhabricatorMarkupOneOff())->setContent($value),
      'default',
      $viewer);
  }

}
