<?php

final class PhabricatorClusterMailersConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'cluster.mailers';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {

    if ($value === null) {
      return;
    }

    if (!is_array($value)) {
      throw $this->newException(
        pht(
          'Mailer cluster configuration is not valid: it should be a list '.
          'of mailer configurations.'));
    }

    foreach ($value as $index => $spec) {
      if (!is_array($spec)) {
        throw $this->newException(
          pht(
            'Mailer cluster configuration is not valid: each entry in the '.
            'list must be a dictionary describing a mailer, but the value '.
            'with index "%s" is not a dictionary.',
            $index));
      }
    }

    $adapters = PhabricatorMailAdapter::getAllAdapters();

    $map = array();
    foreach ($value as $index => $spec) {
      try {
        PhutilTypeSpec::checkMap(
          $spec,
          array(
            'key' => 'string',
            'type' => 'string',
            'priority' => 'optional int',
            'options' => 'optional wild',
            'inbound' => 'optional bool',
            'outbound' => 'optional bool',
            'media' => 'optional list<string>',
          ));
      } catch (Exception $ex) {
        throw $this->newException(
          pht(
            'Mailer configuration has an invalid mailer specification '.
            '(at index "%s"): %s.',
            $index,
            $ex->getMessage()));
      }

      $key = $spec['key'];
      if (isset($map[$key])) {
        throw $this->newException(
          pht(
            'Mailer configuration is invalid: multiple mailers have the same '.
            'key ("%s"). Each mailer must have a unique key.',
            $key));
      }
      $map[$key] = true;

      $priority = idx($spec, 'priority');
      if ($priority !== null && $priority <= 0) {
        throw $this->newException(
          pht(
            'Mailer configuration ("%s") is invalid: priority must be '.
            'greater than 0.',
            $key));
      }

      $type = $spec['type'];
      if (!isset($adapters[$type])) {
        throw $this->newException(
          pht(
            'Mailer configuration ("%s") is invalid: mailer type ("%s") is '.
            'unknown. Supported mailer types are: %s.',
            $key,
            $type,
            implode(', ', array_keys($adapters))));
      }

      $options = idx($spec, 'options', array());
      try {
        $defaults = $adapters[$type]->newDefaultOptions();
        $options = $options + $defaults;
        id(clone $adapters[$type])->setOptions($options);
      } catch (Exception $ex) {
        throw $this->newException(
          pht(
            'Mailer configuration ("%s") specifies invalid options for '.
            'mailer: %s',
            $key,
            $ex->getMessage()));
      }
    }
  }

}
