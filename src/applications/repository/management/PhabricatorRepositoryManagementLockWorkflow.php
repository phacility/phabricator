<?php

final class PhabricatorRepositoryManagementLockWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('lock')
      ->setExamples('**lock** [options] __repository__ ...')
      ->setSynopsis(
        pht(
          'Temporarily lock clustered repositories to perform maintenance.'))
      ->setArguments(
        array(
          array(
            'name' => 'repositories',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $repositories = $this->loadRepositories($args, 'repositories');
    if (!$repositories) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more repositories to lock.'));
    }

    foreach ($repositories as $repository) {
      $display_name = $repository->getDisplayName();

      if (!$repository->isHosted()) {
        throw new PhutilArgumentUsageException(
          pht(
            'Unable to lock repository "%s": only hosted repositories may be '.
            'locked.',
            $display_name));
      }

      if (!$repository->supportsSynchronization()) {
        throw new PhutilArgumentUsageException(
          pht(
            'Unable to lock repository "%s": only repositories that support '.
            'clustering may be locked.',
            $display_name));
      }

      if (!$repository->getAlmanacServicePHID()) {
        throw new PhutilArgumentUsageException(
          pht(
            'Unable to lock repository "%s": only clustered repositories '.
            'may be locked.',
            $display_name));
      }
    }

    $diffusion_phid = id(new PhabricatorDiffusionApplication())
      ->getPHID();

    $locks = array();
    foreach ($repositories as $repository) {
      $engine = id(new DiffusionRepositoryClusterEngine())
        ->setViewer($viewer)
        ->setActingAsPHID($diffusion_phid)
        ->setRepository($repository);

      $event = $engine->newMaintenanceEvent();

      $logs = array();
      $logs[] = $engine->newMaintenanceLog();

      $locks[] = array(
        'repository' => $repository,
        'engine' => $engine,
        'event' => $event,
        'logs' => $logs,
      );
    }

    $display_list = new PhutilConsoleList();
    foreach ($repositories as $repository) {
      $display_list->addItem(
        pht(
          '%s %s',
          $repository->getMonogram(),
          $repository->getName()));
    }

    echo tsprintf(
      "%s\n\n%B\n",
      pht('These repositories will be locked:'),
      $display_list->drawConsoleString());

    echo tsprintf(
      "%s\n",
      pht(
        'While the lock is held: users will be unable to write to this '.
        'repository, and you may safely perform working copy maintenance '.
        'on this node in another terminal window.'));

    $query = pht('Lock repositories and begin maintenance?');
    if (!phutil_console_confirm($query)) {
      throw new ArcanistUserAbortException();
    }

    foreach ($locks as $key => $lock) {
      $engine = $lock['engine'];
      $engine->synchronizeWorkingCopyBeforeWrite();
    }

    echo tsprintf(
      "%s\n",
      pht(
        'Repositories are now locked. You may begin maintenance in '.
        'another terminal window. Keep this process running until '.
        'you complete the maintenance, then confirm that you are ready to '.
        'release the locks.'));

    while (!phutil_console_confirm('Ready to release the locks?')) {
      // Wait for the user to confirm that they're ready.
    }

    foreach ($locks as $key => $lock) {
      $lock['event']->saveWithLogs($lock['logs']);

      $engine = $lock['engine'];
      $engine->synchronizeWorkingCopyAfterWrite();
    }

    echo tsprintf(
      "%s\n",
      pht('Done.'));

    return 0;
  }

}
