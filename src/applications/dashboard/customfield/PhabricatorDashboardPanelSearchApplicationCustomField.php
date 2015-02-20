<?php

final class PhabricatorDashboardPanelSearchApplicationCustomField
  extends PhabricatorStandardCustomField {

  public function getFieldType() {
    return 'search.application';
  }

  public function shouldAppearInApplicationSearch() {
    return false;
  }

  public function renderEditControl(array $handles) {

    $engines = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorApplicationSearchEngine')
      ->loadObjects();
    $engines = mfilter($engines, 'canUseInPanelContext');
    $all_apps = id(new PhabricatorApplicationQuery())
      ->setViewer($this->getViewer())
      ->withUnlisted(false)
      ->withInstalled(true)
      ->execute();
    foreach ($engines as $index => $engine) {
      if (!isset($all_apps[$engine->getApplicationClassName()])) {
        unset($engines[$index]);
        continue;
      }
    }

    $options = array();

    $value = $this->getFieldValue();
    if (strlen($value) && empty($engines[$value])) {
      $options[$value] = $value;
    }

    $engines = msort($engines, 'getResultTypeDescription');
    foreach ($engines as $class_name => $engine) {
      $options[$class_name] = $engine->getResultTypeDescription();
    }

    return id(new AphrontFormSelectControl())
      ->setID($this->getFieldControlID())
      ->setLabel($this->getFieldName())
      ->setCaption($this->getCaption())
      ->setName($this->getFieldKey())
      ->setValue($this->getFieldValue())
      ->setOptions($options);
  }

}
