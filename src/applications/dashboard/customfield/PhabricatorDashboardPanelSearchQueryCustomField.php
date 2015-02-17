<?php

final class PhabricatorDashboardPanelSearchQueryCustomField
  extends PhabricatorStandardCustomField {

  public function getFieldType() {
    return 'search.query';
  }

  public function shouldAppearInApplicationSearch() {
    return false;
  }

  public function renderEditControl(array $handles) {

    $engines = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorApplicationSearchEngine')
      ->loadObjects();
    $engines = mfilter($engines, 'canUseInPanelContext');

    $value = $this->getFieldValue();

    $queries = array();
    $seen = false;
    foreach ($engines as $engine_class => $engine) {
      $engine->setViewer($this->getViewer());
      $engine_queries = $engine->loadEnabledNamedQueries();
      $query_map = mpull($engine_queries, 'getQueryName', 'getQueryKey');
      asort($query_map);

      foreach ($query_map as $key => $name) {
        $queries[$engine_class][] = array('key' => $key, 'name' => $name);
        if ($key == $value) {
          $seen = true;
        }
      }
    }

    if (strlen($value) && !$seen) {
      $name = pht('Custom Query ("%s")', $value);
    } else {
      $name = pht('(None)');
    }

    $options = array($value => $name);

    $app_control_key = $this->getFieldConfigValue('control.application');
    Javelin::initBehavior(
      'dashboard-query-panel-select',
      array(
        'applicationID' => $this->getFieldControlID($app_control_key),
        'queryID' => $this->getFieldControlID(),
        'options' => $queries,
        'value' => array(
          'key' => strlen($value) ? $value : null,
          'name' => $name,
        ),
      ));

    return id(new AphrontFormSelectControl())
      ->setID($this->getFieldControlID())
      ->setLabel($this->getFieldName())
      ->setCaption($this->getCaption())
      ->setName($this->getFieldKey())
      ->setValue($this->getFieldValue())
      ->setOptions($options);
  }

}
