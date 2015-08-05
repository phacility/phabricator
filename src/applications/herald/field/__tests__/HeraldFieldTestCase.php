<?php

final class HeraldFieldTestCase extends PhutilTestCase {

  public function testGetAllFields() {
    HeraldField::getAllFields();
    $this->assertTrue(true);
  }

}
