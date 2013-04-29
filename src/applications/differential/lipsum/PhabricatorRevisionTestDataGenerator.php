<?php

final class PhabricatorRevisionTestDataGenerator
  extends PhabricatorTestDataGenerator {

  public function generate() {
      try {
        $revision = new DifferentialRevision();
        echo id(new PhutilLipsumContextFreeGrammar())->generate();
        return $revision;
      } catch (AphrontQueryDuplicateKeyException $ex) {
      }
  }
}
