<?php

final class PhabricatorStandardCustomFieldDatasource
  extends PhabricatorStandardCustomFieldTokenizer {

  public function getFieldType() {
    return 'datasource';
  }

  public function getDatasource() {
    $parameters = $this->getFieldConfigValue('datasource.parameters', array());

    $class = $this->getFieldConfigValue('datasource.class');
    $parent = 'PhabricatorTypeaheadDatasource';
    if (!is_subclass_of($class, $parent)) {
      throw new Exception(
        pht(
          'Configured datasource class "%s" must be a valid subclass of '.
          '"%s".',
          $class,
          $parent));
    }

    return newv($class, array())
      ->setParameters($parameters);
  }

}
