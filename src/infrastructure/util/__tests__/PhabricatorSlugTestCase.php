<?php

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
