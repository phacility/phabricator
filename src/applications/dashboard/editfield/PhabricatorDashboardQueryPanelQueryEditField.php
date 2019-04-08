<?php

final class PhabricatorDashboardQueryPanelQueryEditField
  extends PhabricatorEditField {

  private $applicationControlID;

  public function setApplicationControlID($id) {
    $this->applicationControlID = $id;
    return $this;
  }

  public function getApplicationControlID() {
    return $this->applicationControlID;
  }

  protected function newControl() {
    $engines = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorApplicationSearchEngine')
      ->setFilterMethod('canUseInPanelContext')
      ->execute();

    $value = $this->getValueForControl();

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

    $application_id = $this->getApplicationControlID();
    $control_id = celerity_generate_unique_node_id();

    Javelin::initBehavior(
      'dashboard-query-panel-select',
      array(
        'applicationID' => $application_id,
        'queryID' => $control_id,
        'options' => $queries,
        'value' => array(
          'key' => strlen($value) ? $value : null,
          'name' => $name,
        ),
      ));

    return id(new AphrontFormSelectControl())
      ->setID($control_id)
      ->setOptions($options);
  }

  protected function newHTTPParameterType() {
    return new AphrontSelectHTTPParameterType();
  }

  protected function newConduitParameterType() {
    return new ConduitStringParameterType();
  }

}
