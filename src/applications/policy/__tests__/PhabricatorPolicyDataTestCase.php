<?php

final class PhabricatorPolicyDataTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testProjectPolicyMembership() {
    $author = $this->generateNewTestUser();

    $proj_a = id(new PhabricatorProject())
      ->setName('A')
      ->setAuthorPHID($author->getPHID())
      ->setIcon(PhabricatorProject::DEFAULT_ICON)
      ->setColor(PhabricatorProject::DEFAULT_COLOR)
      ->save();
    $proj_b = id(new PhabricatorProject())
      ->setName('B')
      ->setAuthorPHID($author->getPHID())
      ->setIcon(PhabricatorProject::DEFAULT_ICON)
      ->setColor(PhabricatorProject::DEFAULT_COLOR)
      ->save();

    $proj_a->setViewPolicy($proj_b->getPHID())->save();
    $proj_b->setViewPolicy($proj_a->getPHID())->save();

    $user = new PhabricatorUser();

    $results = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->execute();

    $this->assertEqual(0, count($results));
  }

  public function testCustomPolicyRuleUser() {
    $user_a = $this->generateNewTestUser();
    $user_b = $this->generateNewTestUser();
    $author = $this->generateNewTestUser();

    $policy = id(new PhabricatorPolicy())
      ->setRules(
        array(
          array(
            'action' => PhabricatorPolicy::ACTION_ALLOW,
            'rule' => 'PhabricatorPolicyRuleUsers',
            'value' => array($user_a->getPHID()),
          ),
        ))
      ->save();

    $task = ManiphestTask::initializeNewTask($author);
    $task->setViewPolicy($policy->getPHID());
    $task->save();

    $can_a_view = PhabricatorPolicyFilter::hasCapability(
      $user_a,
      $task,
      PhabricatorPolicyCapability::CAN_VIEW);

    $this->assertTrue($can_a_view);

    $can_b_view = PhabricatorPolicyFilter::hasCapability(
      $user_b,
      $task,
      PhabricatorPolicyCapability::CAN_VIEW);

    $this->assertFalse($can_b_view);
  }

  public function testCustomPolicyRuleAdministrators() {
    $user_a = $this->generateNewTestUser();
    $user_a->setIsAdmin(true)->save();
    $user_b = $this->generateNewTestUser();
    $author = $this->generateNewTestUser();

    $policy = id(new PhabricatorPolicy())
      ->setRules(
        array(
          array(
            'action' => PhabricatorPolicy::ACTION_ALLOW,
            'rule' => 'PhabricatorPolicyRuleAdministrators',
            'value' => null,
          ),
        ))
      ->save();

    $task = ManiphestTask::initializeNewTask($author);
    $task->setViewPolicy($policy->getPHID());
    $task->save();

    $can_a_view = PhabricatorPolicyFilter::hasCapability(
      $user_a,
      $task,
      PhabricatorPolicyCapability::CAN_VIEW);

    $this->assertTrue($can_a_view);

    $can_b_view = PhabricatorPolicyFilter::hasCapability(
      $user_b,
      $task,
      PhabricatorPolicyCapability::CAN_VIEW);

    $this->assertFalse($can_b_view);
  }

  public function testCustomPolicyRuleLunarPhase() {
    $user_a = $this->generateNewTestUser();
    $author = $this->generateNewTestUser();

    $policy = id(new PhabricatorPolicy())
      ->setRules(
        array(
          array(
            'action' => PhabricatorPolicy::ACTION_ALLOW,
            'rule' => 'PhabricatorPolicyRuleLunarPhase',
            'value' => 'new',
          ),
        ))
      ->save();

    $task = ManiphestTask::initializeNewTask($author);
    $task->setViewPolicy($policy->getPHID());
    $task->save();

    $time_a = PhabricatorTime::pushTime(934354800, 'UTC');

      $can_a_view = PhabricatorPolicyFilter::hasCapability(
        $user_a,
        $task,
        PhabricatorPolicyCapability::CAN_VIEW);
      $this->assertTrue($can_a_view);

    unset($time_a);


    $time_b = PhabricatorTime::pushTime(1116745200, 'UTC');

      $can_a_view = PhabricatorPolicyFilter::hasCapability(
        $user_a,
        $task,
        PhabricatorPolicyCapability::CAN_VIEW);
      $this->assertFalse($can_a_view);

    unset($time_b);
  }

}
