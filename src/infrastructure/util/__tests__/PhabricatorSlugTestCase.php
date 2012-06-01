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

final class PhabricatorSlugTestCase extends PhabricatorTestCase {

  public function testSlugNormalization() {
    $slugs = array(
      ''                  => '/',
      '/'                 => '/',
      '//'                => '/',
      '&&&'               => '/',
      '/derp/'            => 'derp/',
      'derp'              => 'derp/',
      'derp//derp'        => 'derp/derp/',
      'DERP//DERP'        => 'derp/derp/',
      'a B c'             => 'a_b_c/',
      '-1~2.3abcd'        => '1_2_3abcd/',
      "T\x95O\x95D\x95O"  => 't_o_d_o/',
    );

    foreach ($slugs as $slug => $normal) {
      $this->assertEqual(
        $normal,
        PhabricatorSlug::normalize($slug),
        "Normalization of '{$slug}'");
    }
  }

  public function testSlugAncestry() {
    $slugs = array(
      '/'                   => array(),
      'pokemon/'            => array('/'),
      'pokemon/squirtle/'   => array('/', 'pokemon/'),
    );

    foreach ($slugs as $slug => $ancestry) {
      $this->assertEqual(
        $ancestry,
        PhabricatorSlug::getAncestry($slug),
        "Ancestry of '{$slug}'");
    }
  }

  public function testSlugDepth() {
    $slugs = array(
      '/'       => 0,
      'a/'      => 1,
      'a/b/'    => 2,
      'a////b/' => 2,
    );

    foreach ($slugs as $slug => $depth) {
      $this->assertEqual(
        $depth,
        PhabricatorSlug::getDepth($slug),
        "Depth of '{$slug}'");
    }
  }
}
