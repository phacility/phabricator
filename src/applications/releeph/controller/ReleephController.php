<?php

abstract class ReleephController extends PhabricatorController {

  private $releephProject;
  private $releephBranch;
  private $releephRequest;

  /**
   * ReleephController will take care of loading any Releeph* objects
   * referenced in the URL.
   */
  public function willProcessRequest(array $data) {
    // Project
    $project = null;
    $project_id = idx($data, 'projectID');
    $project_name = idx($data, 'projectName');
    if ($project_id) {
      $project = id(new ReleephProject())->load($project_id);
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
      $branch = id(new ReleephBranch())->load($branch_id);
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

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName(pht('Releeph'));
    $page->setBaseURI('/releeph/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xD3\x82");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
