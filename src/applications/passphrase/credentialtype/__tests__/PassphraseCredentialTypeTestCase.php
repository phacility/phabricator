<?php

final class PassphraseCredentialTypeTestCase extends PhabricatorTestCase {

  public function testGetAllTypes() {
    PassphraseCredentialType::getAllTypes();
    $this->assertTrue(true);
  }

}
