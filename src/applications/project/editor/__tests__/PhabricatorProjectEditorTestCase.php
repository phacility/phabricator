<?php

final class PhabricatorProjectEditorTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testViewProject() {
    $user = $this->createUser();
    $user->save();

    $user2 = $this->createUser();
    $user2->save();

    $proj = $this->createProject();
    $proj->setAuthorPHID($user->getPHID());
    $proj->save();

    $proj = $this->refreshProject($proj, $user, true);

    PhabricatorProjectEditor::applyJoinProject($proj, $user);
    $proj->setViewPolicy(PhabricatorPolicies::POLICY_USER);
    $proj->save();

    $can_view = PhabricatorPolicyCapability::CAN_VIEW;

    // When the view policy is set to "users", any user can see the project.
    $this->assertEqual(
      true,
      (bool)$this->refreshProject($proj, $user));
    $this->assertEqual(
      true,
      (bool)$this->refreshProject($proj, $user2));


    // When the view policy is set to "no one", members can still see the
    // project.
    $proj->setViewPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->save();

    $this->assertEqual(
      true,
      (bool)$this->refreshProject($proj, $user));
    $this->assertEqual(
      false,
      (bool)$this->refreshProject($proj, $user2));
  }

  public function testEditProject() {
    $user = $this->createUser();
    $user->save();

    $user2 = $this->createUser();
    $user2->save();

    $proj = $this->createProject();
    $proj->setAuthorPHID($user->getPHID());
    $proj->save();


    // When edit and view policies are set to "user", anyone can edit.
    $proj->setViewPolicy(PhabricatorPolicies::POLICY_USER);
    $proj->setEditPolicy(PhabricatorPolicies::POLICY_USER);
    $proj->save();

    $this->assertEqual(
      true,
      $this->attemptProjectEdit($proj, $user));


    // When edit policy is set to "no one", no one can edit.
    $proj->setEditPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->save();

    $caught = null;
    try {
      $this->attemptProjectEdit($proj, $user);
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertEqual(true, ($caught instanceof Exception));
  }

  private function attemptProjectEdit(
    PhabricatorProject $proj,
    PhabricatorUser $user,
    $skip_refresh = false) {

    $proj = $this->refreshProject($proj, $user, true);

    $new_name = $proj->getName().' '.mt_rand();

    $xaction = new PhabricatorProjectTransaction();
    $xaction->setTransactionType(PhabricatorProjectTransactionType::TYPE_NAME);
    $xaction->setNewValue($new_name);

    $editor = new PhabricatorProjectEditor($proj);
    $editor->setActor($user);
    $editor->applyTransactions(array($xaction));

    return true;
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


    // If a user can't edit or join a project, joining fails.
    $proj->setEditPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->setJoinPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->save();

    $proj = $this->refreshProject($proj, $user, true);
    $caught = null;
    try {
      PhabricatorProjectEditor::applyJoinProject($proj, $user);
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertEqual(true, ($ex instanceof Exception));


    // If a user can edit a project, they can join.
    $proj->setEditPolicy(PhabricatorPolicies::POLICY_USER);
    $proj->setJoinPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->save();

    $proj = $this->refreshProject($proj, $user, true);
    PhabricatorProjectEditor::applyJoinProject($proj, $user);
    $proj = $this->refreshProject($proj, $user, true);
    $this->assertEqual(
      true,
      $proj->isUserMember($user->getPHID()),
      'Join allowed with edit permission.');
    PhabricatorProjectEditor::applyLeaveProject($proj, $user);


    // If a user can join a project, they can join, even if they can't edit.
    $proj->setEditPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->setJoinPolicy(PhabricatorPolicies::POLICY_USER);
    $proj->save();

    $proj = $this->refreshProject($proj, $user, true);
    PhabricatorProjectEditor::applyJoinProject($proj, $user);
    $proj = $this->refreshProject($proj, $user, true);
    $this->assertEqual(
      true,
      $proj->isUserMember($user->getPHID()),
      'Join allowed with join permission.');


    // A user can leave a project even if they can't edit it or join.
    $proj->setEditPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->setJoinPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->save();

    $proj = $this->refreshProject($proj, $user, true);
    PhabricatorProjectEditor::applyLeaveProject($proj, $user);
    $proj = $this->refreshProject($proj, $user, true);
    $this->assertEqual(
      false,
      $proj->isUserMember($user->getPHID()),
      'Leave allowed without any permission.');
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
