<?php

// TODO: After T2222, this is likely unreachable?

final class ReleephRequestDifferentialCreateController
  extends ReleephController {

  private $revision;

  public function handleRequest(AphrontRequest $request) {
    $revision_id = $request->getURIData('diffRevID');
    $viewer = $request->getViewer();

    $diff_rev = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($revision_id))
      ->executeOne();
    if (!$diff_rev) {
      return new Aphront404Response();
    }
    $this->revision = $diff_rev;

    $repository = $this->revision->getRepository();

    $projects = id(new ReleephProject())->loadAllWhere(
      'repositoryPHID = %s AND isActive = 1',
      $repository->getPHID());
    if (!$projects) {
      throw new Exception(
        pht(
          "%s belongs to the '%s' repository, ".
          "which is not part of any Releeph project!",
          'D'.$this->revision->getID(),
          $repository->getMonogram()));
    }

    $branches = id(new ReleephBranch())->loadAllWhere(
      'releephProjectID IN (%Ld) AND isActive = 1',
      mpull($projects, 'getID'));
    if (!$branches) {
      throw new Exception(pht(
        '%s could be in the Releeph project(s) %s, '.
        'but this project / none of these projects have open branches.',
        'D'.$this->revision->getID(),
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
      ->setUser($viewer)
      ->setTitle(pht('Choose Releeph Branch'))
      ->setClass('releeph-request-differential-create-dialog')
      ->addCancelButton('/D'.$request->getStr('D'));

    $dialog->appendChild(
      pht(
        'This differential revision changes code that is associated '.
        'with multiple Releeph branches. Please select the branch '.
        'where you would like this code to be picked.'));

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

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }

  private function buildReleephRequestURI(ReleephBranch $branch) {
    $uri = $branch->getURI('request/');
    return id(new PhutilURI($uri))
      ->setQueryParam('D', $this->revision->getID());
  }

}
