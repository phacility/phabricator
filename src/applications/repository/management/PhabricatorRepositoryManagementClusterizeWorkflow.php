<?php

final class PhabricatorRepositoryManagementClusterizeWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('clusterize')
      ->setExamples('**clusterize** [options] __repository__ ...')
      ->setSynopsis(
        pht('Convert existing repositories into cluster repositories.'))
      ->setArguments(
        array(
          array(
            'name' => 'service',
            'param' => 'service',
            'help' => pht(
              'Cluster repository service in Almanac to move repositories '.
              'into.'),
          ),
          array(
            'name' => 'remove-service',
            'help' => pht('Take repositories out of a cluster.'),
          ),
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
        pht('Specify one or more repositories to clusterize.'));
    }

    $service_name = $args->getArg('service');
    $remove_service = $args->getArg('remove-service');

    if ($remove_service && $service_name) {
      throw new PhutilArgumentUsageException(
        pht('Specify --service or --remove-service, but not both.'));
    }

    if (!$service_name && !$remove_service) {
      throw new PhutilArgumentUsageException(
        pht('Specify --service or --remove-service.'));
    }

    if ($remove_service) {
      $service = null;
    } else {
      $service = id(new AlmanacServiceQuery())
        ->setViewer($viewer)
        ->withNames(array($service_name))
        ->withServiceTypes(
          array(
            AlmanacClusterRepositoryServiceType::SERVICETYPE,
          ))
        ->needBindings(true)
        ->executeOne();
      if (!$service) {
        throw new PhutilArgumentUsageException(
          pht(
            'No repository service "%s" exists.',
            $service_name));
      }
    }

    if ($service) {
      $service_phid = $service->getPHID();

      $bindings = $service->getActiveBindings();

      $unique_devices = array();
      foreach ($bindings as $binding) {
        $unique_devices[$binding->getDevicePHID()] = $binding->getDevice();
      }

      if (count($unique_devices) > 1) {
        $device_names = mpull($unique_devices, 'getName');

        echo id(new PhutilConsoleBlock())
          ->addParagraph(
            pht(
              'Service "%s" is actively bound to more than one device (%s).',
              $service_name,
              implode(', ', $device_names)))
          ->addParagraph(
            pht(
              'If you clusterize a repository onto this service it may be '.
              'unclear which devices have up-to-date copies of the '.
              'repository. If so, leader/follower ambiguity will freeze the '.
              'repository. You may need to manually promote a device to '.
              'unfreeze it. See "Ambiguous Leaders" in the documentation '.
              'for discussion.'))
          ->drawConsoleString();

        $prompt = pht('Continue anyway?');
        if (!phutil_console_confirm($prompt)) {
          throw new PhutilArgumentUsageException(
            pht('User aborted the workflow.'));
        }
      }
    } else {
      $service_phid = null;
    }

    $content_source = $this->newContentSource();
    $diffusion_phid = id(new PhabricatorDiffusionApplication())->getPHID();

    foreach ($repositories as $repository) {
      $xactions = array();

      $xactions[] = id(new PhabricatorRepositoryTransaction())
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_SERVICE)
        ->setNewValue($service_phid);

      id(new PhabricatorRepositoryEditor())
        ->setActor($viewer)
        ->setActingAsPHID($diffusion_phid)
        ->setContentSource($content_source)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($repository, $xactions);

      if ($service) {
        echo tsprintf(
          "%s\n",
          pht(
            'Moved repository "%s" to cluster service "%s".',
            $repository->getDisplayName(),
            $service->getName()));
      } else {
        echo tsprintf(
          "%s\n",
          pht(
            'Removed repository "%s" from cluster service.',
            $repository->getDisplayName()));
      }
    }

    return 0;
  }

}
