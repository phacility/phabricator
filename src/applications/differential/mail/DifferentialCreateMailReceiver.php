<?php

final class DifferentialCreateMailReceiver extends PhabricatorMailReceiver {

  public function isEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorDifferentialApplication');
  }

  public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail) {
    $differential_app = new PhabricatorDifferentialApplication();
    return $this->canAcceptApplicationMail($differential_app, $mail);
  }

  protected function processReceivedMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {

    $attachments = $mail->getAttachments();
    $files = array();
    $errors = array();
    if ($attachments) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer($sender)
        ->withPHIDs($attachments)
        ->execute();
      foreach ($files as $index => $file) {
        if ($file->getMimeType() != 'text/plain') {
          $errors[] = pht(
            'Could not parse file %s; only files with mimetype text/plain '.
            'can be parsed via email.',
            $file->getName());
          unset($files[$index]);
        }
      }
    }

    $diffs = array();
    foreach ($files as $file) {
      $call = new ConduitCall(
        'differential.createrawdiff',
        array(
          'diff' => $file->loadFileData(),
        ));
      $call->setUser($sender);
      try {
        $result = $call->execute();
        $diffs[$file->getName()] = $result['uri'];
      } catch (Exception $e) {
        $errors[] = pht(
          'Could not parse attachment %s; only attachments (and mail bodies) '.
          'generated via "diff" commands can be parsed.',
          $file->getName());
      }
    }

    $body = $mail->getCleanTextBody();
    if ($body) {
      $call = new ConduitCall(
        'differential.createrawdiff',
        array(
          'diff' => $body,
        ));
      $call->setUser($sender);
      try {
        $result = $call->execute();
        $diffs[pht('Mail Body')] = $result['uri'];
      } catch (Exception $e) {
        $errors[] = pht(
          'Could not parse mail body; only mail bodies (and attachments) '.
          'generated via "diff" commands can be parsed.');
      }
    }

    $subject_prefix =
      PhabricatorEnv::getEnvConfig('metamta.differential.subject-prefix');
    if (count($diffs)) {
      $subject = pht(
        'You successfully created %d diff(s).',
        count($diffs));
    } else {
      $subject = pht(
        'Diff creation failed; see body for %s error(s).',
        phutil_count($errors));
    }
    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection($subject);
    if (count($diffs)) {
      $text_body = '';
      $html_body = array();
      $body_label = pht('%s DIFF LINK(S)', phutil_count($diffs));
      foreach ($diffs as $filename => $diff_uri) {
        $text_body .= $filename.': '.$diff_uri."\n";
        $html_body[] = phutil_tag(
          'a',
          array(
            'href' => $diff_uri,
          ),
          $filename);
        $html_body[] = phutil_tag('br');
      }
      $body->addTextSection($body_label, $text_body);
      $body->addHTMLSection($body_label, $html_body);
    }

    if (count($errors)) {
      $body_section = new PhabricatorMetaMTAMailSection();
      $body_label = pht('%s ERROR(S)', phutil_count($errors));
      foreach ($errors as $error) {
        $body_section->addFragment($error);
      }
      $body->addTextSection($body_label, $body_section);
    }

    id(new PhabricatorMetaMTAMail())
      ->addTos(array($sender->getPHID()))
      ->setSubject($subject)
      ->setSubjectPrefix($subject_prefix)
      ->setFrom($sender->getPHID())
      ->setBody($body->render())
      ->saveAndSend();
  }

}
