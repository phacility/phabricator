<?php

final class DiffusionJenkinsHookAPIMethod
  extends DiffusionConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.jenkinshook';
  }

  public function getMethodDescription() {
    return 'Notify about finished Jenkins build.';
  }

  public function defineParamTypes() {
    return array(
      'callsign'    => 'required string',
      'commit'      => 'required string',
      'jobName'     => 'required string',
      'buildNumber' => 'required int',
    );
  }

  public function defineReturnType() {
    return 'bool';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_MISSING_COMMIT' => pht('Commit identifier is required'),
      'ERR_BAD_COMMIT' => pht('No commit found with that identifier'),
      'ERR_MISSING_JOB' => pht('Job is required.'),
      'ERR_BAD_JOB' => pht('Job not found.'),
      'ERR_MISSING_BUILD' => pht('Build is required.'),
      'ERR_BAD_BUILD' => pht('Build not found.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $commit_identifier = $request->getValue('commit');
    if (!$commit_identifier) {
      throw new ConduitException('ERR_MISSING_COMMIT');
    }

    $job_name = $request->getValue('jobName');
    if (!$job_name) {
      throw new ConduitException('ERR_MISSING_JOB');
    }

    $build_number = $request->getValue('buildNumber');
    if (!$build_number) {
      throw new ConduitException('ERR_MISSING_BUILD');
    }

    $drequest = DiffusionRequest::newFromDictionary(array(
      'user' => $request->getUser(),
      'callsign' => $request->getValue('callsign'),
      'commit' => $commit_identifier,
    ));

    $commit = $drequest->loadCommit();
    if (!$commit) {
      throw new ConduitException('ERR_BAD_COMMIT');
    }

    $property = $this->getCommitProperty($commit, 'build-recorded');

    if ($property) {
      // Don't record same build twice.
      return false;
    }

    $commit_paths = $this->getCommitFiles($drequest);

    // 1. record checkstyle warnings
    $checkstyle_warnings =
      id(new JenkinsWarnings($job_name, $build_number, 'checkstyleResult'))
      ->get($commit_paths);
    $this->setCommitProperty($commit, 'checkstyle:warnings', $checkstyle_warnings);

    // 2. record pmd warnings
    $pmd_warnings =
      id(new JenkinsWarnings($job_name, $build_number, 'pmdResult'))
      ->get($commit_paths);
    $this->setCommitProperty($commit, 'checkstyle:warnings', $pmd_warnings);

    // 3. record build information
    $job_info = JenkinsAPIRequest::create()
      ->addJob($job_name)
      ->addBuild($build_number)
      ->query();

    $message = <<<MESSAGE
    Build **#{$job_info->number}** finished with **{$job_info->result}** status: {$job_info->url}
MESSAGE;
    $this->addComment($commit, $request, $message);

    // 4. mark as processed
    $this->setCommitProperty($commit, 'build-recorded', true);

    return true;
  }

  private function getCommitFiles(DiffusionRequest $drequest) {
    $change_query = DiffusionPathChangeQuery::newFromDiffusionRequest(
      $drequest);
    $path_changes = $change_query->loadChanges();

    return mpull($path_changes, 'getPath');
  }

  private function setCommitProperty(PhabricatorRepositoryCommit $commit, $name, $value) {
    $property = $this->getCommitProperty($commit, $name);

    if (!$value) {
      if ($property) {
        $property->delete();
      }

      return null;
    }

    if (!$property) {
      $property = new PhabricatorRepositoryCommitProperty();
      $property->setCommitID($commit->getID());
      $property->setName($name);
    }

    $property->setData($value);
    $property->save();

    return $property;
  }

  private function getCommitProperty(PhabricatorRepositoryCommit $commit, $name) {
    $property = id(new PhabricatorRepositoryCommitProperty())->loadOneWhere(
      'commitID = %d AND name = %s',
      $commit->getID(),
      $name);

    return $property;
  }

  private function addComment(
    PhabricatorRepositoryCommit $commit,
    ConduitAPIRequest $request,
    $message) {

    $conduit_call = new ConduitCall(
      'diffusion.createcomment',
      array(
        'phid' => $commit->getPHID(),
        'message' => $message,
      ));

    $conduit_call
      ->setUser($request->getUser())
      ->execute();
  }
}
