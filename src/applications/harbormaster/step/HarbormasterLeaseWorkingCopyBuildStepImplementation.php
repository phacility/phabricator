<?php

final class HarbormasterLeaseWorkingCopyBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Lease Working Copy');
  }

  public function getGenericDescription() {
    return pht('Build a working copy in Drydock.');
  }

  public function getBuildStepGroupKey() {
    return HarbormasterDrydockBuildStepGroup::GROUPKEY;
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $settings = $this->getSettings();

    // TODO: We should probably have a separate temporary storage area for
    // execution stuff that doesn't step on configuration state?
    $lease_phid = $build_target->getDetail('exec.leasePHID');

    if ($lease_phid) {
      $lease = id(new DrydockLeaseQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($lease_phid))
        ->executeOne();
      if (!$lease) {
        throw new PhabricatorWorkerPermanentFailureException(
          pht(
            'Lease "%s" could not be loaded.',
            $lease_phid));
      }
    } else {
      $working_copy_type = id(new DrydockWorkingCopyBlueprintImplementation())
        ->getType();

      $allowed_phids = $build_target->getFieldValue('blueprintPHIDs');
      if (!is_array($allowed_phids)) {
        $allowed_phids = array();
      }
      $authorizing_phid = $build_target->getBuildStep()->getPHID();

      $lease = DrydockLease::initializeNewLease()
        ->setResourceType($working_copy_type)
        ->setOwnerPHID($build_target->getPHID())
        ->setAuthorizingPHID($authorizing_phid)
        ->setAllowedBlueprintPHIDs($allowed_phids);

      $map = $this->buildRepositoryMap($build_target);

      $lease->setAttribute('repositories.map', $map);

      $task_id = $this->getCurrentWorkerTaskID();
      if ($task_id) {
        $lease->setAwakenTaskIDs(array($task_id));
      }

      // TODO: Maybe add a method to mark artifacts like this as pending?

      // Create the artifact now so that the lease is always disposed of, even
      // if this target is aborted.
      $build_target->createArtifact(
        $viewer,
        $settings['name'],
        HarbormasterWorkingCopyArtifact::ARTIFACTCONST,
        array(
          'drydockLeasePHID' => $lease->getPHID(),
        ));

      $lease->queueForActivation();

      $build_target
        ->setDetail('exec.leasePHID', $lease->getPHID())
        ->save();
    }

    if ($lease->isActivating()) {
      // TODO: Smart backoff?
      throw new PhabricatorWorkerYieldException(15);
    }

    if (!$lease->isActive()) {
      // TODO: We could just forget about this lease and retry?
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Lease "%s" never activated.',
          $lease->getPHID()));
    }
  }

  public function getArtifactOutputs() {
    return array(
      array(
        'name' => pht('Working Copy'),
        'key' => $this->getSetting('name'),
        'type' => HarbormasterWorkingCopyArtifact::ARTIFACTCONST,
      ),
    );
  }

  public function getFieldSpecifications() {
    return array(
      'name' => array(
        'name' => pht('Artifact Name'),
        'type' => 'text',
        'required' => true,
      ),
      'blueprintPHIDs' => array(
        'name' => pht('Use Blueprints'),
        'type' => 'blueprints',
        'required' => true,
      ),
      'repositoryPHIDs' => array(
        'name' => pht('Also Clone'),
        'type' => 'datasource',
        'datasource.class' => 'DiffusionRepositoryDatasource',
      ),
    );
  }

  private function buildRepositoryMap(HarbormasterBuildTarget $build_target) {
    $viewer = PhabricatorUser::getOmnipotentUser();
    $variables = $build_target->getVariables();

    $repository_phid = idx($variables, 'repository.phid');
    if (!$repository_phid) {
      throw new Exception(
        pht(
          'Unable to determine how to clone the repository for this '.
          'buildable: it is not associated with a tracked repository.'));
    }

    $also_phids = $build_target->getFieldValue('repositoryPHIDs');
    if (!is_array($also_phids)) {
      $also_phids = array();
    }

    $all_phids = $also_phids;
    $all_phids[] = $repository_phid;

    $repositories = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withPHIDs($all_phids)
      ->execute();
    $repositories = mpull($repositories, null, 'getPHID');

    foreach ($all_phids as $phid) {
      if (empty($repositories[$phid])) {
        throw new PhabricatorWorkerPermanentFailureException(
          pht(
            'Unable to load repository with PHID "%s".',
            $phid));
      }
    }

    $map = array();

    foreach ($also_phids as $also_phid) {
      $also_repo = $repositories[$also_phid];
      $map[$also_repo->getCloneName()] = array(
        'phid' => $also_repo->getPHID(),
        'branch' => 'master',
      );
    }

    $repository = $repositories[$repository_phid];

    $commit = idx($variables, 'buildable.commit');
    $ref_uri = idx($variables, 'repository.staging.uri');
    $ref_ref = idx($variables, 'repository.staging.ref');
    if ($commit) {
      $spec = array(
        'commit' => $commit,
      );
    } else if ($ref_uri && $ref_ref) {
      $spec = array(
        'ref' => array(
          'uri' => $ref_uri,
          'ref' => $ref_ref,
        ),
      );
    } else {
      throw new Exception(
        pht(
          'Unable to determine how to fetch changes: this buildable does not '.
          'identify a commit or a staging ref. You may need to configure a '.
          'repository staging area.'));
    }

    $directory = $repository->getCloneName();
    $map[$directory] = array(
      'phid' => $repository->getPHID(),
      'default' => true,
    ) + $spec;

    return $map;
  }

}
