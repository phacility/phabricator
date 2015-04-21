<?php

final class ArcanistProjectInfoConduitAPIMethod
  extends ArcanistConduitAPIMethod {

  public function getAPIMethodName() {
    return 'arcanist.projectinfo';
  }

  public function getMethodDescription() {
    return 'Get information about Arcanist projects.';
  }

  protected function defineParamTypes() {
    return array(
      'name' => 'required string',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
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

    $repository = null;
    if ($project->getRepositoryID()) {
      $repository = id(new PhabricatorRepositoryQuery())
        ->setViewer($request->getUser())
        ->withIDs(array($project->getRepositoryID()))
        ->executeOne();
    }

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
