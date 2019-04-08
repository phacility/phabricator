<?php

final class PhabricatorDashboardQueryPanelApplicationEditField
  extends PhabricatorEditField {

  private $controlID;

  protected function newControl() {
    $engines = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorApplicationSearchEngine')
      ->setFilterMethod('canUseInPanelContext')
      ->execute();

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

    $value = $this->getValueForControl();
    if (strlen($value) && empty($engines[$value])) {
      $options[$value] = $value;
    }

    $engines = msort($engines, 'getResultTypeDescription');
    foreach ($engines as $class_name => $engine) {
      $options[$class_name] = $engine->getResultTypeDescription();
    }

    return id(new AphrontFormSelectControl())
      ->setID($this->getControlID())
      ->setOptions($options);
  }

  protected function newHTTPParameterType() {
    return new AphrontSelectHTTPParameterType();
  }

  public function getControlID() {
    if (!$this->controlID) {
      $this->controlID = celerity_generate_unique_node_id();
    }

    return $this->controlID;
  }

  protected function newConduitParameterType() {
    return new ConduitStringParameterType();
  }

}
