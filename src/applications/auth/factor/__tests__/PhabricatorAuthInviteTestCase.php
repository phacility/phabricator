<?php

final class PhabricatorAuthInviteTestCase extends PhabricatorTestCase {


  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }


  /**
   * Test that invalid invites can not be accepted.
   */
  public function testInvalidInvite() {
    $viewer = $this->generateUser();
    $engine = $this->generateEngine($viewer);

    $caught = null;
    try {
      $engine->processInviteCode('asdf1234');
    } catch (PhabricatorAuthInviteInvalidException $ex) {
      $caught = $ex;
    }

    $this->assertTrue($caught instanceof Exception);
  }


  /**
   * Test that invites can be accepted exactly once.
   */
  public function testDuplicateInvite() {
    $author = $this->generateUser();
    $viewer = $this->generateUser();
    $address = Filesystem::readRandomCharacters(16).'@example.com';

    $invite = id(new PhabricatorAuthInvite())
      ->setAuthorPHID($author->getPHID())
      ->setEmailAddress($address)
      ->save();

    $engine = $this->generateEngine($viewer);
    $engine->setUserHasConfirmedVerify(true);

    $caught = null;
    try {
      $result = $engine->processInviteCode($invite->getVerificationCode());
    } catch (Exception $ex) {
      $caught = $ex;
    }

    // This first time should accept the invite and verify the address.
    $this->assertTrue(
      ($caught instanceof PhabricatorAuthInviteRegisteredException));

    try {
      $result = $engine->processInviteCode($invite->getVerificationCode());
    } catch (Exception $ex) {
      $caught = $ex;
    }

    // The second time through, the invite should not be acceptable.
    $this->assertTrue(
      ($caught instanceof PhabricatorAuthInviteInvalidException));
  }


  /**
   * Test easy invite cases, where the email is not anywhere in the system.
   */
  public function testInviteWithNewEmail() {
    $expect_map = array(
      'out' => array(
        null,
        null,
      ),
      'in' => array(
        'PhabricatorAuthInviteVerifyException',
        'PhabricatorAuthInviteRegisteredException',
      ),
    );

    $author = $this->generateUser();
    $logged_in = $this->generateUser();
    $logged_out = new PhabricatorUser();

    foreach (array('out', 'in') as $is_logged_in) {
      foreach (array(0, 1) as $should_verify) {
        $address = Filesystem::readRandomCharacters(16).'@example.com';

        $invite = id(new PhabricatorAuthInvite())
          ->setAuthorPHID($author->getPHID())
          ->setEmailAddress($address)
          ->save();

        switch ($is_logged_in) {
          case 'out':
            $viewer = $logged_out;
            break;
          case 'in':
            $viewer = $logged_in;
            break;
        }

        $engine = $this->generateEngine($viewer);
        $engine->setUserHasConfirmedVerify($should_verify);

        $caught = null;
        try {
          $result = $engine->processInviteCode($invite->getVerificationCode());
        } catch (Exception $ex) {
          $caught = $ex;
        }

        $expect = $expect_map[$is_logged_in];
        $expect = $expect[$should_verify];

        $this->assertEqual(
          ($expect !== null),
          ($caught instanceof Exception),
          pht(
            'user=%s, should_verify=%s',
            $is_logged_in,
            $should_verify));

        if ($expect === null) {
          $this->assertEqual($invite->getPHID(), $result->getPHID());
        } else {
          $this->assertEqual(
            $expect,
            get_class($caught),
            pht('Actual exception: %s', $caught->getMessage()));
        }
      }
    }
  }


  /**
   * Test hard invite cases, where the email is already known and attached
   * to some user account.
   */
  public function testInviteWithKnownEmail() {

    // This tests all permutations of:
    //
    //   - Is the user logged out, logged in with a different account, or
    //     logged in with the correct account?
    //   - Is the address verified, or unverified?
    //   - Is the address primary, or nonprimary?
    //   - Has the user confirmed that they want to verify the address?

    $expect_map = array(
      'out' => array(
        array(
          array(
            // For example, this corresponds to a logged out user trying to
            // follow an invite with an unverified, nonprimary address, and
            // they haven't clicked the "Verify" button yet. We ask them to
            // verify that they want to register a new account.
            'PhabricatorAuthInviteVerifyException',

            // In this case, they have clicked the verify button. The engine
            // continues the workflow.
            null,
          ),
          array(
            // And so on. All of the rest of these cases cover the other
            // permutations.
            'PhabricatorAuthInviteLoginException',
            'PhabricatorAuthInviteLoginException',
          ),
        ),
        array(
          array(
            'PhabricatorAuthInviteLoginException',
            'PhabricatorAuthInviteLoginException',
          ),
          array(
            'PhabricatorAuthInviteLoginException',
            'PhabricatorAuthInviteLoginException',
          ),
        ),
      ),
      'in' => array(
        array(
          array(
            'PhabricatorAuthInviteVerifyException',
            array(true, 'PhabricatorAuthInviteRegisteredException'),
          ),
          array(
            'PhabricatorAuthInviteAccountException',
            'PhabricatorAuthInviteAccountException',
          ),
        ),
        array(
          array(
            'PhabricatorAuthInviteAccountException',
            'PhabricatorAuthInviteAccountException',
          ),
          array(
            'PhabricatorAuthInviteAccountException',
            'PhabricatorAuthInviteAccountException',
          ),
        ),
      ),
      'same' => array(
        array(
          array(
            'PhabricatorAuthInviteVerifyException',
            array(true, 'PhabricatorAuthInviteRegisteredException'),
          ),
          array(
            'PhabricatorAuthInviteVerifyException',
            array(true, 'PhabricatorAuthInviteRegisteredException'),
          ),
        ),
        array(
          array(
            'PhabricatorAuthInviteRegisteredException',
            'PhabricatorAuthInviteRegisteredException',
          ),
          array(
            'PhabricatorAuthInviteRegisteredException',
            'PhabricatorAuthInviteRegisteredException',
          ),
        ),
      ),
    );

    $author = $this->generateUser();
    $logged_in = $this->generateUser();
    $logged_out = new PhabricatorUser();

    foreach (array('out', 'in', 'same') as $is_logged_in) {
      foreach (array(0, 1) as $is_verified) {
        foreach (array(0, 1) as $is_primary) {
          foreach (array(0, 1) as $should_verify) {
            $other = $this->generateUser();

            switch ($is_logged_in) {
              case 'out':
                $viewer = $logged_out;
                break;
              case 'in';
                $viewer = $logged_in;
                break;
              case 'same':
                $viewer = clone $other;
                break;
            }

            $email = $this->generateEmail($other, $is_verified, $is_primary);

            $invite = id(new PhabricatorAuthInvite())
              ->setAuthorPHID($author->getPHID())
              ->setEmailAddress($email->getAddress())
              ->save();
            $code = $invite->getVerificationCode();

            $engine = $this->generateEngine($viewer);
            $engine->setUserHasConfirmedVerify($should_verify);

            $caught = null;
            try {
              $result = $engine->processInviteCode($code);
            } catch (Exception $ex) {
              $caught = $ex;
            }

            $expect = $expect_map[$is_logged_in];
            $expect = $expect[$is_verified];
            $expect = $expect[$is_primary];
            $expect = $expect[$should_verify];

            if (is_array($expect)) {
              list($expect_reassign, $expect_exception) = $expect;
            } else {
              $expect_reassign = false;
              $expect_exception = $expect;
            }

            $case_info = pht(
              'user=%s, verified=%s, primary=%s, should_verify=%s',
              $is_logged_in,
              $is_verified,
              $is_primary,
              $should_verify);

            $this->assertEqual(
              ($expect_exception !== null),
              ($caught instanceof Exception),
              $case_info);

            if ($expect_exception === null) {
              $this->assertEqual($invite->getPHID(), $result->getPHID());
            } else {
              $this->assertEqual(
                $expect_exception,
                get_class($caught),
                pht('%s, exception=%s', $case_info, $caught->getMessage()));
            }

            if ($expect_reassign) {
              $email->reload();

              $this->assertEqual(
                $viewer->getPHID(),
                $email->getUserPHID(),
                pht(
                  'Expected email address reassignment (%s).',
                  $case_info));
            }

            switch ($expect_exception) {
              case 'PhabricatorAuthInviteRegisteredException':
                $invite->reload();

                $this->assertEqual(
                  $viewer->getPHID(),
                  $invite->getAcceptedByPHID(),
                  pht(
                    'Expected invite accepted (%s).',
                    $case_info));
                break;
            }

          }
        }
      }
    }
  }

  private function generateUser() {
    return $this->generateNewTestUser();
  }

  private function generateEngine(PhabricatorUser $viewer) {
    return id(new PhabricatorAuthInviteEngine())
      ->setViewer($viewer);
  }

  private function generateEmail(
    PhabricatorUser $user,
    $is_verified,
    $is_primary) {

    // NOTE: We're being a little bit sneaky here because UserEditor will not
    // let you make an unverified address a primary account address, and
    // the test user will already have a verified primary address.

    $email = id(new PhabricatorUserEmail())
      ->setAddress(Filesystem::readRandomCharacters(16).'@example.com')
      ->setIsVerified((int)($is_verified || $is_primary))
      ->setIsPrimary(0);

    $editor = id(new PhabricatorUserEditor())
      ->setActor($user);

    $editor->addEmail($user, $email);

    if ($is_primary) {
      $editor->changePrimaryEmail($user, $email);
    }

    $email->setIsVerified((int)$is_verified);
    $email->save();

    return $email;
  }

}
