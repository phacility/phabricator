<?php

/**
 * @group conduit
 */
final class ConduitAPI_arcanist_projectinfo_Method
  extends ConduitAPI_arcanist_Method {

  public function getMethodDescription() {
    return "Get information about Arcanist projects.";
  }

  public function defineParamTypes() {
    return array(
      'name' => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-BAD-ARCANIST-PROJECT' => 'No such project exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $name = $request->getValue('name');

    $project = id(new PhabricatorRepositoryArcanistProject())->loadOneWhere(
      'name = %s',
      $name);

    if (!$project) {
      throw new ConduitException('ERR-BAD-ARCANIST-PROJECT');
    }

    $repository = $project->loadRepository();

    $repository_phid = null;
    $tracked = false;
    $encoding = null;
    $dictionary = array();
    if ($repository) {
      $repository_phid = $repository->getPHID();
      $tracked = $repository->isTracked();
      $encoding = $repository->getDetail('encoding');
      $dictionary = $repository->toDictionary();
    }

    return array(
      'name'            => $project->getName(),
      'phid'            => $project->getPHID(),
      'repositoryPHID'  => $repository_phid,
      'tracked'         => $tracked,
      'encoding'        => $encoding,
      'repository'      => $dictionary,
    );
  }

}
