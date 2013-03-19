<?php

final class ReleephRequestDifferentialCreateController
  extends ReleephController {

  private $revision;

  public function willProcessRequest(array $data) {
    $diff_rev_id = $data['diffRevID'];
    $diff_rev = id(new DifferentialRevision())->load($diff_rev_id);
    if (!$diff_rev) {
      throw new Exception(sprintf('D%d not found!', $diff_rev_id));
    }
    $this->revision = $diff_rev;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $arc_project = id(new PhabricatorRepositoryArcanistProject())
      ->loadOneWhere('phid = %s', $this->revision->getArcanistProjectPHID());

    $projects = id(new ReleephProject())->loadAllWhere(
      'arcanistProjectID = %d AND isActive = 1',
      $arc_project->getID());
    if (!$projects) {
      throw new ReleephRequestException(sprintf(
        "D%d belongs to the '%s' Arcanist project, ".
        "which is not part of any Releeph project!",
        $this->revision->getID(),
        $arc_project->getName()));
    }

    $branches = id(new ReleephBranch())->loadAllWhere(
      'releephProjectID IN (%Ld) AND isActive = 1',
      mpull($projects, 'getID'));
    if (!$branches) {
      throw new ReleephRequestException(sprintf(
        "D%d could be in the Releeph project(s) %s, ".
        "but this project / none of these projects have open branches.",
        $this->revision->getID(),
        implode(', ', mpull($projects, 'getName'))));
    }

    if (count($branches) === 1) {
      return id(new AphrontRedirectResponse())
        ->setURI($this->buildReleephRequestURI(head($branches)));
    }

    $projects = msort(
      mpull($projects, null, 'getID'),
      'getName');

    $branch_groups = mgroup($branches, 'getReleephProjectID');

    require_celerity_resource('releeph-request-differential-create-dialog');
    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle('Choose Releeph Branch')
      ->setClass('releeph-request-differential-create-dialog')
      ->addCancelButton('/D'.$request->getStr('D'));

    $dialog->appendChild(
      "This differential revision changes code that is associated ".
      "with multiple Releeph branches.  ".
      "Please select the branch where you would like this code to be picked.");

    foreach ($branch_groups as $project_id => $branches) {
      $project = idx($projects, $project_id);
      $dialog->appendChild(
        phutil_tag(
          'h1',
          array(),
          $project->getName()));
      $branches = msort($branches, 'getBasename');
      foreach ($branches as $branch) {
        $uri = $this->buildReleephRequestURI($branch);
        $dialog->appendChild(
          phutil_tag(
            'a',
            array(
              'href' => $uri,
            ),
            $branch->getDisplayNameWithDetail()));
      }
    }

    return id(new AphrontDialogResponse)
      ->setDialog($dialog);
  }

  private function buildReleephRequestURI(ReleephBranch $branch) {
    return id(new PhutilURI('/releeph/request/create/'))
      ->setQueryParam('branchID', $branch->getID())
      ->setQueryParam('D', $this->revision->getID());
  }

}
