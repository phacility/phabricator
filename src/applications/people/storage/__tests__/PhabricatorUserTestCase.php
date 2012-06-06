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
    );

    foreach ($map as $name => $expect) {
      $this->assertEqual(
        $expect,
        PhabricatorUser::validateUsername($name),
        "Validity of '{$name}'.");
    }
  }

}
