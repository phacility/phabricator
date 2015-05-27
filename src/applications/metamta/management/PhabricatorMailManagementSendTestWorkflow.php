<?php

final class PhabricatorMailManagementSendTestWorkflow
  extends PhabricatorMailManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('send-test')
      ->setSynopsis(
        pht(
          'Simulate sending mail. This may be useful to test your mail '.
          'configuration, or while developing new mail adapters.'))
      ->setExamples('**send-test** --to alincoln --subject hi < body.txt')
      ->setArguments(
        array(
          array(
            'name'    => 'from',
            'param'   => 'user',
            'help'    => pht('Send mail from the specified user.'),
          ),
          array(
            'name'    => 'to',
            'param'   => 'user',
            'help'    => pht('Send mail "To:" the specified users.'),
            'repeat'  => true,
          ),
          array(
            'name'    => 'cc',
            'param'   => 'user',
            'help'    => pht('Send mail which "Cc:"s the specified users.'),
            'repeat'  => true,
          ),
          array(
            'name'    => 'subject',
            'param'   => 'text',
            'help'    => pht('Use the provided subject.'),
          ),
          array(
            'name'    => 'tag',
            'param'   => 'text',
            'help'    => pht('Add the given mail tags.'),
            'repeat'  => true,
          ),
          array(
            'name'    => 'attach',
            'param'   => 'file',
            'help'    => pht('Attach a file.'),
            'repeat'  => true,
          ),
          array(
            'name'    => 'html',
            'help'    => pht('Send as HTML mail.'),
          ),
          array(
            'name'    => 'bulk',
            'help'    => pht('Send with bulk headers.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $viewer = $this->getViewer();

    $from = $args->getArg('from');
    if ($from) {
      $user = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withUsernames(array($from))
        ->executeOne();
      if (!$user) {
        throw new PhutilArgumentUsageException(
          pht("No such user '%s' exists.", $from));
      }
      $from = $user;
    }

    $tos = $args->getArg('to');
    $ccs = $args->getArg('cc');

    if (!$tos && !$ccs) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify one or more users to send mail to with `%s` and `%s`.',
          '--to',
          '--cc'));
    }

    $names = array_merge($tos, $ccs);
    $users = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withUsernames($names)
      ->execute();
    $users = mpull($users, null, 'getUsername');

    foreach ($tos as $key => $username) {
      if (empty($users[$username])) {
        throw new PhutilArgumentUsageException(
          pht("No such user '%s' exists.", $username));
      }
      $tos[$key] = $users[$username]->getPHID();
    }

    foreach ($ccs as $key => $username) {
      if (empty($users[$username])) {
        throw new PhutilArgumentUsageException(
          pht("No such user '%s' exists.", $username));
      }
      $ccs[$key] = $users[$username]->getPHID();
    }

    $subject = $args->getArg('subject');
    if ($subject === null) {
      $subject = pht('No Subject');
    }

    $tags = $args->getArg('tag');
    $attach = $args->getArg('attach');
    $is_bulk = $args->getArg('bulk');

    $console->writeErr("%s\n", pht('Reading message body from stdin...'));
    $body = file_get_contents('php://stdin');

    $mail = id(new PhabricatorMetaMTAMail())
      ->addTos($tos)
      ->addCCs($ccs)
      ->setSubject($subject)
      ->setBody($body)
      ->setIsBulk($is_bulk)
      ->setMailTags($tags);

    if ($args->getArg('html')) {
      $mail->setBody(
        pht(
          '(This is a placeholder plaintext email body for a test message '.
          'sent with %s.)',
          '--html'));

      $mail->setHTMLBody($body);
    } else {
      $mail->setBody($body);
    }

    if ($from) {
      $mail->setFrom($from->getPHID());
    }

    foreach ($attach as $attachment) {
      $data = Filesystem::readFile($attachment);
      $name = basename($attachment);
      $mime = Filesystem::getMimeType($attachment);
      $file = new PhabricatorMetaMTAAttachment($data, $name, $mime);
      $mail->addAttachment($file);
    }

    PhabricatorWorker::setRunAllTasksInProcess(true);
    $mail->save();

    $console->writeErr(
      "%s\n\n    phabricator/ $ ./bin/mail show-outbound --id %d\n\n",
      pht('Mail sent! You can view details by running this command:'),
      $mail->getID());
  }

}
