<?php

final class ConduitAPI_releephwork_getorigcommitmessage_Method
  extends ConduitAPI_releeph_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return "Return the original commit message for the given commit.";
  }

  public function defineParamTypes() {
    return array(
      'commitPHID' => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty string';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $commit = id(new PhabricatorRepositoryCommit())
      ->loadOneWhere('phid = %s', $request->getValue('commitPHID'));
    $commit_data = $commit->loadCommitData();
    $commit_message = $commit_data->getCommitMessage();
    return trim($commit_message);
  }
}
