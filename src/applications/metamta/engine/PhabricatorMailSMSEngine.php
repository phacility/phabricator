<?php

final class PhabricatorMailSMSEngine
  extends PhabricatorMailMessageEngine {

  public function newMessage() {
    $mailer = $this->getMailer();
    $mail = $this->getMail();

    $message = new PhabricatorMailSMSMessage();

    $phids = $mail->getToPHIDs();
    if (!$phids) {
      $mail->setMessage(pht('Message has no "To" recipient.'));
      return null;
    }

    if (count($phids) > 1) {
      $mail->setMessage(pht('Message has more than one "To" recipient.'));
      return null;
    }

    $phid = head($phids);

    $actor = $this->getActor($phid);
    if (!$actor) {
      $mail->setMessage(pht('Message recipient has no mailable actor.'));
      return null;
    }

    if (!$actor->isDeliverable()) {
      $mail->setMessage(pht('Message recipient is not deliverable.'));
      return null;
    }

    $omnipotent = PhabricatorUser::getOmnipotentUser();

    $contact_numbers = id(new PhabricatorAuthContactNumberQuery())
      ->setViewer($omnipotent)
      ->withObjectPHIDs(array($phid))
      ->withStatuses(
        array(
          PhabricatorAuthContactNumber::STATUS_ACTIVE,
        ))
      ->withIsPrimary(true)
      ->execute();

    if (!$contact_numbers) {
      $mail->setMessage(
        pht('Message recipient has no primary contact number.'));
      return null;
    }

    // The database does not strictly guarantee that only one number is
    // primary, so make sure no one has monkeyed with stuff.
    if (count($contact_numbers) > 1) {
      $mail->setMessage(
        pht('Message recipient has more than one primary contact number.'));
      return null;
    }

    $contact_number = head($contact_numbers);
    $contact_number = $contact_number->getContactNumber();
    $to_number = new PhabricatorPhoneNumber($contact_number);
    $message->setToNumber($to_number);

    $body = $mail->getBody();
    if ($body !== null) {
      $message->setTextBody($body);
    }

    return $message;
  }

}
