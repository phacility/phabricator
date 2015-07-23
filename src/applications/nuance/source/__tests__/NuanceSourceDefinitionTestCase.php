<?php

final class NuanceSourceDefinitionTestCase extends PhabricatorTestCase {

  public function testGetAllTypes() {
    NuanceSourceDefinition::getAllDefinitions();
    $this->assertTrue(true);
  }

}
