<?php

final class PhabricatorConduitTestCase extends PhabricatorTestCase {

  public function testConduitMethods() {
    $methods = id(new PhutilClassMapQuery())
      ->setAncestorClass('ConduitAPIMethod')
      ->execute();

    // We're just looking for a side effect of ConduitCall construction
    // here: it will throw if any methods define reserved parameter names.

    foreach ($methods as $method) {
      new ConduitCall($method->getAPIMethodName(), array());
    }

    $this->assertTrue(true);
  }
}
