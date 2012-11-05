<?php

/**
 * @group phriction
 */
final class PhrictionDocumentTestCase extends PhabricatorTestCase {

  public function testProjectSlugs() {
    $slugs = array(
      '/'                   => false,
      'zebra/'              => false,
      'projects/'           => false,
      'projects/a/'         => true,
      'projects/a/b/'       => true,
      'stuff/projects/a/'   => false,
    );

    foreach ($slugs as $slug => $expect) {
      $this->assertEqual(
        $expect,
        PhrictionDocument::isProjectSlug($slug),
        "Is '{$slug}' a project slug?");
    }
  }

  public function testProjectSlugIdentifiers() {
    $slugs = array(
      'projects/'     => null,
      'derp/'         => null,
      'projects/a/'   => 'a/',
      'projects/a/b/' => 'a/',
    );

    foreach ($slugs as $slug => $expect) {
      $ex = null;
      $result = null;
      try {
        $result = PhrictionDocument::getProjectSlugIdentifier($slug);
      } catch (Exception $e) {
        $ex = $e;
      }

      if ($expect === null) {
        $this->assertEqual(true, (bool)$ex, "Slug '{$slug}' is invalid.");
      } else {
        $this->assertEqual($expect, $result, "Slug '{$slug}' identifier.");
      }
    }
  }

}
