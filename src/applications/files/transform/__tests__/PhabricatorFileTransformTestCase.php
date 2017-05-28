<?php

final class PhabricatorFileTransformTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testGetAllTransforms() {
    PhabricatorFileTransform::getAllTransforms();
    $this->assertTrue(true);
  }

  public function testThumbTransformDefaults() {
    $xforms = PhabricatorFileTransform::getAllTransforms();
    $file = new PhabricatorFile();

    foreach ($xforms as $xform) {
      if (!($xform instanceof PhabricatorFileThumbnailTransform)) {
        continue;
      }

      // For thumbnails, generate the default thumbnail. This should be able
      // to generate something rather than throwing an exception because we
      // forgot to add a default file to the builtin resources. See T12614.
      $xform->getDefaultTransform($file);

      $this->assertTrue(true);
    }
  }

}
