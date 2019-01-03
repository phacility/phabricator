<?php

abstract class PhabricatorApplicationMailReceiver
  extends PhabricatorMailReceiver {

  private $applicationEmail;
  private $emailList;
  private $author;

  abstract protected function newApplication();

  final protected function setApplicationEmail(
    PhabricatorMetaMTAApplicationEmail $email) {
    $this->applicationEmail = $email;
    return $this;
  }

  final protected function getApplicationEmail() {
    return $this->applicationEmail;
  }

  final protected function setAuthor(PhabricatorUser $author) {
    $this->author = $author;
    return $this;
  }

  final protected function getAuthor() {
    return $this->author;
  }

  final public function isEnabled() {
    return $this->newApplication()->isInstalled();
  }

  final public function canAcceptMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhutilEmailAddress $target) {

    $viewer = $this->getViewer();
    $sender = $this->getSender();

    foreach ($this->loadApplicationEmailList() as $application_email) {
      $create_address = $application_email->newAddress();

      if (!PhabricatorMailUtil::matchAddresses($create_address, $target)) {
        continue;
      }

      if ($sender) {
        $author = $sender;
      } else {
        $author_phid = $application_email->getDefaultAuthorPHID();

        // If this mail isn't from a recognized sender and the target address
        // does not have a default author, we can't accept it, and it's an
        // error because you tried to send it here.

        // You either need to be sending from a real address or be sending to
        // an address which accepts mail from the public internet.

        if (!$author_phid) {
          throw new PhabricatorMetaMTAReceivedMailProcessingException(
            MetaMTAReceivedMailStatus::STATUS_UNKNOWN_SENDER,
            pht(
              'You are sending from an unrecognized email address to '.
              'an address which does not support public email ("%s").',
              (string)$target));
        }

        $author = id(new PhabricatorPeopleQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($author_phid))
          ->executeOne();
        if (!$author) {
          throw new Exception(
            pht(
              'Application email ("%s") has an invalid default author ("%s").',
              (string)$create_address,
              $author_phid));
        }
      }

      $this
        ->setApplicationEmail($application_email)
        ->setAuthor($author);

      return true;
    }

    return false;
  }

  private function loadApplicationEmailList() {
    if ($this->emailList === null) {
      $viewer = $this->getViewer();
      $application = $this->newApplication();

      $this->emailList = id(new PhabricatorMetaMTAApplicationEmailQuery())
        ->setViewer($viewer)
        ->withApplicationPHIDs(array($application->getPHID()))
        ->execute();
    }

    return $this->emailList;
  }

}
