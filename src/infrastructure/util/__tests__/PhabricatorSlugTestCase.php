<?php

final class PhabricatorSlugTestCase extends PhabricatorTestCase {

  public function testSlugNormalization() {
    $slugs = array(
      ''                  => '/',
      '/'                 => '/',
      '//'                => '/',
      '&&&'               => '_/',
      '/derp/'            => 'derp/',
      'derp'              => 'derp/',
      'derp//derp'        => 'derp/derp/',
      'DERP//DERP'        => 'derp/derp/',
      'a B c'             => 'a_b_c/',
      '-1~2.3abcd'        => '-1~2.3abcd/',
      "T\x00O\x00D\x00O"  => 't_o_d_o/',
      'x#%&+=\\?<> y'     => 'x_y/',
      "\xE2\x98\x83"      => "\xE2\x98\x83/",
      '..'                => 'dotdot/',
      '../'               => 'dotdot/',
      '/../'              => 'dotdot/',
      'a/b'               => 'a/b/',
      'a//b'              => 'a/b/',
      'a/../b/'           => 'a/dotdot/b/',
      '/../a'             => 'dotdot/a/',
      '../a'              => 'dotdot/a/',
      'a/..'              => 'a/dotdot/',
      'a/../'             => 'a/dotdot/',
      'a?'                => 'a/',
      '??'                => '_/',
      'a/?'               => 'a/_/',
      '??/a/??'           => '_/a/_/',
      'a/??/c'            => 'a/_/c/',
      'a/?b/c'            => 'a/b/c/',
      'a/b?/c'            => 'a/b/c/',
      'a - b'             => 'a_-_b/',
      'a[b]'              => 'a_b/',
      'ab!'               => 'ab!/',
    );

    foreach ($slugs as $slug => $normal) {
      $this->assertEqual(
        $normal,
        PhabricatorSlug::normalize($slug),
        pht("Normalization of '%s'", $slug));
    }
  }

  public function testProjectSlugs() {
    $slugs = array(
      'a:b' => 'a_b',
      'a!b' => 'a_b',
      'a - b' => 'a_-_b',
      '' => '',
      'Demonology: HSA (Hexes, Signs, Alchemy)' =>
        'demonology_hsa_hexes_signs_alchemy',
    );

    foreach ($slugs as $slug => $normal) {
      $this->assertEqual(
        $normal,
        PhabricatorSlug::normalizeProjectSlug($slug),
        pht('Hashtag normalization of "%s"', $slug));
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
        pht("Ancestry of '%s'", $slug));
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
        pht("Depth of '%s'", $slug));
    }
  }
}
