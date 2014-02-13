<?php

abstract class ReleephProjectController extends ReleephController {

  private $releephProject;
  private $releephBranch;
  private $releephRequest;

  /**
   * ReleephController will take care of loading any Releeph* objects
   * referenced in the URL.
   */
  public function willProcessRequest(array $data) {
    $viewer = $this->getRequest()->getUser();

    // Project
    $project = null;
    $project_id = idx($data, 'projectID');
    $project_name = idx($data, 'projectName');
    if ($project_id) {
      $project = id(new ReleephProjectQuery())
        ->setViewer($viewer)
        ->withIDs(array($project_id))
        ->executeOne();
      if (!$project) {
        throw new Exception(
          "ReleephProject with id '{$project_id}' not found!");
      }
    } elseif ($project_name) {
      $project = id(new ReleephProject())
        ->loadOneWhere('name = %s', $project_name);
      if (!$project) {
        throw new Exception(
          "ReleephProject with name '{$project_name}' not found!");
      }
    }

    // Branch
    $branch = null;
    $branch_id = idx($data, 'branchID');
    $branch_name = idx($data, 'branchName');
    if ($branch_id) {
      $branch = id(new ReleephBranchQuery())
        ->setViewer($viewer)
        ->withIDs(array($branch_id))
        ->executeOne();
      if (!$branch) {
        throw new Exception("Branch with id '{$branch_id}' not found!");
      }
    } elseif ($branch_name) {
      if (!$project) {
        throw new Exception(
          "You cannot refer to a branch by name without also referring ".
          "to a ReleephProject (branch names are only unique in projects).");
      }
      $branch = id(new ReleephBranch())->loadOneWhere(
        'basename = %s AND releephProjectID = %d',
        $branch_name,
        $project->getID());
      if (!$branch) {
        throw new Exception(
          "ReleephBranch with basename '{$branch_name}' not found ".
          "in project '{$project->getName()}'!");
      }
      // Do the branch query again, properly, to hit policies and load attached
      // data.
      // TODO: Clean this up with T3657.
      $branch = id(new ReleephBranchQuery())
        ->setViewer($viewer)
        ->withIDs(array($branch->getID()))
        ->executeOne();
      if (!$branch) {
        throw new Exception('404!');
      }
    }

    // Request
    $request = null;
    $request_id = idx($data, 'requestID');
    if ($request_id) {
      $request = id(new ReleephRequest())->load($request_id);
      if (!$request) {
        throw new Exception(
          "ReleephRequest with id '{$request_id}' not found!");
      }
    }

    // Fill in the gaps
    if ($request && !$branch) {
      $branch = $request->loadReleephBranch();
    }

    if ($branch && !$project) {
      $project = $branch->loadReleephProject();
    }

    // Set!
    $this->releephProject = $project;
    $this->releephBranch = $branch;
    $this->releephRequest = $request;
  }

  protected function getReleephProject() {
    if (!$this->releephProject) {
      throw new Exception(
        'This controller did not load a ReleephProject from the URL $data.');
    }
    return $this->releephProject;
  }

  protected function getReleephBranch() {
    if (!$this->releephBranch) {
      throw new Exception(
        'This controller did not load a ReleephBranch from the URL $data.');
    }
    return $this->releephBranch;
  }

  protected function getReleephRequest() {
    if (!$this->releephRequest) {
      throw new Exception(
        'This controller did not load a ReleephRequest from the URL $data.');
    }
    return $this->releephRequest;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    // TODO: The massive amount of derps here should be fixed once URIs get
    // sorted out; see T3657.

    try {
      $project = $this->getReleephProject();
      $project_id = $project->getID();
      $project_uri = $this->getApplicationURI("project/{$project_id}/");

      $crumbs->addTextCrumb($project->getName(), $project_uri);
    } catch (Exception $ex) {
      // TODO: This is derps.
    }

    try {
      $branch = $this->getReleephBranch();
      $branch_uri = $branch->getURI();
      $crumbs->addTextCrumb($branch->getDisplayNameWithDetail(), $branch_uri);
    } catch (Exception $ex) {
      // TODO: This is also derps.
    }

    return $crumbs;
  }


}
