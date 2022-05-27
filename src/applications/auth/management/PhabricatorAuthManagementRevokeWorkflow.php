<?php

final class PhabricatorAuthManagementRevokeWorkflow
  extends PhabricatorAuthManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('revoke')
      ->setExamples(
        "**revoke** --list\n".
        "**revoke** --type __type__ --from __@user__\n".
        "**revoke** --everything --everywhere")
      ->setSynopsis(
        pht(
          'Revoke credentials which may have been leaked or disclosed.'))
      ->setArguments(
        array(
          array(
            'name' => 'from',
            'param' => 'object',
            'help' => pht(
              'Revoke credentials for the specified object. To revoke '.
              'credentials for a user, use "@username".'),
          ),
          array(
            'name' => 'type',
            'param' => 'type',
            'help' => pht('Revoke credentials of the given type.'),
          ),
          array(
            'name' => 'list',
            'help' => pht(
              'List information about available credential revokers.'),
          ),
          array(
            'name' => 'everything',
            'help' => pht('Revoke all credentials types.'),
          ),
          array(
            'name' => 'everywhere',
            'help' => pht('Revoke from all credential owners.'),
          ),
          array(
            'name' => 'force',
            'help' => pht('Revoke credentials without prompting.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $all_types = PhabricatorAuthRevoker::getAllRevokers();
    $is_force = $args->getArg('force');

    // The "--list" flag is compatible with revoker selection flags like
    // "--type" to filter the list, but not compatible with target selection
    // flags like "--from".
    $is_list = $args->getArg('list');

    $type = $args->getArg('type');
    $is_everything = $args->getArg('everything');
    if ($type === null && !$is_everything) {
      if ($is_list) {
        // By default, "bin/revoke --list" implies "--everything".
        $types = $all_types;
      } else {
        throw new PhutilArgumentUsageException(
          pht(
            'Specify the credential type to revoke with "--type" or specify '.
            '"--everything". Use "--list" to list available credential '.
            'types.'));
      }
    } else if (strlen($type) && $is_everything) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify the credential type to revoke with "--type" or '.
          '"--everything", but not both.'));
    } else if ($is_everything) {
      $types = $all_types;
    } else {
      if (empty($all_types[$type])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Credential type "%s" is not valid. Valid credential types '.
            'are: %s.',
            $type,
            implode(', ', array_keys($all_types))));
      }
      $types = array($all_types[$type]);
    }

    $is_everywhere = $args->getArg('everywhere');
    $from = $args->getArg('from');

    if ($is_list) {
      if ($from !== null || $is_everywhere) {
        throw new PhutilArgumentUsageException(
          pht(
            'You can not "--list" and revoke credentials (with "--from" or '.
            '"--everywhere") in the same operation.'));
      }
    }

    if ($is_list) {
      $last_key = last_key($types);
      foreach ($types as $key => $type) {
        echo tsprintf(
          "**%s** (%s)\n\n",
          $type->getRevokerKey(),
          $type->getRevokerName());

        id(new PhutilConsoleBlock())
          ->addParagraph(tsprintf('%B', $type->getRevokerDescription()))
          ->draw();
      }

      return 0;
    }

    $target = null;
    if (!strlen($from) && !$is_everywhere) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify the target to revoke credentials from with "--from" or '.
          'specify "--everywhere".'));
    } else if (strlen($from) && $is_everywhere) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify the target to revoke credentials from with "--from" or '.
          'specify "--everywhere", but not both.'));
    } else if ($is_everywhere) {
      // Just carry the flag through.
    } else {
      $target = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withNames(array($from))
        ->executeOne();
      if (!$target) {
        throw new PhutilArgumentUsageException(
          pht(
            'Target "%s" is not a valid target to revoke credentials from. '.
            'Usually, revoke from "@username".',
            $from));
      }
    }

    if ($is_everywhere && !$is_force) {
      echo id(new PhutilConsoleBlock())
        ->addParagraph(
          pht(
          'You are destroying an entire class of credentials. This may be '.
          'very disruptive to users. You should normally do this only if '.
          'you suspect there has been a widespread compromise which may '.
          'have impacted everyone.'))
        ->drawConsoleString();

      $prompt = pht('Really destroy credentials everywhere?');
      if (!phutil_console_confirm($prompt)) {
        throw new PhutilArgumentUsageException(
          pht('Aborted workflow.'));
      }
    }

    foreach ($types as $type) {
      $type->setViewer($viewer);
      if ($is_everywhere) {
        $count = $type->revokeAllCredentials();
      } else {
        $count = $type->revokeCredentialsFrom($target);
      }

      echo tsprintf(
        "%s\n",
        pht(
          'Destroyed %s credential(s) of type "%s".',
          new PhutilNumber($count),
          $type->getRevokerKey()));

      $guidance = $type->getRevokerNextSteps();
      if ($guidance !== null) {
        echo tsprintf(
          "%s\n",
          $guidance);
      }
    }

    echo tsprintf(
      "%s\n",
      pht('Done.'));

    return 0;
  }

}
