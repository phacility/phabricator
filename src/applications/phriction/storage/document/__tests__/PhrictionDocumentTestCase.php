<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
