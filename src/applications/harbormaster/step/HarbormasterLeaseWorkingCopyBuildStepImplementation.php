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
    return HarbormasterPrototypeBuildStepGroup::GROUPKEY;
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

      $lease = id(new DrydockLease())
        ->setResourceType($working_copy_type)
        ->setOwnerPHID($build_target->getPHID());

      $variables = $build_target->getVariables();

      $repository_phid = idx($variables, 'repository.phid');
      $commit = idx($variables, 'repository.commit');

      $lease
        ->setAttribute('repositoryPHID', $repository_phid)
        ->setAttribute('commit', $commit);

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

    $artifact = $build_target->createArtifact(
      $viewer,
      $settings['name'],
      HarbormasterWorkingCopyArtifact::ARTIFACTCONST,
      array(
        'drydockLeasePHID' => $lease->getPHID(),
      ));
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
    );
  }

}
