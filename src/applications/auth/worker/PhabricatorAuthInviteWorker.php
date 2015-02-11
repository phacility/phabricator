<?php

final class PhabricatorAuthInviteWorker
  extends PhabricatorWorker {

  protected function doWork() {
    $data = $this->getTaskData();
    $viewer = PhabricatorUser::getOmnipotentUser();

    $address = idx($data, 'address');
    $author_phid = idx($data, 'authorPHID');

    $author = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($author_phid))
      ->executeOne();
    if (!$author) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Invite has invalid author PHID ("%s").', $author_phid));
    }

    $invite = id(new PhabricatorAuthInviteQuery())
      ->setViewer($viewer)
      ->withEmailAddresses(array($address))
      ->executeOne();
    if ($invite) {
      // If we're inviting a user who has already been invited, we just
      // regenerate their invite code.
      $invite->regenerateVerificationCode();
    } else {
      // Otherwise, we're creating a new invite.
      $invite = id(new PhabricatorAuthInvite())
        ->setEmailAddress($address);
    }

    // Whether this is a new invite or not, tag this most recent author as
    // the invite author.
    $invite->setAuthorPHID($author_phid);

    $code = $invite->getVerificationCode();
    $invite_uri = '/auth/invite/'.$code.'/';
    $invite_uri = PhabricatorEnv::getProductionURI($invite_uri);

    $template = idx($data, 'template');
    $template = str_replace('{$INVITE_URI}', $invite_uri, $template);

    $invite->save();

    $mail = id(new PhabricatorMetaMTAMail())
      ->addRawTos(array($invite->getEmailAddress()))
      ->setForceDelivery(true)
      ->setSubject(
        pht(
          '[Phabricator] %s has invited you to join Phabricator',
          $author->getFullName()))
      ->setBody($template)
      ->saveAndSend();
  }

}
