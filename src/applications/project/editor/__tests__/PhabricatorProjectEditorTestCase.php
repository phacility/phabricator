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

final class PhabricatorProjectEditorTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testJoinLeaveProject() {
    $user = $this->createUser();
    $user->save();

    $proj = $this->createProjectWithNewAuthor();
    $proj->save();

    $proj = $this->refreshProject($proj, $user, true);
    $this->assertEqual(
      true,
      (bool)$proj,
      'Assumption that projects are default visible to any user when created.');

    $this->assertEqual(
      false,
      $proj->isUserMember($user->getPHID()),
      'Arbitrary user not member of project.');

    // Join the project.
    PhabricatorProjectEditor::applyJoinProject($proj, $user);

    $proj = $this->refreshProject($proj, $user, true);
    $this->assertEqual(true, (bool)$proj);

    $this->assertEqual(
      true,
      $proj->isUserMember($user->getPHID()),
      'Join works.');


    // Join the project again.
    PhabricatorProjectEditor::applyJoinProject($proj, $user);

    $proj = $this->refreshProject($proj, $user, true);
    $this->assertEqual(true, (bool)$proj);

    $this->assertEqual(
      true,
      $proj->isUserMember($user->getPHID()),
      'Joining an already-joined project is a no-op.');


    // Leave the project.
    PhabricatorProjectEditor::applyLeaveProject($proj, $user);

    $proj = $this->refreshProject($proj, $user, true);
    $this->assertEqual(true, (bool)$proj);

    $this->assertEqual(
      false,
      $proj->isUserMember($user->getPHID()),
      'Leave works.');


    // Leave the project again.
    PhabricatorProjectEditor::applyLeaveProject($proj, $user);

    $proj = $this->refreshProject($proj, $user, true);
    $this->assertEqual(true, (bool)$proj);

    $this->assertEqual(
      false,
      $proj->isUserMember($user->getPHID()),
      'Leaving an already-left project is a no-op.');
  }

  private function refreshProject(
    PhabricatorProject $project,
    PhabricatorUser $viewer,
    $need_members = false) {

    $results = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->needMembers($need_members)
      ->withIDs(array($project->getID()))
      ->execute();

    if ($results) {
      return head($results);
    } else {
      return null;
    }
  }

  private function createProject() {
    $project = new PhabricatorProject();
    $project->setName('Test Project '.mt_rand());

    return $project;
  }

  private function createProjectWithNewAuthor() {
    $author = $this->createUser();
    $author->save();

    $project = $this->createProject();
    $project->setAuthorPHID($author->getPHID());

    return $project;
  }

  private function createUser() {
    $rand = mt_rand();

    $user = new PhabricatorUser();
    $user->setUsername('unittestuser'.$rand);
    $user->setRealName('Unit Test User '.$rand);

    return $user;
  }

}
