<?php

abstract class PhabricatorTypeaheadProxyDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  private $datasource;

  public function setDatasource(PhabricatorTypeaheadDatasource $datasource) {
    $this->datasource = $datasource;
    $this->setParameters(
      array(
        'class' => get_class($datasource),
        'parameters' => $datasource->getParameters(),
      ));
    return $this;
  }

  public function getDatasource() {
    if (!$this->datasource) {
      $class = $this->getParameter('class');

      $parent = 'PhabricatorTypeaheadDatasource';
      if (!is_subclass_of($class, $parent)) {
        throw new Exception(
          pht(
            'Configured datasource class "%s" must be a valid subclass of '.
            '"%s".',
            $class,
            $parent));
      }

      $datasource = newv($class, array());
      $datasource->setParameters($this->getParameter('parameters', array()));
      $this->datasource = $datasource;
    }

    return $this->datasource;
  }

  public function getComponentDatasources() {
    return array(
      $this->getDatasource(),
    );
  }

  public function getDatasourceApplicationClass() {
    return $this->getDatasource()->getDatasourceApplicationClass();
  }

  public function getBrowseTitle() {
    return $this->getDatasource()->getBrowseTitle();
  }

  public function getPlaceholderText() {
    return $this->getDatasource()->getPlaceholderText();
  }

}
