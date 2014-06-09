<?php

abstract class DifferentialLandingStrategy {

  public abstract function processLandRequest(
    AphrontRequest $request,
    DifferentialRevision $revision,
    PhabricatorRepository $repository);

  /**
   * returns PhabricatorActionView or null.
   */
  abstract function createMenuItem(
    PhabricatorUser $viewer,
    DifferentialRevision $revision,
    PhabricatorRepository $repository);

  /**
   * returns PhabricatorActionView which can be attached to the revision view.
   */
  protected function createActionView($revision, $name) {
    $strategy = get_class($this);
    $revision_id = $revision->getId();
    return id(new PhabricatorActionView())
      ->setRenderAsForm(true)
      ->setWorkflow(true)
      ->setName($name)
      ->setHref("/differential/revision/land/{$revision_id}/{$strategy}/");
  }

  /**
   * Check if this action should be disabled, and explain why.
   *
   * By default, this method checks for push permissions, and for the
   * revision being Accepted.
   *
   * @return FALSE for "not disabled";
   *         Human-readable text explaining why, if it is disabled;
   */
  public function isActionDisabled(
    PhabricatorUser $viewer,
    DifferentialRevision $revision,
    PhabricatorRepository $repository) {

    $status = $revision->getStatus();
    if ($status != ArcanistDifferentialRevisionStatus::ACCEPTED) {
      return pht('Only Accepted revisions can be landed.');
    }

    if (!PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $repository,
        DiffusionCapabilityPush::CAPABILITY)) {
      return pht('You do not have permissions to push to this repository.');
    }

    return false;
  }

  /**
   * might break if repository is not Git.
   */
  protected function getGitWorkspace(PhabricatorRepository $repository) {
    try {
        return DifferentialGetWorkingCopy::getCleanGitWorkspace($repository);
    } catch (Exception $e) {
      throw new PhutilProxyException(
        'Failed to allocate a workspace',
        $e);
    }
  }

  /**
   * might break if repository is not Mercurial.
   */
  protected function getMercurialWorkspace(PhabricatorRepository $repository) {
    try {
      return DifferentialGetWorkingCopy::getCleanMercurialWorkspace(
        $repository);
    } catch (Exception $e) {
      throw new PhutilProxyException(
        'Failed to allocate a workspace',
        $e);
    }
  }
}
