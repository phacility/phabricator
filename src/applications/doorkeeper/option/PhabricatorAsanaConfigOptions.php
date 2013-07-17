<?php

final class PhabricatorAsanaConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Integration with Asana");
  }

  public function getDescription() {
    return pht("Asana integration options.");
  }

  public function getOptions() {
    return array(
      $this->newOption('asana.workspace-id', 'string', null)
        ->setSummary(pht("Asana Workspace ID to publish into."))
        ->setDescription(
          pht(
            'To enable synchronization into Asana, enter an Asana Workspace '.
            'ID here.'.
            "\n\n".
            "NOTE: This feature is new and experimental.")),
    );
  }

  public function renderContextualDescription(
    PhabricatorConfigOption $option,
    AphrontRequest $request) {

    switch ($option->getKey()) {
      case 'asana.workspace-id':
        break;
      default:
        return parent::renderContextualDescription($option, $request);
    }

    $viewer = $request->getUser();

    $provider = PhabricatorAuthProviderOAuthAsana::getAsanaProvider();
    if (!$provider) {
      return null;
    }

    $account = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->withAccountTypes(array($provider->getProviderType()))
      ->withAccountDomains(array($provider->getProviderDomain()))
      ->executeOne();
    if (!$account) {
      return null;
    }

    $token = $provider->getOAuthAccessToken($account);
    if (!$token) {
      return null;
    }

    try {
      $workspaces = id(new PhutilAsanaFuture())
        ->setAccessToken($token)
        ->setRawAsanaQuery('workspaces')
        ->resolve();
    } catch (Exception $ex) {
      return null;
    }

    if (!$workspaces) {
      return null;
    }

    $out = array();
    $out[] = pht("| Workspace ID | Workspace Name |");
    $out[] =     "| ------------ | -------------- |";
    foreach ($workspaces as $workspace) {
      $out[] = sprintf('| `%s` | `%s` |', $workspace['id'], $workspace['name']);
    }

    $out = implode("\n", $out);

    $out = pht(
      "The Asana Workspaces your linked account has access to are:\n\n%s",
      $out);

    return PhabricatorMarkupEngine::renderOneObject(
      id(new PhabricatorMarkupOneOff())->setContent($out),
      'default',
      $viewer);
  }


}
