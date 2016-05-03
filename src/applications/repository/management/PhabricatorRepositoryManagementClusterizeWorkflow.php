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
