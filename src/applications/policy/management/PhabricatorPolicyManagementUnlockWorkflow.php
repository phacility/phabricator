<?php

final class PhabricatorPolicyManagementUnlockWorkflow
  extends PhabricatorPolicyManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('unlock')
      ->setSynopsis(
        pht(
          'Unlock an object by setting its policies to allow anyone to view '.
          'and edit it.'))
      ->setExamples('**unlock** D123')
      ->setArguments(
        array(
          array(
            'name'      => 'objects',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $viewer = $this->getViewer();

    $obj_names = $args->getArg('objects');
    if (!$obj_names) {
      throw new PhutilArgumentUsageException(
        pht('Specify the name of an object to unlock.'));
    } else if (count($obj_names) > 1) {
      throw new PhutilArgumentUsageException(
        pht('Specify the name of exactly one object to unlock.'));
    }

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames($obj_names)
      ->executeOne();

    if (!$object) {
      $name = head($obj_names);
      throw new PhutilArgumentUsageException(
        pht("No such object '%s'!", $name));
    }

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object->getPHID()))
      ->executeOne();

    if ($object instanceof PhabricatorApplication) {
      $application = $object;

      $console->writeOut(
        "%s\n",
        pht('Unlocking Application: %s', $handle->getFullName()));

      // For applications, we can't unlock them in a normal way and don't want
      // to unlock every capability, just view and edit.
      $capabilities = array(
        PhabricatorPolicyCapability::CAN_VIEW,
        PhabricatorPolicyCapability::CAN_EDIT,
      );

      $key = 'phabricator.application-settings';
      $config_entry = PhabricatorConfigEntry::loadConfigEntry($key);
      $value = $config_entry->getValue();

      foreach ($capabilities as $capability) {
        if ($application->isCapabilityEditable($capability)) {
          unset($value[$application->getPHID()]['policy'][$capability]);
        }
      }

      $config_entry->setValue($value);
      $config_entry->save();

      $console->writeOut("%s\n", pht('Saved application.'));

      return 0;
    }

    $console->writeOut("%s\n", pht('Unlocking: %s', $handle->getFullName()));

    $updated = false;
    foreach ($object->getCapabilities() as $capability) {
      switch ($capability) {
        case PhabricatorPolicyCapability::CAN_VIEW:
          try {
            $object->setViewPolicy(PhabricatorPolicies::POLICY_USER);
            $console->writeOut("%s\n", pht('Unlocked view policy.'));
            $updated = true;
          } catch (Exception $ex) {
            $console->writeOut("%s\n", pht('View policy is not mutable.'));
          }
          break;
        case PhabricatorPolicyCapability::CAN_EDIT:
          try {
            $object->setEditPolicy(PhabricatorPolicies::POLICY_USER);
            $console->writeOut("%s\n", pht('Unlocked edit policy.'));
            $updated = true;
          } catch (Exception $ex) {
            $console->writeOut("%s\n", pht('Edit policy is not mutable.'));
          }
          break;
        case PhabricatorPolicyCapability::CAN_JOIN:
          try {
            $object->setJoinPolicy(PhabricatorPolicies::POLICY_USER);
            $console->writeOut("%s\n", pht('Unlocked join policy.'));
            $updated = true;
          } catch (Exception $ex) {
            $console->writeOut("%s\n", pht('Join policy is not mutable.'));
          }
          break;
      }
    }

    if ($updated) {
      $object->save();
      $console->writeOut("%s\n", pht('Saved object.'));
    } else {
      $console->writeOut(
        "%s\n",
        pht(
          'Object has no mutable policies. Try unlocking parent/container '.
          'object instead. For example, to gain access to a commit, unlock '.
          'the repository it belongs to.'));
    }
  }

}
