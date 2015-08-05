<?php


/**
 * This class does an unusual amount of flow control via exceptions. The intent
 * is to make the workflows highly testable, because this code is high-stakes
 * and difficult to test.
 */
final class PhabricatorAuthInviteEngine extends Phobject {

  private $viewer;
  private $userHasConfirmedVerify;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    if (!$this->viewer) {
      throw new PhutilInvalidStateException('setViewer');
    }
    return $this->viewer;
  }

  public function setUserHasConfirmedVerify($confirmed) {
    $this->userHasConfirmedVerify = $confirmed;
    return $this;
  }

  private function shouldVerify() {
    return $this->userHasConfirmedVerify;
  }

  public function processInviteCode($code) {
    $viewer = $this->getViewer();

    $invite = id(new PhabricatorAuthInviteQuery())
      ->setViewer($viewer)
      ->withVerificationCodes(array($code))
      ->executeOne();
    if (!$invite) {
      throw id(new PhabricatorAuthInviteInvalidException(
        pht('Bad Invite Code'),
        pht(
          'The invite code in the link you clicked is invalid. Check that '.
          'you followed the link correctly.')))
        ->setCancelButtonURI('/')
        ->setCancelButtonText(pht('Curses!'));
    }

    $accepted_phid = $invite->getAcceptedByPHID();
    if ($accepted_phid) {
      if ($accepted_phid == $viewer->getPHID()) {
        throw id(new PhabricatorAuthInviteInvalidException(
          pht('Already Accepted'),
          pht(
            'You have already accepted this invitation.')))
          ->setCancelButtonURI('/')
          ->setCancelButtonText(pht('Awesome'));
      } else {
        throw id(new PhabricatorAuthInviteInvalidException(
          pht('Already Accepted'),
          pht(
            'The invite code in the link you clicked has already '.
            'been accepted.')))
          ->setCancelButtonURI('/')
          ->setCancelButtonText(pht('Continue'));
      }
    }

    $email = id(new PhabricatorUserEmail())->loadOneWhere(
      'address = %s',
      $invite->getEmailAddress());

    if ($viewer->isLoggedIn()) {
      $this->handleLoggedInInvite($invite, $viewer, $email);
    }

    if ($email) {
      $other_user = $this->loadUserForEmail($email);

      if ($email->getIsVerified()) {
        throw id(new PhabricatorAuthInviteLoginException(
          pht('Already Registered'),
          pht(
            'The email address you just clicked a link from is already '.
            'verified and associated with a registered account (%s). Log '.
            'in to continue.',
            phutil_tag('strong', array(), $other_user->getName()))))
          ->setCancelButtonText(pht('Log In'))
          ->setCancelButtonURI($this->getLoginURI());
      } else if ($email->getIsPrimary()) {
        throw id(new PhabricatorAuthInviteLoginException(
          pht('Already Registered'),
          pht(
            'The email address you just clicked a link from is already '.
            'the primary email address for a registered account (%s). Log '.
            'in to continue.',
            phutil_tag('strong', array(), $other_user->getName()))))
          ->setCancelButtonText(pht('Log In'))
          ->setCancelButtonURI($this->getLoginURI());
      } else if (!$this->shouldVerify()) {
        throw id(new PhabricatorAuthInviteVerifyException(
          pht('Already Associated'),
          pht(
            'The email address you just clicked a link from is already '.
            'associated with a registered account (%s), but is not '.
            'verified. Log in to that account to continue. If you can not '.
            'log in, you can register a new account.',
            phutil_tag('strong', array(), $other_user->getName()))))
          ->setCancelButtonText(pht('Log In'))
          ->setCancelButtonURI($this->getLoginURI())
          ->setSubmitButtonText(pht('Register New Account'));
      } else {
        // NOTE: The address is not verified and not a primary address, so
        // we will eventually steal it if the user completes registration.
      }
    }

    // The invite and email address are OK, but the user needs to register.
    return $invite;
  }

  private function handleLoggedInInvite(
    PhabricatorAuthInvite $invite,
    PhabricatorUser $viewer,
    PhabricatorUserEmail $email = null) {

    if ($email && ($email->getUserPHID() !== $viewer->getPHID())) {
      $other_user = $this->loadUserForEmail($email);
      if ($email->getIsVerified()) {
        throw id(new PhabricatorAuthInviteAccountException(
          pht('Wrong Account'),
          pht(
            'You are logged in as %s, but the email address you just '.
            'clicked a link from is already verified and associated '.
            'with another account (%s). Switch accounts, then try again.',
            phutil_tag('strong', array(), $viewer->getUsername()),
            phutil_tag('strong', array(), $other_user->getName()))))
          ->setSubmitButtonText(pht('Log Out'))
          ->setSubmitButtonURI($this->getLogoutURI())
          ->setCancelButtonURI('/');
      } else if ($email->getIsPrimary()) {
        // NOTE: We never steal primary addresses from other accounts, even
        // if they are unverified. This would leave the other account with
        // no address. Users can use password recovery to access the other
        // account if they really control the address.
        throw id(new PhabricatorAuthInviteAccountException(
          pht('Wrong Acount'),
          pht(
            'You are logged in as %s, but the email address you just '.
            'clicked a link from is already the primary email address '.
            'for another account (%s). Switch accounts, then try again.',
            phutil_tag('strong', array(), $viewer->getUsername()),
            phutil_tag('strong', array(), $other_user->getName()))))
          ->setSubmitButtonText(pht('Log Out'))
          ->setSubmitButtonURI($this->getLogoutURI())
          ->setCancelButtonURI('/');
      } else if (!$this->shouldVerify()) {
        throw id(new PhabricatorAuthInviteVerifyException(
          pht('Verify Email'),
          pht(
            'You are logged in as %s, but the email address (%s) you just '.
            'clicked a link from is already associated with another '.
            'account (%s). You can log out to switch accounts, or verify '.
            'the address and attach it to your current account. Attach '.
            'email address %s to user account %s?',
            phutil_tag('strong', array(), $viewer->getUsername()),
            phutil_tag('strong', array(), $invite->getEmailAddress()),
            phutil_tag('strong', array(), $other_user->getName()),
            phutil_tag('strong', array(), $invite->getEmailAddress()),
            phutil_tag('strong', array(), $viewer->getUsername()))))
          ->setSubmitButtonText(
            pht(
              'Verify %s',
              $invite->getEmailAddress()))
          ->setCancelButtonText(pht('Log Out'))
          ->setCancelButtonURI($this->getLogoutURI());
      }
    }

    if (!$email) {
      $email = id(new PhabricatorUserEmail())
        ->setAddress($invite->getEmailAddress())
        ->setIsVerified(0)
        ->setIsPrimary(0);
    }

    if (!$email->getIsVerified()) {
      // We're doing this check here so that we can verify the address if
      // it's already attached to the viewer's account, just not verified.
      if (!$this->shouldVerify()) {
        throw id(new PhabricatorAuthInviteVerifyException(
          pht('Verify Email'),
          pht(
            'Verify this email address (%s) and attach it to your '.
            'account (%s)?',
            phutil_tag('strong', array(), $invite->getEmailAddress()),
            phutil_tag('strong', array(), $viewer->getUsername()))))
          ->setSubmitButtonText(
            pht(
              'Verify %s',
              $invite->getEmailAddress()))
          ->setCancelButtonURI('/');
      }

      $editor = id(new PhabricatorUserEditor())
        ->setActor($viewer);

      // If this is a new email, add it to the user's account.
      if (!$email->getUserPHID()) {
        $editor->addEmail($viewer, $email);
      }

      // If another user added this email (but has not verified it),
      // take it from them.
      $editor->reassignEmail($viewer, $email);

      $editor->verifyEmail($viewer, $email);
    }

    $invite->setAcceptedByPHID($viewer->getPHID());
    $invite->save();

    // If we make it here, the user was already logged in with the email
    // address attached to their account and verified, or we attached it to
    // their account (if it was not already attached) and verified it.
    throw new PhabricatorAuthInviteRegisteredException();
  }

  private function loadUserForEmail(PhabricatorUserEmail $email) {
    $user = id(new PhabricatorHandleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($email->getUserPHID()))
      ->executeOne();
    if (!$user) {
      throw new Exception(
        pht(
          'Email record ("%s") has bad associated user PHID ("%s").',
          $email->getAddress(),
          $email->getUserPHID()));
    }

    return $user;
  }

  private function getLoginURI() {
    return '/auth/start/';
  }

  private function getLogoutURI() {
    return '/logout/';
  }

}
