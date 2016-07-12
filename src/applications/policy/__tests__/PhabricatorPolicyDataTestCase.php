<?php

final class PhabricatorPolicyDataTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testProjectPolicyMembership() {
    $author = $this->generateNewTestUser();

    $proj_a = PhabricatorProject::initializeNewProject($author)
      ->setName('A')
      ->save();
    $proj_b = PhabricatorProject::initializeNewProject($author)
      ->setName('B')
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
            'rule' => 'PhabricatorUsersPolicyRule',
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
            'rule' => 'PhabricatorAdministratorsPolicyRule',
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
            'rule' => 'PhabricatorLunarPhasePolicyRule',
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

  public function testObjectPolicyRuleTaskAuthor() {
    $author = $this->generateNewTestUser();
    $viewer = $this->generateNewTestUser();

    $rule = new ManiphestTaskAuthorPolicyRule();

    $task = ManiphestTask::initializeNewTask($author);
    $task->setViewPolicy($rule->getObjectPolicyFullKey());
    $task->save();

    $this->assertTrue(
      PhabricatorPolicyFilter::hasCapability(
        $author,
        $task,
        PhabricatorPolicyCapability::CAN_VIEW));

    $this->assertFalse(
      PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $task,
        PhabricatorPolicyCapability::CAN_VIEW));
  }

  public function testObjectPolicyRuleThreadMembers() {
    $author = $this->generateNewTestUser();
    $viewer = $this->generateNewTestUser();

    $rule = new ConpherenceThreadMembersPolicyRule();

    $thread = ConpherenceThread::initializeNewRoom($author);
    $thread->setViewPolicy($rule->getObjectPolicyFullKey());
    $thread->save();

    $this->assertFalse(
      PhabricatorPolicyFilter::hasCapability(
        $author,
        $thread,
        PhabricatorPolicyCapability::CAN_VIEW));

    $this->assertFalse(
      PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $thread,
        PhabricatorPolicyCapability::CAN_VIEW));

    $participant = id(new ConpherenceParticipant())
      ->setParticipantPHID($viewer->getPHID())
      ->setConpherencePHID($thread->getPHID());

    $thread->attachParticipants(array($viewer->getPHID() => $participant));

    $this->assertTrue(
      PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $thread,
        PhabricatorPolicyCapability::CAN_VIEW));
  }

  public function testObjectPolicyRuleSubscribers() {
    $author = $this->generateNewTestUser();

    $rule = new PhabricatorSubscriptionsSubscribersPolicyRule();

    $task = ManiphestTask::initializeNewTask($author);
    $task->setViewPolicy($rule->getObjectPolicyFullKey());
    $task->save();

    $this->assertFalse(
      PhabricatorPolicyFilter::hasCapability(
        $author,
        $task,
        PhabricatorPolicyCapability::CAN_VIEW));

    id(new PhabricatorSubscriptionsEditor())
      ->setActor($author)
      ->setObject($task)
      ->subscribeExplicit(array($author->getPHID()))
      ->save();

    $this->assertTrue(
      PhabricatorPolicyFilter::hasCapability(
        $author,
        $task,
        PhabricatorPolicyCapability::CAN_VIEW));
  }

}
