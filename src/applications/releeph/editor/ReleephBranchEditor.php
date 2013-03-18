<?php

final class ReleephBranchEditor extends PhabricatorEditor {

  private $releephProject;
  private $releephBranch;

  public function setReleephProject(ReleephProject $rp) {
    $this->releephProject = $rp;
    return $this;
  }

  public function setReleephBranch(ReleephBranch $branch) {
    $this->releephBranch = $branch;
    return $this;
  }

  public function newBranchFromCommit(PhabricatorRepositoryCommit $cut_point,
                                      $branch_date,
                                      $symbolic_name = null) {

    $template = $this->releephProject->getDetail('branchTemplate');
    if (!$template) {
      $template = ReleephBranchTemplate::getRequiredDefaultTemplate();
    }

    $cut_point_handle = head(
      id(new PhabricatorObjectHandleData(array($cut_point->getPHID())))
        // We'll assume that whoever found the $cut_point has passed privacy
        // checks.
        ->setViewer($this->requireActor())
        ->loadHandles());

    list($name, $errors) = id(new ReleephBranchTemplate())
      ->setCommitHandle($cut_point_handle)
      ->setBranchDate($branch_date)
      ->setReleephProjectName($this->releephProject->getName())
      ->interpolate($template);

    $basename = last(explode('/', $name));

    $table = id(new ReleephBranch());
    $transaction = $table->openTransaction();
    $branch = id(new ReleephBranch())
      ->setName($name)
      ->setBasename($basename)
      ->setReleephProjectID($this->releephProject->getID())
      ->setCreatedByUserPHID($this->requireActor()->getPHID())
      ->setCutPointCommitIdentifier($cut_point->getCommitIdentifier())
      ->setCutPointCommitPHID($cut_point->getPHID())
      ->setIsActive(1)
      ->setDetail('branchDate', $branch_date)
      ->save();

    /**
     * Steal the symbolic name from any other branch that has it (in this
     * project).
     */
    if ($symbolic_name) {
      $others = id(new ReleephBranch())->loadAllWhere(
        'releephProjectID = %d',
        $this->releephProject->getID());
      foreach ($others as $other) {
        if ($other->getSymbolicName() == $symbolic_name) {
          $other
            ->setSymbolicName(null)
            ->save();
        }
      }
      $branch
        ->setSymbolicName($symbolic_name)
        ->save();
    }

    id(new ReleephEvent())
      ->setType(ReleephEvent::TYPE_BRANCH_CREATE)
      ->setActorPHID($this->requireActor()->getPHID())
      ->setReleephProjectID($this->releephProject->getID())
      ->setReleephBranchID($branch->getID())
      ->save();

    $table->saveTransaction();
    return $branch;
  }

  // aka "close" and "reopen"
  public function changeBranchAccess($is_active) {
    $branch = $this->releephBranch;
    $branch->openTransaction();

    $branch
      ->setIsActive((int)$is_active)
      ->save();

    id(new ReleephEvent())
      ->setType(ReleephEvent::TYPE_BRANCH_ACCESS)
      ->setActorPHID($this->requireActor()->getPHID())
      ->setReleephProjectID($branch->getReleephProjectID())
      ->setReleephBranchID($branch->getID())
      ->setDetail('isActive', $is_active)
      ->save();

    $branch->saveTransaction();
  }

}
