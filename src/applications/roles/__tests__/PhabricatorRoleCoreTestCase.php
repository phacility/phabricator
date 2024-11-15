<?php

final class PhabricatorRoleCoreTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testViewRole() {
    $user = $this->createUser();
    $user->save();

    $user2 = $this->createUser();
    $user2->save();

    $role = $this->createRole($user);

    $role = $this->refreshRole($role, $user, true);

    $this->joinRole($role, $user);
    $role->setViewPolicy(PhabricatorPolicies::POLICY_USER);
    $role->save();

    $can_view = PhabricatorPolicyCapability::CAN_VIEW;

    // When the view policy is set to "users", any user can see the role.
    $this->assertTrue((bool)$this->refreshRole($role, $user));
    $this->assertTrue((bool)$this->refreshRole($role, $user2));


    // When the view policy is set to "no one", members can still see the
    // role.
    $role->setViewPolicy(PhabricatorPolicies::POLICY_NOONE);
    $role->save();

    $this->assertTrue((bool)$this->refreshRole($role, $user));
    $this->assertFalse((bool)$this->refreshRole($role, $user2));
  }

  public function testApplicationPolicy() {
    $user = $this->createUser()
      ->save();

    $role = $this->createRole($user);

    $this->assertTrue(
      PhabricatorPolicyFilter::hasCapability(
        $user,
        $role,
        PhabricatorPolicyCapability::CAN_VIEW));

    // This object is visible so its handle should load normally.
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($role->getPHID()))
      ->executeOne();
    $this->assertEqual($role->getPHID(), $handle->getPHID());

    // Change the "Can Use Application" policy for roles to "No One". This
    // should cause filtering checks to fail even when they are executed
    // directly rather than via a Query.
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig(
      'phabricator.application-settings',
      array(
        'PHID-APPS-PhabricatorRoleApplication' => array(
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
        $role,
        PhabricatorPolicyCapability::CAN_VIEW));

    // We should still be able to load a handle for the role, even if we
    // can not see the application.
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($role->getPHID()))
      ->executeOne();

    // The handle should load...
    $this->assertEqual($role->getPHID(), $handle->getPHID());

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

    $role1 = $this->createRole($user1);
    $role1 = $this->refreshRole($role1, $user1);

    $this->joinRole($role1, $user1);
    $this->joinRole($role1, $user3);
    $this->watchRole($role1, $user3);

    $role1 = $this->refreshRole($role1, $user1);

    $this->assertTrue($role1->isUserMember($user1->getPHID()));

    $role1 = $this->refreshRole($role1, $user1, false, true);

    $this->assertTrue($role1->isUserMember($user1->getPHID()));
    $this->assertFalse($role1->isUserWatcher($user1->getPHID()));

    $role1 = $this->refreshRole($role1, $user1, true, false);

    $this->assertTrue($role1->isUserMember($user1->getPHID()));
    $this->assertFalse($role1->isUserMember($user2->getPHID()));
    $this->assertTrue($role1->isUserMember($user3->getPHID()));

    $role1 = $this->refreshRole($role1, $user1, true, true);

    $this->assertTrue($role1->isUserMember($user1->getPHID()));
    $this->assertFalse($role1->isUserMember($user2->getPHID()));
    $this->assertTrue($role1->isUserMember($user3->getPHID()));

    $this->assertFalse($role1->isUserWatcher($user1->getPHID()));
    $this->assertFalse($role1->isUserWatcher($user2->getPHID()));
    $this->assertTrue($role1->isUserWatcher($user3->getPHID()));
  }

  public function testEditRole() {
    $user = $this->createUser();
    $user->save();

    $user->setAllowInlineCacheGeneration(true);

    $role = $this->createRole($user);


    // When edit and view policies are set to "user", anyone can edit.
    $role->setViewPolicy(PhabricatorPolicies::POLICY_USER);
    $role->setEditPolicy(PhabricatorPolicies::POLICY_USER);
    $role->save();

    $this->assertTrue($this->attemptRoleEdit($role, $user));


    // When edit policy is set to "no one", no one can edit.
    $role->setEditPolicy(PhabricatorPolicies::POLICY_NOONE);
    $role->save();

    $caught = null;
    try {
      $this->attemptRoleEdit($role, $user);
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

    $parent = $this->createRole($user1);
    $child = $this->createRole($user1, $parent);

    $this->joinRole($child, $user1);
    $this->joinRole($child, $user2);

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($user1)
      ->withPHIDs(array($child->getPHID()))
      ->needAncestorMembers(true)
      ->executeOne();

    $members = array_fuse($role->getParentRole()->getMemberPHIDs());
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

    $ancestor = $this->createRole($user);
    $parent = $this->createRole($user, $ancestor);
    $child = $this->createRole($user, $parent);

    $roles = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withAncestorRolePHIDs(array($ancestor->getPHID()))
      ->execute();
    $this->assertEqual(2, count($roles));

    $roles = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withParentRolePHIDs(array($ancestor->getPHID()))
      ->execute();
    $this->assertEqual(1, count($roles));
    $this->assertEqual(
      $parent->getPHID(),
      head($roles)->getPHID());

    $roles = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withAncestorRolePHIDs(array($ancestor->getPHID()))
      ->withDepthBetween(2, null)
      ->execute();
    $this->assertEqual(1, count($roles));
    $this->assertEqual(
      $child->getPHID(),
      head($roles)->getPHID());

    $parent2 = $this->createRole($user, $ancestor);
    $child2 = $this->createRole($user, $parent2);
    $grandchild2 = $this->createRole($user, $child2);

    $roles = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withAncestorRolePHIDs(array($ancestor->getPHID()))
      ->execute();
    $this->assertEqual(5, count($roles));

    $roles = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withParentRolePHIDs(array($ancestor->getPHID()))
      ->execute();
    $this->assertEqual(2, count($roles));

    $roles = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withAncestorRolePHIDs(array($ancestor->getPHID()))
      ->withDepthBetween(2, null)
      ->execute();
    $this->assertEqual(3, count($roles));

    $roles = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withAncestorRolePHIDs(array($ancestor->getPHID()))
      ->withDepthBetween(3, null)
      ->execute();
    $this->assertEqual(1, count($roles));

    $roles = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withPHIDs(
        array(
          $child->getPHID(),
          $grandchild2->getPHID(),
        ))
      ->execute();
    $this->assertEqual(2, count($roles));
  }

  public function testMemberMaterialization() {
    $material_type = PhabricatorRoleMaterializedMemberEdgeType::EDGECONST;

    $user = $this->createUser();
    $user->save();

    $parent = $this->createRole($user);
    $child = $this->createRole($user, $parent);

    $this->joinRole($child, $user);

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

    $parent = $this->createRole($user);

    $m1 = $this->createRole($user, $parent, true);
    $m2 = $this->createRole($user, $parent, true);
    $m3 = $this->createRole($user, $parent, true);

    $this->assertEqual(1, $m1->getMilestoneNumber());
    $this->assertEqual(2, $m2->getMilestoneNumber());
    $this->assertEqual(3, $m3->getMilestoneNumber());
  }

  public function testMilestoneMembership() {
    $user = $this->createUser();
    $user->save();

    $parent = $this->createRole($user);
    $milestone = $this->createRole($user, $parent, true);

    $this->joinRole($parent, $user);

    $milestone = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withPHIDs(array($milestone->getPHID()))
      ->executeOne();

    $this->assertTrue($milestone->isUserMember($user->getPHID()));

    $milestone = id(new PhabricatorRoleQuery())
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
    // or renaming the role.

    $user = $this->createUser();
    $user->save();

    $role = $this->createRole($user);

    // In this first case, set the name and slugs at the same time.
    $name = 'slugrole';

    $xactions = array();
    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorRoleNameTransaction::TRANSACTIONTYPE)
      ->setNewValue($name);
    $this->applyTransactions($role, $user, $xactions);

    $xactions = array();
    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorRoleSlugsTransaction::TRANSACTIONTYPE)
      ->setNewValue(array($name));
    $this->applyTransactions($role, $user, $xactions);

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withPHIDs(array($role->getPHID()))
      ->needSlugs(true)
      ->executeOne();

    $slugs = $role->getSlugs();
    $slugs = mpull($slugs, 'getSlug');

    $this->assertTrue(in_array($name, $slugs));

    // In this second case, set the name first and then the slugs separately.
    $name2 = 'slugrole2';
    $xactions = array();

    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorRoleNameTransaction::TRANSACTIONTYPE)
      ->setNewValue($name2);

    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorRoleSlugsTransaction::TRANSACTIONTYPE)
      ->setNewValue(array($name2));

    $this->applyTransactions($role, $user, $xactions);

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withPHIDs(array($role->getPHID()))
      ->needSlugs(true)
      ->executeOne();

    $slugs = $role->getSlugs();
    $slugs = mpull($slugs, 'getSlug');

    $this->assertTrue(in_array($name2, $slugs));
  }

  public function testDuplicateSlugs() {
    // Creating a role with multiple duplicate slugs should succeed.

    $user = $this->createUser();
    $user->save();

    $role = $this->createRole($user);

    $input = 'duplicate';

    $xactions = array();

    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorRoleSlugsTransaction::TRANSACTIONTYPE)
      ->setNewValue(array($input, $input));

    $this->applyTransactions($role, $user, $xactions);

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withPHIDs(array($role->getPHID()))
      ->needSlugs(true)
      ->executeOne();

    $slugs = $role->getSlugs();
    $slugs = mpull($slugs, 'getSlug');

    $this->assertTrue(in_array($input, $slugs));
  }

  public function testNormalizeSlugs() {
    // When a user creates a role with slug "XxX360n0sc0perXxX", normalize
    // it before writing it.

    $user = $this->createUser();
    $user->save();

    $role = $this->createRole($user);

    $input = 'NoRmAlIzE';
    $expect = 'normalize';

    $xactions = array();

    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorRoleSlugsTransaction::TRANSACTIONTYPE)
      ->setNewValue(array($input));

    $this->applyTransactions($role, $user, $xactions);

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withPHIDs(array($role->getPHID()))
      ->needSlugs(true)
      ->executeOne();

    $slugs = $role->getSlugs();
    $slugs = mpull($slugs, 'getSlug');

    $this->assertTrue(in_array($expect, $slugs));


    // If another user tries to add the same slug in denormalized form, it
    // should be caught and fail, even though the database version of the slug
    // is normalized.

    $role2 = $this->createRole($user);

    $xactions = array();

    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorRoleSlugsTransaction::TRANSACTIONTYPE)
      ->setNewValue(array($input));

    $caught = null;
    try {
      $this->applyTransactions($role2, $user, $xactions);
    } catch (PhabricatorApplicationTransactionValidationException $ex) {
      $caught = $ex;
    }

    $this->assertTrue((bool)$caught);
  }

  public function testRoleMembersVisibility() {
    // This is primarily testing that you can create a role and set the
    // visibility or edit policy to "Role Members" immediately.

    $user1 = $this->createUser();
    $user1->save();

    $user2 = $this->createUser();
    $user2->save();

    $role = PhabricatorRole::initializeNewRole($user1);
    $name = pht('Test Role %d', mt_rand());

    $xactions = array();

    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorRoleNameTransaction::TRANSACTIONTYPE)
      ->setNewValue($name);

    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
      ->setNewValue(
        id(new PhabricatorRoleMembersPolicyRule())
          ->getObjectPolicyFullKey());

    $edge_type = PhabricatorRoleRoleHasMemberEdgeType::EDGECONST;

    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $edge_type)
      ->setNewValue(
        array(
          '=' => array($user1->getPHID() => $user1->getPHID()),
        ));

    $this->applyTransactions($role, $user1, $xactions);

    $this->assertTrue((bool)$this->refreshRole($role, $user1));
    $this->assertFalse((bool)$this->refreshRole($role, $user2));

    $this->leaveRole($role, $user1);

    $this->assertFalse((bool)$this->refreshRole($role, $user1));
  }

  public function testParentRole() {
    $user = $this->createUser();
    $user->save();

    $parent = $this->createRole($user);
    $child = $this->createRole($user, $parent);

    $this->assertTrue(true);

    $child = $this->refreshRole($child, $user);

    $this->assertEqual(
      $parent->getPHID(),
      $child->getParentRole()->getPHID());

    $this->assertEqual(1, (int)$child->getRoleDepth());

    $this->assertFalse(
      $child->isUserMember($user->getPHID()));

    $this->assertFalse(
      $child->getParentRole()->isUserMember($user->getPHID()));

    $this->joinRole($child, $user);

    $child = $this->refreshRole($child, $user);

    $this->assertTrue(
      $child->isUserMember($user->getPHID()));

    $this->assertTrue(
      $child->getParentRole()->isUserMember($user->getPHID()));


    // Test that hiding a parent hides the child.

    $user2 = $this->createUser();
    $user2->save();

    // Second user can see the role for now.
    $this->assertTrue((bool)$this->refreshRole($child, $user2));

    // Hide the parent.
    $this->setViewPolicy($parent, $user, $user->getPHID());

    // First user (who can see the parent because they are a member of
    // the child) can see the role.
    $this->assertTrue((bool)$this->refreshRole($child, $user));

    // Second user can not, because they can't see the parent.
    $this->assertFalse((bool)$this->refreshRole($child, $user2));
  }

  public function testSlugMaps() {
    // When querying by slugs, slugs should be normalized and the mapping
    // should be reported correctly.
    $user = $this->createUser();
    $user->save();

    $name = 'queryslugrole';
    $name2 = 'QUERYslugROLE';
    $slug = 'queryslugextra';
    $slug2 = 'QuErYSlUgExTrA';

    $role = PhabricatorRole::initializeNewRole($user);

    $xactions = array();

    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorRoleNameTransaction::TRANSACTIONTYPE)
      ->setNewValue($name);

    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorRoleSlugsTransaction::TRANSACTIONTYPE)
      ->setNewValue(array($slug));

    $this->applyTransactions($role, $user, $xactions);

    $role_query = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withSlugs(array($name));
    $role_query->execute();
    $map = $role_query->getSlugMap();

    $this->assertEqual(
      array(
        $name => $role->getPHID(),
      ),
      ipull($map, 'rolePHID'));

    $role_query = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withSlugs(array($slug));
    $role_query->execute();
    $map = $role_query->getSlugMap();

    $this->assertEqual(
      array(
        $slug => $role->getPHID(),
      ),
      ipull($map, 'rolePHID'));

    $role_query = id(new PhabricatorRoleQuery())
      ->setViewer($user)
      ->withSlugs(array($name, $slug, $name2, $slug2));
    $role_query->execute();
    $map = $role_query->getSlugMap();

    $expect = array(
      $name => $role->getPHID(),
      $slug => $role->getPHID(),
      $name2 => $role->getPHID(),
      $slug2 => $role->getPHID(),
    );

    $actual = ipull($map, 'rolePHID');

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

  public function testJoinLeaveRole() {
    $user = $this->createUser();
    $user->save();

    $role = $this->createRoleWithNewAuthor();

    $role = $this->refreshRole($role, $user, true);
    $this->assertTrue(
      (bool)$role,
      pht(
        'Assumption that roles are default visible '.
        'to any user when created.'));

    $this->assertFalse(
      $role->isUserMember($user->getPHID()),
      pht('Arbitrary user not member of role.'));

    // Join the role.
    $this->joinRole($role, $user);

    $role = $this->refreshRole($role, $user, true);
    $this->assertTrue((bool)$role);

    $this->assertTrue(
      $role->isUserMember($user->getPHID()),
      pht('Join works.'));


    // Join the role again.
    $this->joinRole($role, $user);

    $role = $this->refreshRole($role, $user, true);
    $this->assertTrue((bool)$role);

    $this->assertTrue(
      $role->isUserMember($user->getPHID()),
      pht('Joining an already-joined role is a no-op.'));


    // Leave the role.
    $this->leaveRole($role, $user);

    $role = $this->refreshRole($role, $user, true);
    $this->assertTrue((bool)$role);

    $this->assertFalse(
      $role->isUserMember($user->getPHID()),
      pht('Leave works.'));


    // Leave the role again.
    $this->leaveRole($role, $user);

    $role = $this->refreshRole($role, $user, true);
    $this->assertTrue((bool)$role);

    $this->assertFalse(
      $role->isUserMember($user->getPHID()),
      pht('Leaving an already-left role is a no-op.'));


    // If a user can't edit or join a role, joining fails.
    $role->setEditPolicy(PhabricatorPolicies::POLICY_NOONE);
    $role->setJoinPolicy(PhabricatorPolicies::POLICY_NOONE);
    $role->save();

    $role = $this->refreshRole($role, $user, true);
    $caught = null;
    try {
      $this->joinRole($role, $user);
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($ex instanceof Exception);


    // If a user can edit a role, they can join.
    $role->setEditPolicy(PhabricatorPolicies::POLICY_USER);
    $role->setJoinPolicy(PhabricatorPolicies::POLICY_NOONE);
    $role->save();

    $role = $this->refreshRole($role, $user, true);
    $this->joinRole($role, $user);
    $role = $this->refreshRole($role, $user, true);
    $this->assertTrue(
      $role->isUserMember($user->getPHID()),
      pht('Join allowed with edit permission.'));
    $this->leaveRole($role, $user);


    // If a user can join a role, they can join, even if they can't edit.
    $role->setEditPolicy(PhabricatorPolicies::POLICY_NOONE);
    $role->setJoinPolicy(PhabricatorPolicies::POLICY_USER);
    $role->save();

    $role = $this->refreshRole($role, $user, true);
    $this->joinRole($role, $user);
    $role = $this->refreshRole($role, $user, true);
    $this->assertTrue(
      $role->isUserMember($user->getPHID()),
      pht('Join allowed with join permission.'));


    // A user can leave a role even if they can't edit it or join.
    $role->setEditPolicy(PhabricatorPolicies::POLICY_NOONE);
    $role->setJoinPolicy(PhabricatorPolicies::POLICY_NOONE);
    $role->save();

    $role = $this->refreshRole($role, $user, true);
    $this->leaveRole($role, $user);
    $role = $this->refreshRole($role, $user, true);
    $this->assertFalse(
      $role->isUserMember($user->getPHID()),
      pht('Leave allowed without any permission.'));
  }


  public function testComplexConstraints() {
    $user = $this->createUser();
    $user->save();

    $engineering = $this->createRole($user);
    $engineering_scan = $this->createRole($user, $engineering);
    $engineering_warp = $this->createRole($user, $engineering);

    $exploration = $this->createRole($user);
    $exploration_diplomacy = $this->createRole($user, $exploration);

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

    $this->assertQueryByRoles(
      $user,
      array(
        $task_engineering,
        $task_warp_explore,
        $task_diplomacy_scan,
        $task_warp_scan,
      ),
      array($engineering),
      pht('All Engineering'));

    $this->assertQueryByRoles(
      $user,
      array(
        $task_diplomacy_scan,
        $task_warp_scan,
      ),
      array($engineering_scan),
      pht('All Scan'));

    $this->assertQueryByRoles(
      $user,
      array(
        $task_warp_explore,
        $task_diplomacy_scan,
      ),
      array($engineering, $exploration),
      pht('Engineering + Exploration'));

    // This is testing that a query for "Parent" and "Parent > Child" works
    // properly.
    $this->assertQueryByRoles(
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

    $stonework = $this->createRole($user);
    $stonework_masonry = $this->createRole($user, $stonework);
    $stonework_sculpting = $this->createRole($user, $stonework);

    $task = $this->newTask($user, array());
    $this->assertEqual(array(), $this->getTaskRoles($task));

    $this->addRoleTags($user, $task, array($stonework->getPHID()));
    $this->assertEqual(
      array(
        $stonework->getPHID(),
      ),
      $this->getTaskRoles($task));

    // Adding a descendant should remove the parent.
    $this->addRoleTags($user, $task, array($stonework_masonry->getPHID()));
    $this->assertEqual(
      array(
        $stonework_masonry->getPHID(),
      ),
      $this->getTaskRoles($task));

    // Adding an ancestor should remove the descendant.
    $this->addRoleTags($user, $task, array($stonework->getPHID()));
    $this->assertEqual(
      array(
        $stonework->getPHID(),
      ),
      $this->getTaskRoles($task));

    // Adding two tags in the same hierarchy which are not mutual ancestors
    // should remove the ancestor but otherwise work fine.
    $this->addRoleTags(
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

    $this->assertEqual($expect,  $this->getTaskRoles($task));
  }

  public function testTagMilestoneConflicts() {
    $user = $this->createUser();
    $user->save();

    $stonework = $this->createRole($user);
    $stonework_1 = $this->createRole($user, $stonework, true);
    $stonework_2 = $this->createRole($user, $stonework, true);

    $task = $this->newTask($user, array());
    $this->assertEqual(array(), $this->getTaskRoles($task));

    $this->addRoleTags($user, $task, array($stonework->getPHID()));
    $this->assertEqual(
      array(
        $stonework->getPHID(),
      ),
      $this->getTaskRoles($task));

    // Adding a milesone should remove the parent.
    $this->addRoleTags($user, $task, array($stonework_1->getPHID()));
    $this->assertEqual(
      array(
        $stonework_1->getPHID(),
      ),
      $this->getTaskRoles($task));

    // Adding the parent should remove the milestone.
    $this->addRoleTags($user, $task, array($stonework->getPHID()));
    $this->assertEqual(
      array(
        $stonework->getPHID(),
      ),
      $this->getTaskRoles($task));

    // First, add one milestone.
    $this->addRoleTags($user, $task, array($stonework_1->getPHID()));
    // Now, adding a second milestone should remove the first milestone.
    $this->addRoleTags($user, $task, array($stonework_2->getPHID()));
    $this->assertEqual(
      array(
        $stonework_2->getPHID(),
      ),
      $this->getTaskRoles($task));
  }

  public function testBoardMoves() {
    $user = $this->createUser();
    $user->save();

    $board = $this->createRole($user);

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

    $board = $this->createRole($user);

    $backlog = $this->addColumn($user, $board, 0);

    // Create a task into the backlog.
    $task = $this->newTask($user, array($board));
    $expect = array(
      $backlog->getPHID(),
    );
    $this->assertColumns($expect, $user, $board, $task);

    $milestone = $this->createRole($user, $board, true);

    $this->addRoleTags($user, $task, array($milestone->getPHID()));

    // We just want the side effect of looking at the board: creation of the
    // milestone column.
    $this->loadColumns($user, $board, $task);

    $column = id(new PhabricatorRoleColumnQuery())
      ->setViewer($user)
      ->withRolePHIDs(array($board->getPHID()))
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
    // the roles the task is tagged with. See T10912.
    $task_a = $task;

    $task_b = $this->newTask($user, array($backlog));
    $this->moveToColumn($user, $board, $task_b, $backlog, $column);

    $a_options = array(
      'beforePHID' => $task_b->getPHID(),
    );

    $b_options = array(
      'beforePHID' => $task_a->getPHID(),
    );

    $old_roles = $this->getTaskRoles($task);

    // Move the target task to the top.
    $this->moveToColumn($user, $board, $task_a, $column, $column, $a_options);
    $new_roles = $this->getTaskRoles($task_a);
    $this->assertEqual($old_roles, $new_roles);

    // Move the other task.
    $this->moveToColumn($user, $board, $task_b, $column, $column, $b_options);
    $new_roles = $this->getTaskRoles($task_a);
    $this->assertEqual($old_roles, $new_roles);

    // Move the target task again.
    $this->moveToColumn($user, $board, $task_a, $column, $column, $a_options);
    $new_roles = $this->getTaskRoles($task_a);
    $this->assertEqual($old_roles, $new_roles);


    // Add the parent role to the task. This should move it out of the
    // milestone column and into the parent's backlog.
    $this->addRoleTags($user, $task, array($board->getPHID()));
    $expect_columns = array(
      $backlog->getPHID(),
    );
    $this->assertColumns($expect_columns, $user, $board, $task);

    $new_roles = $this->getTaskRoles($task);
    $expect_roles = array(
      $board->getPHID(),
    );
    $this->assertEqual($expect_roles, $new_roles);
  }

  public function testColumnExtendedPolicies() {
    $user = $this->createUser();
    $user->save();

    $board = $this->createRole($user);
    $column = $this->addColumn($user, $board, 0);

    // At first, the user should be able to view and edit the column.
    $column = $this->refreshColumn($user, $column);
    $this->assertTrue((bool)$column);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $column,
      PhabricatorPolicyCapability::CAN_EDIT);
    $this->assertTrue($can_edit);

    // Now, set the role edit policy to "Members of Role". This should
    // disable editing.
    $members_policy = id(new PhabricatorRoleMembersPolicyRule())
      ->getObjectPolicyFullKey();
    $board->setEditPolicy($members_policy)->save();

    $column = $this->refreshColumn($user, $column);
    $this->assertTrue((bool)$column);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $column,
      PhabricatorPolicyCapability::CAN_EDIT);
    $this->assertFalse($can_edit);

    // Now, join the role. This should make the column editable again.
    $this->joinRole($board, $user);

    $column = $this->refreshColumn($user, $column);
    $this->assertTrue((bool)$column);

    // This test has been failing randomly in a way that doesn't reproduce
    // on any host, so add some extra assertions to try to nail it down.
    $board = $this->refreshRole($board, $user, true);
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

  public function testRolePolicyRules() {
    $author = $this->generateNewTestUser();

    $role_a = PhabricatorRole::initializeNewRole($author)
      ->setName('Policy A')
      ->save();
    $role_b = PhabricatorRole::initializeNewRole($author)
      ->setName('Policy B')
      ->save();

    $user_none = $this->generateNewTestUser();
    $user_any = $this->generateNewTestUser();
    $user_all = $this->generateNewTestUser();

    $this->joinRole($role_a, $user_any);
    $this->joinRole($role_a, $user_all);
    $this->joinRole($role_b, $user_all);

    $any_policy = id(new PhabricatorPolicy())
      ->setRules(
        array(
          array(
            'action' => PhabricatorPolicy::ACTION_ALLOW,
            'rule' => 'PhabricatorRolesPolicyRule',
            'value' => array(
              $role_a->getPHID(),
              $role_b->getPHID(),
            ),
          ),
        ))
      ->save();

    $all_policy = id(new PhabricatorPolicy())
      ->setRules(
        array(
          array(
            'action' => PhabricatorPolicy::ACTION_ALLOW,
            'rule' => 'PhabricatorRolesAllPolicyRule',
            'value' => array(
              $role_a->getPHID(),
              $role_b->getPHID(),
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
        pht('Role policy rule; user in no roles'),
        $user_none,
        false,
        false,
      ),
      array(
        pht('Role policy rule; user in some roles'),
        $user_any,
        true,
        false,
      ),
      array(
        pht('Role policy rule; user in all roles'),
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
    PhabricatorRole $board,
    ManiphestTask $task,
    PhabricatorRoleColumn $src,
    PhabricatorRoleColumn $dst,
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
    PhabricatorRole $board,
    ManiphestTask $task) {
    $column_phids = $this->loadColumns($viewer, $board, $task);
    $this->assertEqual($expect, $column_phids);
  }

  private function loadColumns(
    PhabricatorUser $viewer,
    PhabricatorRole $board,
    ManiphestTask $task) {
    $engine = id(new PhabricatorRoleBoardLayoutEngine())
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
    PhabricatorRole $board,
    PhabricatorRoleColumn $column,
    $label = null) {

    $engine = id(new PhabricatorRoleBoardLayoutEngine())
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
    PhabricatorRole $role,
    $sequence) {

    $role->setHasWorkboard(1)->save();

    return PhabricatorRoleColumn::initializeNewColumn($viewer)
      ->setSequence(0)
      ->setProperty('isDefault', ($sequence == 0))
      ->setRolePHID($role->getPHID())
      ->save();
  }

  private function getTaskRoles(ManiphestTask $task) {
    $role_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $task->getPHID(),
      PhabricatorRoleObjectHasRoleEdgeType::EDGECONST);

    sort($role_phids);

    return $role_phids;
  }

  private function attemptRoleEdit(
    PhabricatorRole $role,
    PhabricatorUser $user,
    $skip_refresh = false) {

    $role = $this->refreshRole($role, $user, true);

    $new_name = $role->getName().' '.mt_rand();

    $params = array(
      'objectIdentifier' => $role->getID(),
      'transactions' => array(
        array(
          'type' => 'name',
          'value' => $new_name,
        ),
      ),
    );

    id(new ConduitCall('role.edit', $params))
      ->setUser($user)
      ->execute();

    return true;
  }


  private function addRoleTags(
    PhabricatorUser $viewer,
    ManiphestTask $task,
    array $phids) {

    $xactions = array();

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue(
        'edge:type',
        PhabricatorRoleObjectHasRoleEdgeType::EDGECONST)
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
    array $roles,
    $name = null) {

    $task = ManiphestTask::initializeNewTask($viewer);

    if ($name === null || $name === '') {
      $name = pht('Test Task');
    }

    $xactions = array();

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTaskTitleTransaction::TRANSACTIONTYPE)
      ->setNewValue($name);

    if ($roles) {
      $xactions[] = id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue(
          'edge:type',
          PhabricatorRoleObjectHasRoleEdgeType::EDGECONST)
        ->setNewValue(
          array(
            '=' => array_fuse(mpull($roles, 'getPHID')),
          ));
    }

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
      ->setContentSource($this->newContentSource())
      ->setContinueOnNoEffect(true)
      ->applyTransactions($task, $xactions);

    return $task;
  }

  private function assertQueryByRoles(
    PhabricatorUser $viewer,
    array $expect,
    array $roles,
    $label = null) {

    $datasource = id(new PhabricatorRoleLogicalDatasource())
      ->setViewer($viewer);

    $role_phids = mpull($roles, 'getPHID');
    $constraints = $datasource->evaluateTokens($role_phids);

    $query = id(new ManiphestTaskQuery())
      ->setViewer($viewer);

    $query->withEdgeLogicConstraints(
      PhabricatorRoleObjectHasRoleEdgeType::EDGECONST,
      $constraints);

    $tasks = $query->execute();

    $expect_phids = mpull($expect, 'getTitle', 'getPHID');
    ksort($expect_phids);

    $actual_phids = mpull($tasks, 'getTitle', 'getPHID');
    ksort($actual_phids);

    $this->assertEqual($expect_phids, $actual_phids, $label);
  }

  private function refreshRole(
    PhabricatorRole $role,
    PhabricatorUser $viewer,
    $need_members = false,
    $need_watchers = false) {

    $results = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->needMembers($need_members)
      ->needWatchers($need_watchers)
      ->withIDs(array($role->getID()))
      ->execute();

    if ($results) {
      return head($results);
    } else {
      return null;
    }
  }

  private function refreshColumn(
    PhabricatorUser $viewer,
    PhabricatorRoleColumn $column) {

    $results = id(new PhabricatorRoleColumnQuery())
      ->setViewer($viewer)
      ->withIDs(array($column->getID()))
      ->execute();

    if ($results) {
      return head($results);
    } else {
      return null;
    }
  }

  private function createRole(
    PhabricatorUser $user,
    PhabricatorRole $parent = null,
    $is_milestone = false) {

    $role = PhabricatorRole::initializeNewRole($user, $parent);

    $name = pht('Test Role %d', mt_rand());

    $xactions = array();

    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorRoleNameTransaction::TRANSACTIONTYPE)
      ->setNewValue($name);

    if ($parent) {
      if ($is_milestone) {
        $xactions[] = id(new PhabricatorRoleTransaction())
          ->setTransactionType(
              PhabricatorRoleMilestoneTransaction::TRANSACTIONTYPE)
          ->setNewValue($parent->getPHID());
      } else {
        $xactions[] = id(new PhabricatorRoleTransaction())
          ->setTransactionType(
              PhabricatorRoleParentTransaction::TRANSACTIONTYPE)
          ->setNewValue($parent->getPHID());
      }
    }

    $this->applyTransactions($role, $user, $xactions);

    // Force these values immediately; they are normally updated by the
    // index engine.
    if ($parent) {
      if ($is_milestone) {
        $parent->setHasMilestones(1)->save();
      } else {
        $parent->setHasSubroles(1)->save();
      }
    }

    return $role;
  }

  private function setViewPolicy(
    PhabricatorRole $role,
    PhabricatorUser $user,
    $policy) {

    $xactions = array();

    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
      ->setNewValue($policy);

    $this->applyTransactions($role, $user, $xactions);

    return $role;
  }

  private function createRoleWithNewAuthor() {
    $author = $this->createUser();
    $author->save();

    $role = $this->createRole($author);

    return $role;
  }

  private function createUser() {
    $rand = mt_rand();

    $user = new PhabricatorUser();
    $user->setUsername('unittestuser'.$rand);
    $user->setRealName(pht('Unit Test User %d', $rand));

    return $user;
  }

  private function joinRole(
    PhabricatorRole $role,
    PhabricatorUser $user) {
    return $this->joinOrLeaveRole($role, $user, '+');
  }

  private function leaveRole(
    PhabricatorRole $role,
    PhabricatorUser $user) {
    return $this->joinOrLeaveRole($role, $user, '-');
  }

  private function watchRole(
    PhabricatorRole $role,
    PhabricatorUser $user) {
    return $this->watchOrUnwatchRole($role, $user, '+');
  }

  private function unwatchRole(
    PhabricatorRole $role,
    PhabricatorUser $user) {
    return $this->watchOrUnwatchRole($role, $user, '-');
  }

  private function joinOrLeaveRole(
    PhabricatorRole $role,
    PhabricatorUser $user,
    $operation) {
    return $this->applyRoleEdgeTransaction(
      $role,
      $user,
      $operation,
      PhabricatorRoleRoleHasMemberEdgeType::EDGECONST);
  }

  private function watchOrUnwatchRole(
    PhabricatorRole $role,
    PhabricatorUser $user,
    $operation) {
    return $this->applyRoleEdgeTransaction(
      $role,
      $user,
      $operation,
      PhabricatorObjectHasWatcherEdgeType::EDGECONST);
  }

  private function applyRoleEdgeTransaction(
    PhabricatorRole $role,
    PhabricatorUser $user,
    $operation,
    $edge_type) {

    $spec = array(
      $operation => array($user->getPHID() => $user->getPHID()),
    );

    $xactions = array();
    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $edge_type)
      ->setNewValue($spec);

    $this->applyTransactions($role, $user, $xactions);

    return $role;
  }

  private function applyTransactions(
    PhabricatorRole $role,
    PhabricatorUser $user,
    array $xactions) {

    $editor = id(new PhabricatorRoleTransactionEditor())
      ->setActor($user)
      ->setContentSource($this->newContentSource())
      ->setContinueOnNoEffect(true)
      ->applyTransactions($role, $xactions);
  }


}
