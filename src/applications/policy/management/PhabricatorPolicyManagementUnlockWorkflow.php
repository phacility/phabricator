<?php

final class PhabricatorPolicyManagementUnlockWorkflow
  extends PhabricatorPolicyManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('unlock')
      ->setSynopsis(
        pht(
          'Unlock one or more objects by changing their view policies, edit '.
          'policies, or owners.'))
      ->setHelp(
        pht(
          'Identify each __object__ by passing an object name '.
          '(like "T123") or a PHID (like "PHID-ABCD-1234...").'.
          "\n\n".
          'Not every type of object has an editable view policy, edit '.
          'policy, or owner, so not all modes will work with all objects. '))
      ->setExamples('**unlock** --view __user__ __object__ ...')
      ->setArguments(
        array(
          array(
            'name' => 'view',
            'param' => 'username',
            'help' => pht(
              'Change the view policy of an object so that the specified '.
              'user may view it.'),
          ),
          array(
            'name' => 'edit',
            'param' => 'username',
            'help' => pht(
              'Change the edit policy of an object so that the specified '.
              'user may edit it.'),
          ),
          array(
            'name' => 'owner',
            'param' => 'username',
            'help' => pht(
              'Change the owner of an object to the specified user.'),
          ),
          array(
            'name' => 'objects',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $object_names = $args->getArg('objects');
    if (!$object_names) {
      throw new PhutilArgumentUsageException(
        pht('Specify the name of an object to unlock.'));
    } else if (count($object_names) > 1) {
      throw new PhutilArgumentUsageException(
        pht('Specify the name of exactly one object to unlock.'));
    }

    $object_name = head($object_names);

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames(array($object_name))
      ->executeOne();
    if (!$object) {
      throw new PhutilArgumentUsageException(
        pht(
          'Unable to find any object with the specified name ("%s").',
          $object_name));
    }

    $view_user = $this->loadUser($args->getArg('view'));
    $edit_user = $this->loadUser($args->getArg('edit'));
    $owner_user = $this->loadUser($args->getArg('owner'));

    if (!$view_user && !$edit_user && !$owner_user) {
      throw new PhutilArgumentUsageException(
        pht(
          'Choose which capabilities to unlock with "--view", "--edit", '.
          'or "--owner".'));
    }

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object->getPHID()))
      ->executeOne();

    echo tsprintf(
      "<bg:blue>** %s **</bg> %s\n",
      pht('UNLOCKING'),
      pht('Unlocking: %s', $handle->getFullName()));

    $engine = PhabricatorUnlockEngine::newUnlockEngineForObject($object);

    $xactions = array();
    if ($view_user) {
      $xactions[] = $engine->newUnlockViewTransactions($object, $view_user);
    }
    if ($edit_user) {
      $xactions[] = $engine->newUnlockEditTransactions($object, $edit_user);
    }
    if ($owner_user) {
      $xactions[] = $engine->newUnlockOwnerTransactions($object, $owner_user);
    }
    $xactions = array_mergev($xactions);

    $policy_application = new PhabricatorPolicyApplication();
    $content_source = $this->newContentSource();

    $editor = $object->getApplicationTransactionEditor()
      ->setActor($viewer)
      ->setActingAsPHID($policy_application->getPHID())
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true)
      ->setContentSource($content_source);

    $editor->applyTransactions($object, $xactions);

    echo tsprintf(
      "<bg:green>** %s **</bg> %s\n",
      pht('UNLOCKED'),
      pht('Modified object policies.'));

    $uri = $handle->getURI();
    if (strlen($uri)) {
      echo tsprintf(
        "\n        **%s**: __%s__\n\n",
        pht('Object URI'),
        PhabricatorEnv::getURI($uri));
    }

    return 0;
  }

  private function loadUser($username) {
    $viewer = $this->getViewer();

    if ($username === null) {
      return null;
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

    return $user;
  }

}
