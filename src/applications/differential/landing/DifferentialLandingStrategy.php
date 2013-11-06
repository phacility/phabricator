<?php

abstract class DifferentialLandingStrategy {

  public abstract function processLandRequest(
    AphrontRequest $request,
    DifferentialRevision $revision,
    PhabricatorRepository $repository);

  /**
   * returns PhabricatorActionView or an array of PhabricatorActionView or null.
   */
  abstract function createMenuItems(
    PhabricatorUser $viewer,
    DifferentialRevision $revision,
    PhabricatorRepository $repository);

  /**
   * returns PhabricatorActionView which can be attached to the revision view.
   */
  protected function createActionView($revision, $name, $disabled = false) {
    $strategy = get_class($this);
    $revision_id = $revision->getId();
    return id(new PhabricatorActionView())
      ->setRenderAsForm(true)
      ->setName($name)
      ->setHref("/differential/revision/land/{$revision_id}/{$strategy}/")
      ->setDisabled($disabled);
  }

  /**
   * might break if repository is not Git.
   */
  protected function getGitWorkspace(PhabricatorRepository $repository) {
    try {
        return DifferentialGetWorkingCopy::getCleanGitWorkspace($repository);
    } catch (Exception $e) {
      throw new PhutilProxyException (
        'Failed to allocate a workspace',
        $e);
    }
  }
}
