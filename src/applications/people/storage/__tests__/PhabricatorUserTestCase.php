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
      '7'           => true,  // Silly, but permitted.
      '0'           => true,
      'Alincoln'    => true,

      'alincoln!'   => false,
      ' alincoln'   => false,
      '____'        => false,
      ''            => false,
    );

    foreach ($map as $name => $expect) {
      $this->assertEqual(
        $expect,
        PhabricatorUser::validateUsername($name),
        "Validity of '{$name}'.");
    }
  }

}
