<?php

final class PhabricatorPolicySearchEngineExtension
  extends PhabricatorSearchEngineExtension {

  const EXTENSIONKEY = 'policy';

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('Support for Policies');
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorPolicyInterface);
  }

  public function getFieldSpecificationsForConduit($object) {
    return array(
      'policy' => array(
        'type' => 'map<string, wild>',
        'description' => pht(
          'Map of capabilities to current policies.'),
      ),
    );
  }

  public function getFieldValuesForConduit($object) {
    $capabilities = $object->getCapabilities();

    $map = array();
    foreach ($capabilities as $capability) {
      $map[$capability] = $object->getPolicy($capability);
    }

    return array(
      'policy' => $map,
    );
  }

}
