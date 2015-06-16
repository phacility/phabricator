<?php

final class PhabricatorUserTestCase extends PhabricatorTestCase {

  public function testUsernameValidation() {
    $map = array(
      'alincoln'    => true,
      'alincoln69'  => true,
      'hd3'         => true,
      'Alincoln'    => true,
      'a.lincoln'   => true,

      'alincoln!'   => false,
      ''            => false,

      // These are silly, but permitted.
      '7'           => true,
      '0'           => true,
      '____'        => true,
      '-'           => true,

      // These are not permitted because they make capturing @mentions
      // ambiguous.
      'joe.'        => false,

      // We can never allow these because they invalidate usernames as tokens
      // in commit messages ("Reviewers: alincoln, usgrant"), or as parameters
      // in URIs ("/p/alincoln/", "?user=alincoln"), or make them unsafe in
      // HTML. Theoretically we escape all the HTML/URI stuff, but these
      // restrictions make attacks more difficult and are generally reasonable,
      // since usernames like "<^, ,^>" don't seem very important to support.
      '<script>'    => false,
      'a lincoln'   => false,
      ' alincoln'   => false,
      'alincoln '   => false,
      'a,lincoln'   => false,
      'a&lincoln'   => false,
      'a/lincoln'   => false,

      "username\n"  => false,
      "user\nname"  => false,
      "\nusername"  => false,
      "username\r"  => false,
      "user\rname"  => false,
      "\rusername"  => false,
    );

    foreach ($map as $name => $expect) {
      $this->assertEqual(
        $expect,
        PhabricatorUser::validateUsername($name),
        pht("Validity of '%s'.", $name));
    }
  }

}
