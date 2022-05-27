<?php

final class PhabricatorAsanaConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Integration with Asana');
  }

  public function getDescription() {
    return pht('Asana integration options.');
  }

  public function getIcon() {
    return 'fa-exchange';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    return array(
      $this->newOption('asana.workspace-id', 'string', null)
        ->setSummary(pht('Asana Workspace ID to publish into.'))
        ->setDescription(
          pht(
            'To enable synchronization into Asana, enter an Asana Workspace '.
            'ID here.'.
            "\n\n".
            "NOTE: This feature is new and experimental.")),
      $this->newOption('asana.project-ids', 'wild', null)
        ->setSummary(pht('Optional Asana projects to use as application tags.'))
        ->setDescription(
          pht(
            'When %s creates tasks in Asana, it can add the tasks '.
            'to Asana projects based on which application the corresponding '.
            'object in %s comes from. For example, you can add code '.
            'reviews in Asana to a "Differential" project.'.
            "\n\n".
            'NOTE: This feature is new and experimental.',
            PlatformSymbols::getPlatformServerName(),
            PlatformSymbols::getPlatformServerName())),
    );
  }

  public function renderContextualDescription(
    PhabricatorConfigOption $option,
    AphrontRequest $request) {

    switch ($option->getKey()) {
      case 'asana.workspace-id':
        break;
      case 'asana.project-ids':
        return $this->renderContextualProjectDescription($option, $request);
      default:
        return parent::renderContextualDescription($option, $request);
    }

    $viewer = $request->getUser();

    $provider = PhabricatorAsanaAuthProvider::getAsanaProvider();
    if (!$provider) {
      return null;
    }

    $account = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->withProviderConfigPHIDs(
        array(
          $provider->getProviderConfigPHID(),
        ))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
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
    $out[] = sprintf(
      '| %s | %s |',
      pht('Workspace ID'),
      pht('Workspace Name'));
    $out[] = '| ------------ | -------------- |';
    foreach ($workspaces as $workspace) {
      $out[] = sprintf(
        '| `%s` | `%s` |',
        $workspace['gid'],
        $workspace['name']);
    }

    $out = implode("\n", $out);

    $out = pht(
      "The Asana Workspaces your linked account has access to are:\n\n%s",
      $out);

    return new PHUIRemarkupView($viewer, $out);
  }

  private function renderContextualProjectDescription(
    PhabricatorConfigOption $option,
    AphrontRequest $request) {

    $viewer = $request->getUser();

    $publishers = id(new PhutilClassMapQuery())
      ->setAncestorClass('DoorkeeperFeedStoryPublisher')
      ->execute();

    $out = array();
    $out[] = pht(
      'To specify projects to add tasks to, enter a JSON map with publisher '.
      'class names as keys and a list of project IDs as values. For example, '.
      'to put Differential tasks into Asana projects with IDs `123` and '.
      '`456`, enter:'.
      "\n\n".
      "  lang=txt\n".
      "  {\n".
      "    \"DifferentialDoorkeeperRevisionFeedStoryPublisher\" : [123, 456]\n".
      "  }\n");

    $out[] = pht('Available publishers class names are:');
    foreach ($publishers as $publisher) {
      $out[] = '  - `'.get_class($publisher).'`';
    }

    $out[] = pht(
      'You can find an Asana project ID by clicking the project in Asana and '.
      'then examining the URL:'.
      "\n\n".
      "  lang=txt\n".
      "  https://app.asana.com/0/12345678901234567890/111111111111111111\n".
      "                          ^^^^^^^^^^^^^^^^^^^^\n".
      "                        This is the ID to use.\n");

    $out = implode("\n", $out);

    return new PHUIRemarkupView($viewer, $out);
  }

}
