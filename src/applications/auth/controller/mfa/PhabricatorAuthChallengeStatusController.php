<?php

final class PhabricatorAuthChallengeStatusController
  extends PhabricatorAuthController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');
    $now = PhabricatorTime::getNow();

    $result = new PhabricatorAuthChallengeUpdate();

    $challenge = id(new PhabricatorAuthChallengeQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->withUserPHIDs(array($viewer->getPHID()))
      ->withChallengeTTLBetween($now, null)
      ->executeOne();
    if ($challenge) {
      $config = id(new PhabricatorAuthFactorConfigQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($challenge->getFactorPHID()))
        ->executeOne();
      if ($config) {
        $provider = $config->getFactorProvider();
        $factor = $provider->getFactor();

        $result = $factor->newChallengeStatusView(
          $config,
          $provider,
          $viewer,
          $challenge);
      }
    }

    return id(new AphrontAjaxResponse())
      ->setContent($result->newContent());
  }

}
