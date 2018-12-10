<?php

final class PhabricatorAphlictManagementNotifyWorkflow
  extends PhabricatorAphlictManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('notify')
      ->setSynopsis(pht('Send a notification to a user.'))
      ->setArguments(
        array(
          array(
            'name' => 'user',
            'param' => 'username',
            'help' => pht('User to notify.'),
          ),
          array(
            'name' => 'message',
            'param' => 'text',
            'help' => pht('Message to send.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $username = $args->getArg('user');
    if (!strlen($username)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a user to notify with "--user".'));
    }

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withUsernames(array($username))
      ->executeOne();

    if (!$user) {
      throw new PhutilArgumentUsageException(
        pht(
          'No user with username "%s" exists.',
          $username));
    }

    $message = $args->getArg('message');
    if (!strlen($message)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a message to send with "--message".'));
    }

    $application_phid = id(new PhabricatorNotificationsApplication())
      ->getPHID();

    $content_source = $this->newContentSource();

    $xactions = array();

    $xactions[] = id(new PhabricatorUserTransaction())
      ->setTransactionType(
        PhabricatorUserNotifyTransaction::TRANSACTIONTYPE)
      ->setNewValue($message)
      ->setForceNotifyPHIDs(array($user->getPHID()));

    $editor = id(new PhabricatorUserTransactionEditor())
      ->setActor($viewer)
      ->setActingAsPHID($application_phid)
      ->setContentSource($content_source);

    $editor->applyTransactions($user, $xactions);

    echo tsprintf(
      "%s\n",
      pht('Sent notification.'));

    return 0;
  }

}
