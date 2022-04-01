<?php

final class PhabricatorProjectCoreTestCase extends PhabricatorTestCase {

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

  public function testApplicationPolicy() {
    $user = $this->createUser()
      ->save();

    $proj = $this->createProject($user);

    $this->assertTrue(
      PhabricatorPolicyFilter::hasCapability(
        $user,
        $proj,
        PhabricatorPolicyCapability::CAN_VIEW));

    // This object is visible so its handle should load normally.
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($proj->getPHID()))
      ->executeOne();
    $this->assertEqual($proj->getPHID(), $handle->getPHID());

    // Change the "Can Use Application" policy for Projecs to "No One". This
    // should cause filtering checks to fail even when they are executed
    // directly rather than via a Query.
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig(
      'phabricator.application-settings',
      array(
        'PHID-APPS-PhabricatorProjectApplication' => array(
          'policy' => array(
            'view' => PhabricatorPolicies::POLICY_NOONE,
          ),
        ),
      ));

    // Application visibility is cached because it does not normally change
    // over the course of a single request. Drop the cache so the next filter
    // test uses the new visibility.
    PhabricatorCaches::destroyRequestCache();

    $this->assertFalse(
      PhabricatorPolicyFilter::hasCapability(
        $user,
        $proj,
        PhabricatorPolicyCapability::CAN_VIEW));

    // We should still be able to load a handle for the project, even if we
    // can not see the application.
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($proj->getPHID()))
      ->executeOne();

    // The handle should load...
    $this->assertEqual($proj->getPHID(), $handle->getPHID());

    // ...but be policy filtered.
    $this->assertTrue($handle->getPolicyFiltered());

    unset($env);
  }

  public function testIsViewerMemberOrWatcher() {
    $user1 = $this->createUser()
      ->save();

    $user2 = $this->createUser()
      ->save();

    $user3 = $this->createUser()
      ->save();

    $proj1 = $this->createProject($user1);
    $proj1 = $this->refreshProject($proj1, $user1);

    $this->joinProject($proj1, $user1);
    $this->joinProject($proj1, $user3);
    $this->watchProject($proj1, $user3);

    $proj1 = $this->refreshProject($proj1, $user1);

    $this->assertTrue($proj1->isUserMember($user1->getPHID()));

    $proj1 = $this->refreshProject($proj1, $user1, false, true);

    $this->assertTrue($proj1->isUserMember($user1->getPHID()));
    $this->assertFalse($proj1->isUserWatcher($user1->getPHID()));

    $proj1 = $this->refreshProject($proj1, $user1, true, false);

    $this->assertTrue($proj1->isUserMember($user1->getPHID()));
    $this->assertFalse($proj1->isUserMember($user2->getPHID()));
    $this->assertTrue($proj1->isUserMember($user3->getPHID()));

    $proj1 = $this->refreshProject($proj1, $user1, true, true);

    $this->assertTrue($proj1->isUserMember($user1->getPHID()));
    $this->assertFalse($proj1->isUserMember($user2->getPHID()));
    $this->assertTrue($proj1->isUserMember($user3->getPHID()));

    $this->assertFalse($proj1->isUserWatcher($user1->getPHID()));
    $this->assertFalse($proj1->isUserWatcher($user2->getPHID()));
    $this->assertTrue($proj1->isUserWatcher($user3->getPHID()));
  }

  public function testEditProject() {
    $user = $this->createUser();
    $user->save();

    $user->setAllowInlineCacheGeneration(true);

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

  public function testAncestorMembers() {
    $user1 = $this->createUser();
    $user1->save();

    $user2 = $this->createUser();
    $user2->save();

    $parent = $this->createProject($user1);
    $child = $this->createProject($user1, $parent);

    $this->joinProject($child, $user1);
    $this->joinProject($child, $user2);

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($user1)
      ->withPHIDs(array($child->getPHID()))
      ->needAncestorMembers(true)
      ->executeOne();

    $members = array_fuse($project->getParentProject()->getMemberPHIDs());
    ksort($members);

    $expect = array_fuse(
      array(
        $user1->getPHID(),
        $user2->getPHID(),
      ));
    ksort($expect);

    $this->assertEqual($expect, $members);
  }

  public function testAncestryQueries() {
    $user = $this->createUser();
    $user->save();

    $ancestor = $this->createProject($user);
    $parent = $this->createProject($user, $ancestor);
    $child = $this->createProject($user, $parent);

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withAncestorProjectPHIDs(array($ancestor->getPHID()))
      ->execute();
    $this->assertEqual(2, count($projects));

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withParentProjectPHIDs(array($ancestor->getPHID()))
      ->execute();
    $this->assertEqual(1, count($projects));
    $this->assertEqual(
      $parent->getPHID(),
      head($projects)->getPHID());

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withAncestorProjectPHIDs(array($ancestor->getPHID()))
      ->withDepthBetween(2, null)
      ->execute();
    $this->assertEqual(1, count($projects));
    $this->assertEqual(
      $child->getPHID(),
      head($projects)->getPHID());

    $parent2 = $this->createProject($user, $ancestor);
    $child2 = $this->createProject($user, $parent2);
    $grandchild2 = $this->createProject($user, $child2);

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withAncestorProjectPHIDs(array($ancestor->getPHID()))
      ->execute();
    $this->assertEqual(5, count($projects));

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withParentProjectPHIDs(array($ancestor->getPHID()))
      ->execute();
    $this->assertEqual(2, count($projects));

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withAncestorProjectPHIDs(array($ancestor->getPHID()))
      ->withDepthBetween(2, null)
      ->execute();
    $this->assertEqual(3, count($projects));

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withAncestorProjectPHIDs(array($ancestor->getPHID()))
      ->withDepthBetween(3, null)
      ->execute();
    $this->assertEqual(1, count($projects));

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withPHIDs(
        array(
          $child->getPHID(),
          $grandchild2->getPHID(),
        ))
      ->execute();
    $this->assertEqual(2, count($projects));
  }

  public function testMemberMaterialization() {
    $material_type = PhabricatorProjectMaterializedMemberEdgeType::EDGECONST;

    $user = $this->createUser();
    $user->save();

    $parent = $this->createProject($user);
    $child = $this->createProject($user, $parent);

    $this->joinProject($child, $user);

    $parent_material = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $parent->getPHID(),
      $material_type);

    $this->assertEqual(
      array($user->getPHID()),
      $parent_material);
  }

  public function testMilestones() {
    $user = $this->createUser();
    $user->save();

    $parent = $this->createProject($user);

    $m1 = $this->createProject($user, $parent, true);
    $m2 = $this->createProject($user, $parent, true);
    $m3 = $this->createProject($user, $parent, true);

    $this->assertEqual(1, $m1->getMilestoneNumber());
    $this->assertEqual(2, $m2->getMilestoneNumber());
    $this->assertEqual(3, $m3->getMilestoneNumber());
  }

  public function testMilestoneMembership() {
    $user = $this->createUser();
    $user->save();

    $parent = $this->createProject($user);
    $milestone = $this->createProject($user, $parent, true);

    $this->joinProject($parent, $user);

    $milestone = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withPHIDs(array($milestone->getPHID()))
      ->executeOne();

    $this->assertTrue($milestone->isUserMember($user->getPHID()));

    $milestone = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withPHIDs(array($milestone->getPHID()))
      ->needMembers(true)
      ->executeOne();

    $this->assertEqual(
      array($user->getPHID()),
      $milestone->getMemberPHIDs());
  }

  public function testSameSlugAsName() {
    // It should be OK to type the primary hashtag into "additional hashtags",
    // even if the primary hashtag doesn't exist yet because you're creating
    // or renaming the project.

    $user = $this->createUser();
    $user->save();

    $project = $this->createProject($user);

    // In this first case, set the name and slugs at the same time.
    $name = 'slugproject';

    $xactions = array();
    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorProjectNameTransaction::TRANSACTIONTYPE)
      ->setNewValue($name);
    $this->applyTransactions($project, $user, $xactions);

    $xactions = array();
    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorProjectSlugsTransaction::TRANSACTIONTYPE)
      ->setNewValue(array($name));
    $this->applyTransactions($project, $user, $xactions);

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withPHIDs(array($project->getPHID()))
      ->needSlugs(true)
      ->executeOne();

    $slugs = $project->getSlugs();
    $slugs = mpull($slugs, 'getSlug');

    $this->assertTrue(in_array($name, $slugs));

    // In this second case, set the name first and then the slugs separately.
    $name2 = 'slugproject2';
    $xactions = array();

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorProjectNameTransaction::TRANSACTIONTYPE)
      ->setNewValue($name2);

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorProjectSlugsTransaction::TRANSACTIONTYPE)
      ->setNewValue(array($name2));

    $this->applyTransactions($project, $user, $xactions);

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withPHIDs(array($project->getPHID()))
      ->needSlugs(true)
      ->executeOne();

    $slugs = $project->getSlugs();
    $slugs = mpull($slugs, 'getSlug');

    $this->assertTrue(in_array($name2, $slugs));
  }

  public function testDuplicateSlugs() {
    // Creating a project with multiple duplicate slugs should succeed.

    $user = $this->createUser();
    $user->save();

    $project = $this->createProject($user);

    $input = 'duplicate';

    $xactions = array();

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorProjectSlugsTransaction::TRANSACTIONTYPE)
      ->setNewValue(array($input, $input));

    $this->applyTransactions($project, $user, $xactions);

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withPHIDs(array($project->getPHID()))
      ->needSlugs(true)
      ->executeOne();

    $slugs = $project->getSlugs();
    $slugs = mpull($slugs, 'getSlug');

    $this->assertTrue(in_array($input, $slugs));
  }

  public function testNormalizeSlugs() {
    // When a user creates a project with slug "XxX360n0sc0perXxX", normalize
    // it before writing it.

    $user = $this->createUser();
    $user->save();

    $project = $this->createProject($user);

    $input = 'NoRmAlIzE';
    $expect = 'normalize';

    $xactions = array();

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorProjectSlugsTransaction::TRANSACTIONTYPE)
      ->setNewValue(array($input));

    $this->applyTransactions($project, $user, $xactions);

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withPHIDs(array($project->getPHID()))
      ->needSlugs(true)
      ->executeOne();

    $slugs = $project->getSlugs();
    $slugs = mpull($slugs, 'getSlug');

    $this->assertTrue(in_array($expect, $slugs));


    // If another user tries to add the same slug in denormalized form, it
    // should be caught and fail, even though the database version of the slug
    // is normalized.

    $project2 = $this->createProject($user);

    $xactions = array();

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorProjectSlugsTransaction::TRANSACTIONTYPE)
      ->setNewValue(array($input));

    $caught = null;
    try {
      $this->applyTransactions($project2, $user, $xactions);
    } catch (PhabricatorApplicationTransactionValidationException $ex) {
      $caught = $ex;
    }

    $this->assertTrue((bool)$caught);
  }

  public function testProjectMembersVisibility() {
    // This is primarily testing that you can create a project and set the
    // visibility or edit policy to "Project Members" immediately.

    $user1 = $this->createUser();
    $user1->save();

    $user2 = $this->createUser();
    $user2->save();

    $project = PhabricatorProject::initializeNewProject($user1);
    $name = pht('Test Project %d', mt_rand());

    $xactions = array();

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorProjectNameTransaction::TRANSACTIONTYPE)
      ->setNewValue($name);

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
      ->setNewValue(
        id(new PhabricatorProjectMembersPolicyRule())
          ->getObjectPolicyFullKey());

    $edge_type = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $edge_type)
      ->setNewValue(
        array(
          '=' => array($user1->getPHID() => $user1->getPHID()),
        ));

    $this->applyTransactions($project, $user1, $xactions);

    $this->assertTrue((bool)$this->refreshProject($project, $user1));
    $this->assertFalse((bool)$this->refreshProject($project, $user2));

    $this->leaveProject($project, $user1);

    $this->assertFalse((bool)$this->refreshProject($project, $user1));
  }

  public function testParentProject() {
    $user = $this->createUser();
    $user->save();

    $parent = $this->createProject($user);
    $child = $this->createProject($user, $parent);

    $this->assertTrue(true);

    $child = $this->refreshProject($child, $user);

    $this->assertEqual(
      $parent->getPHID(),
      $child->getParentProject()->getPHID());

    $this->assertEqual(1, (int)$child->getProjectDepth());

    $this->assertFalse(
      $child->isUserMember($user->getPHID()));

    $this->assertFalse(
      $child->getParentProject()->isUserMember($user->getPHID()));

    $this->joinProject($child, $user);

    $child = $this->refreshProject($child, $user);

    $this->assertTrue(
      $child->isUserMember($user->getPHID()));

    $this->assertTrue(
      $child->getParentProject()->isUserMember($user->getPHID()));


    // Test that hiding a parent hides the child.

    $user2 = $this->createUser();
    $user2->save();

    // Second user can see the project for now.
    $this->assertTrue((bool)$this->refreshProject($child, $user2));

    // Hide the parent.
    $this->setViewPolicy($parent, $user, $user->getPHID());

    // First user (who can see the parent because they are a member of
    // the child) can see the project.
    $this->assertTrue((bool)$this->refreshProject($child, $user));

    // Second user can not, because they can't see the parent.
    $this->assertFalse((bool)$this->refreshProject($child, $user2));
  }

  public function testSlugMaps() {
    // When querying by slugs, slugs should be normalized and the mapping
    // should be reported correctly.
    $user = $this->createUser();
    $user->save();

    $name = 'queryslugproject';
    $name2 = 'QUERYslugPROJECT';
    $slug = 'queryslugextra';
    $slug2 = 'QuErYSlUgExTrA';

    $project = PhabricatorProject::initializeNewProject($user);

    $xactions = array();

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorProjectNameTransaction::TRANSACTIONTYPE)
      ->setNewValue($name);

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorProjectSlugsTransaction::TRANSACTIONTYPE)
      ->setNewValue(array($slug));

    $this->applyTransactions($project, $user, $xactions);

    $project_query = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withSlugs(array($name));
    $project_query->execute();
    $map = $project_query->getSlugMap();

    $this->assertEqual(
      array(
        $name => $project->getPHID(),
      ),
      ipull($map, 'projectPHID'));

    $project_query = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withSlugs(array($slug));
    $project_query->execute();
    $map = $project_query->getSlugMap();

    $this->assertEqual(
      array(
        $slug => $project->getPHID(),
      ),
      ipull($map, 'projectPHID'));

    $project_query = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withSlugs(array($name, $slug, $name2, $slug2));
    $project_query->execute();
    $map = $project_query->getSlugMap();

    $expect = array(
      $name => $project->getPHID(),
      $slug => $project->getPHID(),
      $name2 => $project->getPHID(),
      $slug2 => $project->getPHID(),
    );

    $actual = ipull($map, 'projectPHID');

    ksort($expect);
    ksort($actual);

    $this->assertEqual($expect, $actual);

    $expect = array(
      $name => $name,
      $slug => $slug,
      $name2 => $name,
      $slug2 => $slug,
    );

    $actual = ipull($map, 'slug');

    ksort($expect);
    ksort($actual);

    $this->assertEqual($expect, $actual);
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


  public function testComplexConstraints() {
    $user = $this->createUser();
    $user->save();

    $engineering = $this->createProject($user);
    $engineering_scan = $this->createProject($user, $engineering);
    $engineering_warp = $this->createProject($user, $engineering);

    $exploration = $this->createProject($user);
    $exploration_diplomacy = $this->createProject($user, $exploration);

    $task_engineering = $this->newTask(
      $user,
      array($engineering),
      pht('Engineering Only'));

    $task_exploration = $this->newTask(
      $user,
      array($exploration),
      pht('Exploration Only'));

    $task_warp_explore = $this->newTask(
      $user,
      array($engineering_warp, $exploration),
      pht('Warp to New Planet'));

    $task_diplomacy_scan = $this->newTask(
      $user,
      array($engineering_scan, $exploration_diplomacy),
      pht('Scan Diplomat'));

    $task_diplomacy = $this->newTask(
      $user,
      array($exploration_diplomacy),
      pht('Diplomatic Meeting'));

    $task_warp_scan = $this->newTask(
      $user,
      array($engineering_scan, $engineering_warp),
      pht('Scan Warp Drives'));

    $this->assertQueryByProjects(
      $user,
      array(
        $task_engineering,
        $task_warp_explore,
        $task_diplomacy_scan,
        $task_warp_scan,
      ),
      array($engineering),
      pht('All Engineering'));

    $this->assertQueryByProjects(
      $user,
      array(
        $task_diplomacy_scan,
        $task_warp_scan,
      ),
      array($engineering_scan),
      pht('All Scan'));

    $this->assertQueryByProjects(
      $user,
      array(
        $task_warp_explore,
        $task_diplomacy_scan,
      ),
      array($engineering, $exploration),
      pht('Engineering + Exploration'));

    // This is testing that a query for "Parent" and "Parent > Child" works
    // properly.
    $this->assertQueryByProjects(
      $user,
      array(
        $task_diplomacy_scan,
        $task_warp_scan,
      ),
      array($engineering, $engineering_scan),
      pht('Engineering + Scan'));
  }

  public function testTagAncestryConflicts() {
    $user = $this->createUser();
    $user->save();

    $stonework = $this->createProject($user);
    $stonework_masonry = $this->createProject($user, $stonework);
    $stonework_sculpting = $this->createProject($user, $stonework);

    $task = $this->newTask($user, array());
    $this->assertEqual(array(), $this->getTaskProjects($task));

    $this->addProjectTags($user, $task, array($stonework->getPHID()));
    $this->assertEqual(
      array(
        $stonework->getPHID(),
      ),
      $this->getTaskProjects($task));

    // Adding a descendant should remove the parent.
    $this->addProjectTags($user, $task, array($stonework_masonry->getPHID()));
    $this->assertEqual(
      array(
        $stonework_masonry->getPHID(),
      ),
      $this->getTaskProjects($task));

    // Adding an ancestor should remove the descendant.
    $this->addProjectTags($user, $task, array($stonework->getPHID()));
    $this->assertEqual(
      array(
        $stonework->getPHID(),
      ),
      $this->getTaskProjects($task));

    // Adding two tags in the same hierarchy which are not mutual ancestors
    // should remove the ancestor but otherwise work fine.
    $this->addProjectTags(
      $user,
      $task,
      array(
        $stonework_masonry->getPHID(),
        $stonework_sculpting->getPHID(),
      ));

    $expect = array(
      $stonework_masonry->getPHID(),
      $stonework_sculpting->getPHID(),
    );
    sort($expect);

    $this->assertEqual($expect,  $this->getTaskProjects($task));
  }

  public function testTagMilestoneConflicts() {
    $user = $this->createUser();
    $user->save();

    $stonework = $this->createProject($user);
    $stonework_1 = $this->createProject($user, $stonework, true);
    $stonework_2 = $this->createProject($user, $stonework, true);

    $task = $this->newTask($user, array());
    $this->assertEqual(array(), $this->getTaskProjects($task));

    $this->addProjectTags($user, $task, array($stonework->getPHID()));
    $this->assertEqual(
      array(
        $stonework->getPHID(),
      ),
      $this->getTaskProjects($task));

    // Adding a milesone should remove the parent.
    $this->addProjectTags($user, $task, array($stonework_1->getPHID()));
    $this->assertEqual(
      array(
        $stonework_1->getPHID(),
      ),
      $this->getTaskProjects($task));

    // Adding the parent should remove the milestone.
    $this->addProjectTags($user, $task, array($stonework->getPHID()));
    $this->assertEqual(
      array(
        $stonework->getPHID(),
      ),
      $this->getTaskProjects($task));

    // First, add one milestone.
    $this->addProjectTags($user, $task, array($stonework_1->getPHID()));
    // Now, adding a second milestone should remove the first milestone.
    $this->addProjectTags($user, $task, array($stonework_2->getPHID()));
    $this->assertEqual(
      array(
        $stonework_2->getPHID(),
      ),
      $this->getTaskProjects($task));
  }

  public function testBoardMoves() {
    $user = $this->createUser();
    $user->save();

    $board = $this->createProject($user);

    $backlog = $this->addColumn($user, $board, 0);
    $column = $this->addColumn($user, $board, 1);

    // New tasks should appear in the backlog.
    $task1 = $this->newTask($user, array($board));
    $expect = array(
      $backlog->getPHID(),
    );
    $this->assertColumns($expect, $user, $board, $task1);

    // Moving a task should move it to the destination column.
    $this->moveToColumn($user, $board, $task1, $backlog, $column);
    $expect = array(
      $column->getPHID(),
    );
    $this->assertColumns($expect, $user, $board, $task1);

    // Same thing again, with a new task.
    $task2 = $this->newTask($user, array($board));
    $expect = array(
      $backlog->getPHID(),
    );
    $this->assertColumns($expect, $user, $board, $task2);

    // Move it, too.
    $this->moveToColumn($user, $board, $task2, $backlog, $column);
    $expect = array(
      $column->getPHID(),
    );
    $this->assertColumns($expect, $user, $board, $task2);

    // Now the stuff should be in the column, in order, with the more recently
    // moved task on top.
    $expect = array(
      $task2->getPHID(),
      $task1->getPHID(),
    );
    $label = pht('Simple move');
    $this->assertTasksInColumn($expect, $user, $board, $column, $label);

    // Move the second task after the first task.
    $options = array(
      'afterPHIDs' => array($task1->getPHID()),
    );
    $this->moveToColumn($user, $board, $task2, $column, $column, $options);
    $expect = array(
      $task1->getPHID(),
      $task2->getPHID(),
    );
    $label = pht('With afterPHIDs');
    $this->assertTasksInColumn($expect, $user, $board, $column, $label);

    // Move the second task before the first task.
    $options = array(
      'beforePHIDs' => array($task1->getPHID()),
    );
    $this->moveToColumn($user, $board, $task2, $column, $column, $options);
    $expect = array(
      $task2->getPHID(),
      $task1->getPHID(),
    );
    $label = pht('With beforePHIDs');
    $this->assertTasksInColumn($expect, $user, $board, $column, $label);
  }

  public function testMilestoneMoves() {
    $user = $this->createUser();
    $user->save();

    $board = $this->createProject($user);

    $backlog = $this->addColumn($user, $board, 0);

    // Create a task into the backlog.
    $task = $this->newTask($user, array($board));
    $expect = array(
      $backlog->getPHID(),
    );
    $this->assertColumns($expect, $user, $board, $task);

    $milestone = $this->createProject($user, $board, true);

    $this->addProjectTags($user, $task, array($milestone->getPHID()));

    // We just want the side effect of looking at the board: creation of the
    // milestone column.
    $this->loadColumns($user, $board, $task);

    $column = id(new PhabricatorProjectColumnQuery())
      ->setViewer($user)
      ->withProjectPHIDs(array($board->getPHID()))
      ->withProxyPHIDs(array($milestone->getPHID()))
      ->executeOne();

    $this->assertTrue((bool)$column);

    // Moving the task to the milestone should have moved it to the milestone
    // column.
    $expect = array(
      $column->getPHID(),
    );
    $this->assertColumns($expect, $user, $board, $task);


    // Move the task within the "Milestone" column. This should not affect
    // the projects the task is tagged with. See T10912.
    $task_a = $task;

    $task_b = $this->newTask($user, array($backlog));
    $this->moveToColumn($user, $board, $task_b, $backlog, $column);

    $a_options = array(
      'beforePHID' => $task_b->getPHID(),
    );

    $b_options = array(
      'beforePHID' => $task_a->getPHID(),
    );

    $old_projects = $this->getTaskProjects($task);

    // Move the target task to the top.
    $this->moveToColumn($user, $board, $task_a, $column, $column, $a_options);
    $new_projects = $this->getTaskProjects($task_a);
    $this->assertEqual($old_projects, $new_projects);

    // Move the other task.
    $this->moveToColumn($user, $board, $task_b, $column, $column, $b_options);
    $new_projects = $this->getTaskProjects($task_a);
    $this->assertEqual($old_projects, $new_projects);

    // Move the target task again.
    $this->moveToColumn($user, $board, $task_a, $column, $column, $a_options);
    $new_projects = $this->getTaskProjects($task_a);
    $this->assertEqual($old_projects, $new_projects);


    // Add the parent project to the task. This should move it out of the
    // milestone column and into the parent's backlog.
    $this->addProjectTags($user, $task, array($board->getPHID()));
    $expect_columns = array(
      $backlog->getPHID(),
    );
    $this->assertColumns($expect_columns, $user, $board, $task);

    $new_projects = $this->getTaskProjects($task);
    $expect_projects = array(
      $board->getPHID(),
    );
    $this->assertEqual($expect_projects, $new_projects);
  }

  public function testColumnExtendedPolicies() {
    $user = $this->createUser();
    $user->save();

    $board = $this->createProject($user);
    $column = $this->addColumn($user, $board, 0);

    // At first, the user should be able to view and edit the column.
    $column = $this->refreshColumn($user, $column);
    $this->assertTrue((bool)$column);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $column,
      PhabricatorPolicyCapability::CAN_EDIT);
    $this->assertTrue($can_edit);

    // Now, set the project edit policy to "Members of Project". This should
    // disable editing.
    $members_policy = id(new PhabricatorProjectMembersPolicyRule())
      ->getObjectPolicyFullKey();
    $board->setEditPolicy($members_policy)->save();

    $column = $this->refreshColumn($user, $column);
    $this->assertTrue((bool)$column);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $column,
      PhabricatorPolicyCapability::CAN_EDIT);
    $this->assertFalse($can_edit);

    // Now, join the project. This should make the column editable again.
    $this->joinProject($board, $user);

    $column = $this->refreshColumn($user, $column);
    $this->assertTrue((bool)$column);

    // This test has been failing randomly in a way that doesn't reproduce
    // on any host, so add some extra assertions to try to nail it down.
    $board = $this->refreshProject($board, $user, true);
    $this->assertTrue((bool)$board);
    $this->assertTrue($board->isUserMember($user->getPHID()));

    $can_view = PhabricatorPolicyFilter::hasCapability(
      $user,
      $column,
      PhabricatorPolicyCapability::CAN_VIEW);
    $this->assertTrue($can_view);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $column,
      PhabricatorPolicyCapability::CAN_EDIT);
    $this->assertTrue($can_edit);
  }

  public function testProjectPolicyRules() {
    $author = $this->generateNewTestUser();

    $proj_a = PhabricatorProject::initializeNewProject($author)
      ->setName('Policy A')
      ->save();
    $proj_b = PhabricatorProject::initializeNewProject($author)
      ->setName('Policy B')
      ->save();

    $user_none = $this->generateNewTestUser();
    $user_any = $this->generateNewTestUser();
    $user_all = $this->generateNewTestUser();

    $this->joinProject($proj_a, $user_any);
    $this->joinProject($proj_a, $user_all);
    $this->joinProject($proj_b, $user_all);

    $any_policy = id(new PhabricatorPolicy())
      ->setRules(
        array(
          array(
            'action' => PhabricatorPolicy::ACTION_ALLOW,
            'rule' => 'PhabricatorProjectsPolicyRule',
            'value' => array(
              $proj_a->getPHID(),
              $proj_b->getPHID(),
            ),
          ),
        ))
      ->save();

    $all_policy = id(new PhabricatorPolicy())
      ->setRules(
        array(
          array(
            'action' => PhabricatorPolicy::ACTION_ALLOW,
            'rule' => 'PhabricatorProjectsAllPolicyRule',
            'value' => array(
              $proj_a->getPHID(),
              $proj_b->getPHID(),
            ),
          ),
        ))
      ->save();

    $any_task = ManiphestTask::initializeNewTask($author)
      ->setViewPolicy($any_policy->getPHID())
      ->save();

    $all_task = ManiphestTask::initializeNewTask($author)
      ->setViewPolicy($all_policy->getPHID())
      ->save();

    $map = array(
      array(
        pht('Project policy rule; user in no projects'),
        $user_none,
        false,
        false,
      ),
      array(
        pht('Project policy rule; user in some projects'),
        $user_any,
        true,
        false,
      ),
      array(
        pht('Project policy rule; user in all projects'),
        $user_all,
        true,
        true,
      ),
    );

    foreach ($map as $test_case) {
      list($label, $user, $expect_any, $expect_all) = $test_case;

      $can_any = PhabricatorPolicyFilter::hasCapability(
        $user,
        $any_task,
        PhabricatorPolicyCapability::CAN_VIEW);

      $can_all = PhabricatorPolicyFilter::hasCapability(
        $user,
        $all_task,
        PhabricatorPolicyCapability::CAN_VIEW);

      $this->assertEqual($expect_any, $can_any, pht('%s / Any', $label));
      $this->assertEqual($expect_all, $can_all, pht('%s / All', $label));
    }
  }


  private function moveToColumn(
    PhabricatorUser $viewer,
    PhabricatorProject $board,
    ManiphestTask $task,
    PhabricatorProjectColumn $src,
    PhabricatorProjectColumn $dst,
    $options = null) {

    $xactions = array();

    if (!$options) {
      $options = array();
    }

    $value = array(
      'columnPHID' => $dst->getPHID(),
    ) + $options;

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COLUMNS)
      ->setNewValue(array($value));

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
      ->setContentSource($this->newContentSource())
      ->setContinueOnNoEffect(true)
      ->applyTransactions($task, $xactions);
  }

  private function assertColumns(
    array $expect,
    PhabricatorUser $viewer,
    PhabricatorProject $board,
    ManiphestTask $task) {
    $column_phids = $this->loadColumns($viewer, $board, $task);
    $this->assertEqual($expect, $column_phids);
  }

  private function loadColumns(
    PhabricatorUser $viewer,
    PhabricatorProject $board,
    ManiphestTask $task) {
    $engine = id(new PhabricatorBoardLayoutEngine())
      ->setViewer($viewer)
      ->setBoardPHIDs(array($board->getPHID()))
      ->setObjectPHIDs(
        array(
          $task->getPHID(),
        ))
      ->executeLayout();

    $columns = $engine->getObjectColumns($board->getPHID(), $task->getPHID());
    $column_phids = mpull($columns, 'getPHID');
    $column_phids = array_values($column_phids);

    return $column_phids;
  }

  private function assertTasksInColumn(
    array $expect,
    PhabricatorUser $viewer,
    PhabricatorProject $board,
    PhabricatorProjectColumn $column,
    $label = null) {

    $engine = id(new PhabricatorBoardLayoutEngine())
      ->setViewer($viewer)
      ->setBoardPHIDs(array($board->getPHID()))
      ->setObjectPHIDs($expect)
      ->executeLayout();

    $object_phids = $engine->getColumnObjectPHIDs(
      $board->getPHID(),
      $column->getPHID());
    $object_phids = array_values($object_phids);

    $this->assertEqual($expect, $object_phids, $label);
  }

  private function addColumn(
    PhabricatorUser $viewer,
    PhabricatorProject $project,
    $sequence) {

    $project->setHasWorkboard(1)->save();

    return PhabricatorProjectColumn::initializeNewColumn($viewer)
      ->setSequence(0)
      ->setProperty('isDefault', ($sequence == 0))
      ->setProjectPHID($project->getPHID())
      ->save();
  }

  private function getTaskProjects(ManiphestTask $task) {
    $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $task->getPHID(),
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);

    sort($project_phids);

    return $project_phids;
  }

  private function attemptProjectEdit(
    PhabricatorProject $proj,
    PhabricatorUser $user,
    $skip_refresh = false) {

    $proj = $this->refreshProject($proj, $user, true);

    $new_name = $proj->getName().' '.mt_rand();

    $params = array(
      'objectIdentifier' => $proj->getID(),
      'transactions' => array(
        array(
          'type' => 'name',
          'value' => $new_name,
        ),
      ),
    );

    id(new ConduitCall('project.edit', $params))
      ->setUser($user)
      ->execute();

    return true;
  }


  private function addProjectTags(
    PhabricatorUser $viewer,
    ManiphestTask $task,
    array $phids) {

    $xactions = array();

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue(
        'edge:type',
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST)
      ->setNewValue(
        array(
          '+' => array_fuse($phids),
        ));

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
      ->setContentSource($this->newContentSource())
      ->setContinueOnNoEffect(true)
      ->applyTransactions($task, $xactions);
  }

  private function newTask(
    PhabricatorUser $viewer,
    array $projects,
    $name = null) {

    $task = ManiphestTask::initializeNewTask($viewer);

    if ($name === null || $name === '') {
      $name = pht('Test Task');
    }

    $xactions = array();

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTaskTitleTransaction::TRANSACTIONTYPE)
      ->setNewValue($name);

    if ($projects) {
      $xactions[] = id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue(
          'edge:type',
          PhabricatorProjectObjectHasProjectEdgeType::EDGECONST)
        ->setNewValue(
          array(
            '=' => array_fuse(mpull($projects, 'getPHID')),
          ));
    }

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
      ->setContentSource($this->newContentSource())
      ->setContinueOnNoEffect(true)
      ->applyTransactions($task, $xactions);

    return $task;
  }

  private function assertQueryByProjects(
    PhabricatorUser $viewer,
    array $expect,
    array $projects,
    $label = null) {

    $datasource = id(new PhabricatorProjectLogicalDatasource())
      ->setViewer($viewer);

    $project_phids = mpull($projects, 'getPHID');
    $constraints = $datasource->evaluateTokens($project_phids);

    $query = id(new ManiphestTaskQuery())
      ->setViewer($viewer);

    $query->withEdgeLogicConstraints(
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
      $constraints);

    $tasks = $query->execute();

    $expect_phids = mpull($expect, 'getTitle', 'getPHID');
    ksort($expect_phids);

    $actual_phids = mpull($tasks, 'getTitle', 'getPHID');
    ksort($actual_phids);

    $this->assertEqual($expect_phids, $actual_phids, $label);
  }

  private function refreshProject(
    PhabricatorProject $project,
    PhabricatorUser $viewer,
    $need_members = false,
    $need_watchers = false) {

    $results = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->needMembers($need_members)
      ->needWatchers($need_watchers)
      ->withIDs(array($project->getID()))
      ->execute();

    if ($results) {
      return head($results);
    } else {
      return null;
    }
  }

  private function refreshColumn(
    PhabricatorUser $viewer,
    PhabricatorProjectColumn $column) {

    $results = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withIDs(array($column->getID()))
      ->execute();

    if ($results) {
      return head($results);
    } else {
      return null;
    }
  }

  private function createProject(
    PhabricatorUser $user,
    PhabricatorProject $parent = null,
    $is_milestone = false) {

    $project = PhabricatorProject::initializeNewProject($user, $parent);

    $name = pht('Test Project %d', mt_rand());

    $xactions = array();

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorProjectNameTransaction::TRANSACTIONTYPE)
      ->setNewValue($name);

    if ($parent) {
      if ($is_milestone) {
        $xactions[] = id(new PhabricatorProjectTransaction())
          ->setTransactionType(
              PhabricatorProjectMilestoneTransaction::TRANSACTIONTYPE)
          ->setNewValue($parent->getPHID());
      } else {
        $xactions[] = id(new PhabricatorProjectTransaction())
          ->setTransactionType(
              PhabricatorProjectParentTransaction::TRANSACTIONTYPE)
          ->setNewValue($parent->getPHID());
      }
    }

    $this->applyTransactions($project, $user, $xactions);

    // Force these values immediately; they are normally updated by the
    // index engine.
    if ($parent) {
      if ($is_milestone) {
        $parent->setHasMilestones(1)->save();
      } else {
        $parent->setHasSubprojects(1)->save();
      }
    }

    return $project;
  }

  private function setViewPolicy(
    PhabricatorProject $project,
    PhabricatorUser $user,
    $policy) {

    $xactions = array();

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
      ->setNewValue($policy);

    $this->applyTransactions($project, $user, $xactions);

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
    $user->setRealName(pht('Unit Test User %d', $rand));

    return $user;
  }

  private function joinProject(
    PhabricatorProject $project,
    PhabricatorUser $user) {
    return $this->joinOrLeaveProject($project, $user, '+');
  }

  private function leaveProject(
    PhabricatorProject $project,
    PhabricatorUser $user) {
    return $this->joinOrLeaveProject($project, $user, '-');
  }

  private function watchProject(
    PhabricatorProject $project,
    PhabricatorUser $user) {
    return $this->watchOrUnwatchProject($project, $user, '+');
  }

  private function unwatchProject(
    PhabricatorProject $project,
    PhabricatorUser $user) {
    return $this->watchOrUnwatchProject($project, $user, '-');
  }

  private function joinOrLeaveProject(
    PhabricatorProject $project,
    PhabricatorUser $user,
    $operation) {
    return $this->applyProjectEdgeTransaction(
      $project,
      $user,
      $operation,
      PhabricatorProjectProjectHasMemberEdgeType::EDGECONST);
  }

  private function watchOrUnwatchProject(
    PhabricatorProject $project,
    PhabricatorUser $user,
    $operation) {
    return $this->applyProjectEdgeTransaction(
      $project,
      $user,
      $operation,
      PhabricatorObjectHasWatcherEdgeType::EDGECONST);
  }

  private function applyProjectEdgeTransaction(
    PhabricatorProject $project,
    PhabricatorUser $user,
    $operation,
    $edge_type) {

    $spec = array(
      $operation => array($user->getPHID() => $user->getPHID()),
    );

    $xactions = array();
    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $edge_type)
      ->setNewValue($spec);

    $this->applyTransactions($project, $user, $xactions);

    return $project;
  }

  private function applyTransactions(
    PhabricatorProject $project,
    PhabricatorUser $user,
    array $xactions) {

    $editor = id(new PhabricatorProjectTransactionEditor())
      ->setActor($user)
      ->setContentSource($this->newContentSource())
      ->setContinueOnNoEffect(true)
      ->applyTransactions($project, $xactions);
  }


}
