<?php

abstract class PhabricatorProjectTypeTransaction
  extends PhabricatorProjectTransactionType {

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if (!$xactions) {
      return $errors;
    }

    $xaction = last($xactions);

    $parent_phid = $xaction->getNewValue();
    if (!$parent_phid) {
      return $errors;
    }

    if (!$this->getEditor()->getIsNewObject()) {
      $errors[] = $this->newInvalidError(
        pht(
          'You can only set a parent or milestone project when creating a '.
          'project for the first time.'));
      return $errors;
    }

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($this->getActor())
      ->withPHIDs(array($parent_phid))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();
    if (!$projects) {
      $errors[] = $this->newInvalidError(
        pht(
          'Parent or milestone project PHID ("%s") must be the PHID of a '.
          'valid, visible project which you have permission to edit.',
          $parent_phid));
      return $errors;
    }

    $project = head($projects);

    if ($project->isMilestone()) {
      $errors[] = $this->newInvalidError(
        pht(
          'Parent or milestone project PHID ("%s") must not be a '.
          'milestone. Milestones may not have subprojects or milestones.',
          $parent_phid));
      return $errors;
    }

    $limit = PhabricatorProject::getProjectDepthLimit();
    if ($project->getProjectDepth() >= ($limit - 1)) {
      $errors[] = $this->newInvalidError(
        pht(
          'You can not create a subproject or milestone under this parent '.
          'because it would nest projects too deeply. The maximum '.
          'nesting depth of projects is %s.',
          new PhutilNumber($limit)));
      return $errors;
    }

    return $errors;
  }

}
