<?php

final class PhabricatorCustomUIFooterConfigType
  extends PhabricatorConfigJSONOptionType {

  public function validateOption(PhabricatorConfigOption $option, $value) {
    if (!is_array($value)) {
      throw new Exception(
        pht(
          'Footer configuration is not valid: value must be a list of '.
          'items.'));
    }

    foreach ($value as $idx => $item) {
      if (!is_array($item)) {
        throw new Exception(
          pht(
            'Footer item with index "%s" is invalid: each item must be a '.
            'dictionary describing a footer item.',
            $idx));
      }

      try {
        PhutilTypeSpec::checkMap(
          $item,
          array(
            'name' => 'string',
            'href' => 'optional string',
          ));
      } catch (Exception $ex) {
        throw new Exception(
          pht(
            'Footer item with index "%s" is invalid: %s',
            $idx,
            $ex->getMessage()));
      }
    }
  }


}
