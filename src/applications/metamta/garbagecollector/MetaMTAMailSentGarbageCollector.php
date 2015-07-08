<?php

final class MetaMTAMailSentGarbageCollector
  extends PhabricatorGarbageCollector {

  public function collectGarbage() {
    $ttl = phutil_units('90 days in seconds');

    $mails = id(new PhabricatorMetaMTAMail())->loadAllWhere(
      'dateCreated < %d LIMIT 100',
      PhabricatorTime::getNow() - $ttl);

    foreach ($mails as $mail) {
      $mail->delete();
    }

    return (count($mails) == 100);
  }

}
