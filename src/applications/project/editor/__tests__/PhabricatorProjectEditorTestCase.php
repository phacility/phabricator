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

    $proj = $this->createProject($user);

    $proj = $this->refreshProject($proj, $user, true);

    $this->joinProject($proj, $user);
    $proj->setViewPolicy(PhabricatorPolicies::POLICY_USER);
    $proj->save();

    $can_view = PhabricatorPolicyCapability::CAN_VIEW;

    // When the view policy is set to "users", any user can see the project.
    $this->assertTrue((bool)$this->refreshProject($proj, $user));
    $this->assertTrue((bool)$this->refreshProject($proj, $user2));


    // When the view policy is set to "no one", members can still see the
    // project.
    $proj->setViewPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->save();

    $this->assertTrue((bool)$this->refreshProject($proj, $user));
    $this->assertFalse((bool)$this->refreshProject($proj, $user2));
  }

  public function testEditProject() {
    $user = $this->createUser();
    $user->save();

    $user2 = $this->createUser();
    $user2->save();

    $proj = $this->createProject($user);


    // When edit and view policies are set to "user", anyone can edit.
    $proj->setViewPolicy(PhabricatorPolicies::POLICY_USER);
    $proj->setEditPolicy(PhabricatorPolicies::POLICY_USER);
    $proj->save();

    $this->assertTrue($this->attemptProjectEdit($proj, $user));


    // When edit policy is set to "no one", no one can edit.
    $proj->setEditPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->save();

    $caught = null;
    try {
      $this->attemptProjectEdit($proj, $user);
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($caught instanceof Exception);
  }

  private function attemptProjectEdit(
    PhabricatorProject $proj,
    PhabricatorUser $user,
    $skip_refresh = false) {

    $proj = $this->refreshProject($proj, $user, true);

    $new_name = $proj->getName().' '.mt_rand();

    $xaction = new PhabricatorProjectTransaction();
    $xaction->setTransactionType(PhabricatorProjectTransaction::TYPE_NAME);
    $xaction->setNewValue($new_name);

    $editor = new PhabricatorProjectTransactionEditor();
    $editor->setActor($user);
    $editor->setContentSource(PhabricatorContentSource::newConsoleSource());
    $editor->applyTransactions($proj, array($xaction));

    return true;
  }

  public function testJoinLeaveProject() {
    $user = $this->createUser();
    $user->save();

    $proj = $this->createProjectWithNewAuthor();

    $proj = $this->refreshProject($proj, $user, true);
    $this->assertTrue(
      (bool)$proj,
      pht(
        'Assumption that projects are default visible '.
        'to any user when created.'));

    $this->assertFalse(
      $proj->isUserMember($user->getPHID()),
      pht('Arbitrary user not member of project.'));

    // Join the project.
    $this->joinProject($proj, $user);

    $proj = $this->refreshProject($proj, $user, true);
    $this->assertTrue((bool)$proj);

    $this->assertTrue(
      $proj->isUserMember($user->getPHID()),
      pht('Join works.'));


    // Join the project again.
    $this->joinProject($proj, $user);

    $proj = $this->refreshProject($proj, $user, true);
    $this->assertTrue((bool)$proj);

    $this->assertTrue(
      $proj->isUserMember($user->getPHID()),
      pht('Joining an already-joined project is a no-op.'));


    // Leave the project.
    $this->leaveProject($proj, $user);

    $proj = $this->refreshProject($proj, $user, true);
    $this->assertTrue((bool)$proj);

    $this->assertFalse(
      $proj->isUserMember($user->getPHID()),
      pht('Leave works.'));


    // Leave the project again.
    $this->leaveProject($proj, $user);

    $proj = $this->refreshProject($proj, $user, true);
    $this->assertTrue((bool)$proj);

    $this->assertFalse(
      $proj->isUserMember($user->getPHID()),
      pht('Leaving an already-left project is a no-op.'));


    // If a user can't edit or join a project, joining fails.
    $proj->setEditPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->setJoinPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->save();

    $proj = $this->refreshProject($proj, $user, true);
    $caught = null;
    try {
      $this->joinProject($proj, $user);
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($ex instanceof Exception);


    // If a user can edit a project, they can join.
    $proj->setEditPolicy(PhabricatorPolicies::POLICY_USER);
    $proj->setJoinPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->save();

    $proj = $this->refreshProject($proj, $user, true);
    $this->joinProject($proj, $user);
    $proj = $this->refreshProject($proj, $user, true);
    $this->assertTrue(
      $proj->isUserMember($user->getPHID()),
      pht('Join allowed with edit permission.'));
    $this->leaveProject($proj, $user);


    // If a user can join a project, they can join, even if they can't edit.
    $proj->setEditPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->setJoinPolicy(PhabricatorPolicies::POLICY_USER);
    $proj->save();

    $proj = $this->refreshProject($proj, $user, true);
    $this->joinProject($proj, $user);
    $proj = $this->refreshProject($proj, $user, true);
    $this->assertTrue(
      $proj->isUserMember($user->getPHID()),
      pht('Join allowed with join permission.'));


    // A user can leave a project even if they can't edit it or join.
    $proj->setEditPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->setJoinPolicy(PhabricatorPolicies::POLICY_NOONE);
    $proj->save();

    $proj = $this->refreshProject($proj, $user, true);
    $this->leaveProject($proj, $user);
    $proj = $this->refreshProject($proj, $user, true);
    $this->assertFalse(
      $proj->isUserMember($user->getPHID()),
      pht('Leave allowed without any permission.'));
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

  private function createProject(PhabricatorUser $user) {
    $project = PhabricatorProject::initializeNewProject($user);
    $project->setName('Test Project '.mt_rand());
    $project->save();

    return $project;
  }

  private function createProjectWithNewAuthor() {
    $author = $this->createUser();
    $author->save();

    $project = $this->createProject($author);

    return $project;
  }

  private function createUser() {
    $rand = mt_rand();

    $user = new PhabricatorUser();
    $user->setUsername('unittestuser'.$rand);
    $user->setRealName('Unit Test User '.$rand);

    return $user;
  }

  private function joinProject(
    PhabricatorProject $project,
    PhabricatorUser $user) {
    $this->joinOrLeaveProject($project, $user, '+');
  }

  private function leaveProject(
    PhabricatorProject $project,
    PhabricatorUser $user) {
    $this->joinOrLeaveProject($project, $user, '-');
  }

  private function joinOrLeaveProject(
    PhabricatorProject $project,
    PhabricatorUser $user,
    $operation) {

    $spec = array(
      $operation => array($user->getPHID() => $user->getPHID()),
    );

    $xactions = array();
    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue(
        'edge:type',
        PhabricatorProjectProjectHasMemberEdgeType::EDGECONST)
      ->setNewValue($spec);

    $editor = id(new PhabricatorProjectTransactionEditor())
      ->setActor($user)
      ->setContentSource(PhabricatorContentSource::newConsoleSource())
      ->setContinueOnNoEffect(true)
      ->applyTransactions($project, $xactions);
  }

}
