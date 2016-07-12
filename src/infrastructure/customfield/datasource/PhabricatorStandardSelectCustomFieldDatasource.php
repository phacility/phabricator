<?php

final class PhabricatorStandardSelectCustomFieldDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Values');
  }

  public function getPlaceholderText() {
    return pht('Type a field value...');
  }

  public function getDatasourceApplicationClass() {
    return null;
  }

  public function loadResults() {
    $viewer = $this->getViewer();

    $class = $this->getParameter('object');
    if (!class_exists($class)) {
      throw new Exception(
        pht(
          'Custom field class "%s" does not exist.',
          $class));
    }

    $reflection = new ReflectionClass($class);
    $interface = 'PhabricatorCustomFieldInterface';
    if (!$reflection->implementsInterface($interface)) {
      throw new Exception(
        pht(
          'Custom field class "%s" does not implement interface "%s".',
          $class,
          $interface));
    }

    $role = $this->getParameter('role');
    if (!strlen($role)) {
      throw new Exception(pht('No custom field role specified.'));
    }

    $object = newv($class, array());
    $field_list = PhabricatorCustomField::getObjectFields($object, $role);

    $field_key = $this->getParameter('key');
    if (!strlen($field_key)) {
      throw new Exception(pht('No custom field key specified.'));
    }

    $field = null;
    foreach ($field_list->getFields() as $candidate_field) {
      if ($candidate_field->getFieldKey() == $field_key) {
        $field = $candidate_field;
        break;
      }
    }

    if ($field === null) {
      throw new Exception(
        pht(
          'No field with field key "%s" exists for objects of class "%s" with '.
          'custom field role "%s".',
          $field_key,
          $class,
          $role));
    }

    if (!($field instanceof PhabricatorStandardCustomFieldSelect)) {
      $field = $field->getProxy();
      if (!($field instanceof PhabricatorStandardCustomFieldSelect)) {
        throw new Exception(
          pht(
            'Field "%s" is not a standard select field, nor a proxy of a '.
            'standard select field.',
            $field_key));
      }
    }

    $options = $field->getOptions();

    $results = array();
    foreach ($options as $key => $option) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($option)
        ->setPHID($key);
    }

    return $this->filterResultsAgainstTokens($results);
  }

}
