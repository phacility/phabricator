<?php

final class ConduitDeprecatedCallSetupCheck extends PhabricatorSetupCheck {

  protected function executeChecks() {
    $methods = id(new PhabricatorConduitMethodQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIsDeprecated(true)
      ->execute();
    if (!$methods) {
      return;
    }

    $methods = mpull($methods, null, 'getAPIMethodName');
    $method_names = mpull($methods, 'getAPIMethodName');

    $table = new PhabricatorConduitMethodCallLog();
    $conn_r = $table->establishConnection('r');

    $calls = queryfx_all(
      $conn_r,
      'SELECT DISTINCT method FROM %T WHERE dateCreated > %d
        AND method IN (%Ls)',
      $table->getTableName(),
      time() - (60 * 60 * 24 * 30),
      $method_names);
    $calls = ipull($calls, 'method', 'method');

    foreach ($calls as $method_name) {
      $method = $methods[$method_name];

      $summary = pht(
        'Deprecated Conduit method `%s` was called in the last 30 days. '.
        'You should migrate away from use of this method: it will be '.
        'removed in a future version of Phabricator.',
        $method_name);

      $uri = PhabricatorEnv::getURI('/conduit/log/?methods='.$method_name);

      $description = $method->getMethodStatusDescription();

      $message = pht(
        'Deprecated Conduit method %s was called in the last 30 days. '.
        'You should migrate away from use of this method: it will be '.
        'removed in a future version of Phabricator.'.
        "\n\n".
        "%s: %s".
        "\n\n".
        'If you have already migrated all callers away from this method, '.
        'you can safely ignore this setup issue.',
        phutil_tag('tt', array(), $method_name),
        phutil_tag('tt', array(), $method_name),
        $description);

      $this
        ->newIssue('conduit.deprecated.'.$method_name)
        ->setShortName(pht('Deprecated Conduit Method'))
        ->setName(pht('Deprecated Conduit Method "%s" In Use', $method_name))
        ->setSummary($summary)
        ->setMessage($message)
        ->addLink($uri, pht('View Method Call Logs'));
    }
  }

}
