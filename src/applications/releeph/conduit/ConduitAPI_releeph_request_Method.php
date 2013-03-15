<?php

final class ConduitAPI_releeph_request_Method
  extends ConduitAPI_releeph_Method {

  public function getMethodDescription() {
    return "Request a commit or diff to be picked to a branch.";
  }

  public function defineParamTypes() {
    return array(
      'branchPHID'  => 'required string',
      'things'      => 'required string',
      'fields'      => 'dict<string, string>',
    );
  }

  public function defineReturnType() {
    return 'dict<string, wild>';
  }

  public function defineErrorTypes() {
    return array(
      "ERR_BRANCH"      => 'Unknown Releeph branch.',
      "ERR_FIELD_PARSE" => 'Unable to parse a Releeph field.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $branch_phid = $request->getValue('branchPHID');
    $releeph_branch = id(new ReleephBranch())
      ->loadOneWhere('phid = %s', $branch_phid);

    if (!$releeph_branch) {
      throw id(new ConduitException("ERR_BRANCH"))->setErrorDescription(
        "No ReleephBranch found with PHID {$branch_phid}!");
    }

    $releeph_project = $releeph_branch->loadReleephProject();

    // Find the requested commit identifiers
    $requested_commits = array();
    $things = $request->getValue('things');
    $finder = id(new ReleephCommitFinder())
      ->setReleephProject($releeph_project);
    foreach ($things as $thing) {
      try {
        $requested_commits[$thing] = $finder->fromPartial($thing);
      } catch (ReleephCommitFinderException $ex) {
        throw id(new ConduitException('ERR_NO_MATCHES'))
          ->setErrorDescription($ex->getMessage());
      }
    }

    // Find any existing requests that clash on the commit id, for this branch
    $existing_releeph_requests = id(new ReleephRequest())->loadAllWhere(
      'requestCommitPHID IN (%Ls) AND branchID = %d',
      mpull($requested_commits, 'getPHID'),
      $releeph_branch->getID());
    $existing_releeph_requests = mpull(
      $existing_releeph_requests,
      null,
      'getRequestCommitPHID');

    $selector = $releeph_project->getReleephFieldSelector();
    $fields = $selector->getFieldSpecifications();
    foreach ($fields as $field) {
      $field
        ->setReleephProject($releeph_project)
        ->setReleephBranch($releeph_branch);
    }

    $results = array();
    foreach ($requested_commits as $thing => $commit) {
      $phid = $commit->getPHID();
      $handles = id(new PhabricatorObjectHandleData(array($phid)))
        ->setViewer($request->getUser())
        ->loadHandles();
      $name = id($handles[$phid])->getName();

      $releeph_request = null;

      $existing_releeph_request = idx($existing_releeph_requests, $phid);
      if ($existing_releeph_request) {
        $releeph_request = $existing_releeph_request;
      } else {
        $releeph_request = new ReleephRequest();
        foreach ($fields as $field) {
          if (!$field->isEditable()) {
            continue;
          }
          $field->setReleephRequest($releeph_request);
          try {
            $field->setValueFromConduitAPIRequest($request);
          } catch (ReleephFieldParseException $ex) {
            throw id(new ConduitException('ERR_FIELD_PARSE'))
              ->setErrorDescription($ex->getMessage());
          }
        }
        id(new ReleephRequestEditor($releeph_request))
          ->setActor($request->getUser())
          ->create($commit, $releeph_branch);
      }

      $releeph_branch->populateReleephRequestHandles(
        $request->getUser(),
        array($releeph_request));
      $rq_handles = $releeph_request->getHandles();
      $requestor_phid = $releeph_request->getRequestUserPHID();
      $requestor = $rq_handles[$requestor_phid]->getName();

      $url = PhabricatorEnv::getProductionURI('/RQ'.$releeph_request->getID());

      $results[$thing] = array(
        'thing'         => $thing,
        'branch'        => $releeph_branch->getDisplayNameWithDetail(),
        'commitName'    => $name,
        'commitID'      => $commit->getCommitIdentifier(),
        'url'           => $url,
        'requestID'     => $releeph_request->getID(),
        'requestor'     => $requestor,
        'requestTime'   => $releeph_request->getDateCreated(),
        'existing'      => $existing_releeph_request !== null,
      );
    }

    return $results;
  }

}
