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

final class PhabricatorRepositoryTestCase
  extends PhabricatorTestCase {

  public function testBranchFilter() {
    $git = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;

    $repo = new PhabricatorRepository();
    $repo->setVersionControlSystem($git);

    $this->assertEqual(
      true,
      $repo->shouldTrackBranch('imaginary'),
      'Track all branches by default.');

    $repo->setDetail(
      'branch-filter',
      array(
        'master' => true,
      ));

    $this->assertEqual(
      true,
      $repo->shouldTrackBranch('master'),
      'Track listed branches.');

    $this->assertEqual(
      false,
      $repo->shouldTrackBranch('imaginary'),
      'Do not track unlisted branches.');
  }

}
