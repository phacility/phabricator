<?php

final class DiffusionLookSoonConduitAPIMethod
  extends DiffusionConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.looksoon';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return pht(
      'Advises Phabricator to look for new commits in a repository as soon '.
      'as possible. This advice is most useful if you have just pushed new '.
      'commits to that repository.');
  }

  protected function defineReturnType() {
    return 'void';
  }

  protected function defineParamTypes() {
    return array(
      'callsigns' => 'required list<string>',
      'urgency' => 'optional string',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    // NOTE: The "urgency" parameter does nothing, it is just a hilarious joke
    // which exemplifies the boundless clever wit of this project.

    $callsigns = $request->getValue('callsigns');
    if (!$callsigns) {
      return null;
    }

    $repositories = id(new PhabricatorRepositoryQuery())
      ->setViewer($request->getUser())
      ->withCallsigns($callsigns)
      ->execute();

    foreach ($repositories as $repository) {
      $repository->writeStatusMessage(
        PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE,
        PhabricatorRepositoryStatusMessage::CODE_OKAY);
    }

    return null;
  }

}
