<?php

final class FileCreateMailReceiver
  extends PhabricatorApplicationMailReceiver {

  protected function newApplication() {
    return new PhabricatorFilesApplication();
  }

  protected function processReceivedMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhutilEmailAddress $target) {
    $author = $this->getAuthor();

    $attachment_phids = $mail->getAttachments();
    if (empty($attachment_phids)) {
      throw new PhabricatorMetaMTAReceivedMailProcessingException(
        MetaMTAReceivedMailStatus::STATUS_UNHANDLED_EXCEPTION,
        pht(
          'Ignoring email to create files that did not include attachments.'));
    }
    $first_phid = head($attachment_phids);
    $mail->setRelatedPHID($first_phid);

    $sender = $this->getSender();
    if (!$sender) {
      return;
    }

    $attachment_count = count($attachment_phids);
    if ($attachment_count > 1) {
      $subject = pht('You successfully uploaded %d files.', $attachment_count);
    } else {
      $subject = pht('You successfully uploaded a file.');
    }
    $subject_prefix = pht('[File]');

    $file_uris = array();
    foreach ($attachment_phids as $phid) {
      $file_uris[] =
        PhabricatorEnv::getProductionURI('/file/info/'.$phid.'/');
    }

    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection($subject);
    $body->addTextSection(pht('FILE LINKS'), implode("\n", $file_uris));

    id(new PhabricatorMetaMTAMail())
      ->addTos(array($sender->getPHID()))
      ->setSubject($subject)
      ->setSubjectPrefix($subject_prefix)
      ->setFrom($sender->getPHID())
      ->setRelatedPHID($first_phid)
      ->setBody($body->render())
      ->saveAndSend();
  }

}
