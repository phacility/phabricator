<?php

final class PhabricatorSystemReadOnlyController
  extends PhabricatorController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $reason = $request->getURIData('reason');

    $body = array();
    switch ($reason) {
      case PhabricatorEnv::READONLY_CONFIG:
        $title = pht('Administrative Read-Only Mode');
        $body[] = pht(
          'An administrator has placed this server into read-only mode.');
        $body[] = pht(
          'This mode may be used to perform temporary maintenance, test '.
          'configuration, or archive an installation permanently.');
        $body[] = pht(
          'Read-only mode was enabled by the explicit action of a human '.
          'administrator, so you can get more information about why it '.
          'has been turned on by rolling your chair away from your desk and '.
          'yelling "Hey! Why is %s in read-only mode??!" using '.
          'your very loudest outside voice.',
          PlatformSymbols::getPlatformServerSymbol());
        $body[] = pht(
          'This mode is active because it is enabled in the configuration '.
          'option "%s".',
          phutil_tag('tt', array(), 'cluster.read-only'));
        $button = pht('Wait Patiently');
        break;
      case PhabricatorEnv::READONLY_MASTERLESS:
        $title = pht('No Writable Database');
        $body[] = pht(
          'This server is currently configured with no writable ("master") '.
          'database, so it can not write new information anywhere. '.
          'This server will run in read-only mode until an administrator '.
          'reconfigures it with a writable database.');
        $body[] = pht(
          'This usually occurs when an administrator is actively working on '.
          'fixing a temporary configuration or deployment problem.');
        $body[] = pht(
          'This mode is active because no database has a "%s" role in '.
          'the configuration option "%s".',
          phutil_tag('tt', array(), 'master'),
          phutil_tag('tt', array(), 'cluster.databases'));
        $button = pht('Wait Patiently');
        break;
      case PhabricatorEnv::READONLY_UNREACHABLE:
        $title = pht('Unable to Reach Master');
        $body[] = pht(
          'This server was unable to connect to the writable ("master") '.
          'database while handling this request, and automatically degraded '.
          'into read-only mode.');
        $body[] = pht(
          'This may happen if there is a temporary network anomaly on the '.
          'server side, like cosmic radiation or spooky ghosts. If this '.
          'failure was caused by a transient service interruption, '.
          'this server will recover momentarily.');
        $body[] = pht(
          'This may also indicate that a more serious failure has occurred. '.
          'If this interruption does not resolve on its own, this server '.
          'will soon detect the persistent disruption and degrade into '.
          'read-only mode until the issue is resolved.');
        $button = pht('Quite Unsettling');
        break;
      case PhabricatorEnv::READONLY_SEVERED:
        $title = pht('Severed From Master');
        $body[] = pht(
          'This server has consistently been unable to reach the writable '.
          '("master") database while processing recent requests.');
        $body[] = pht(
          'This likely indicates a severe misconfiguration or major service '.
          'interruption.');
        $body[] = pht(
          'This server will periodically retry the connection and recover '.
          'once service is restored. Most causes of persistent service '.
          'interruption will require administrative intervention in order '.
          'to restore service.');
        $body[] = pht(
          'Although this may be the result of a misconfiguration or '.
          'operational error, this is also the state you reach if a '.
          'meteor recently obliterated a datacenter.');
        $button = pht('Panic!');
        break;
      default:
        return new Aphront404Response();
    }

    switch ($reason) {
      case PhabricatorEnv::READONLY_UNREACHABLE:
      case PhabricatorEnv::READONLY_SEVERED:
        $body[] = pht(
          'This request was served from a replica database. Replica '.
          'databases may lag behind the master, so very recent activity '.
          'may not be reflected in the UI. This data will be restored if '.
          'the master database is restored, but may have been lost if the '.
          'master database has been reduced to a pile of ash.');
        break;
    }

    $body[] = pht(
      'In read-only mode you can read existing information, but you will not '.
      'be able to edit objects or create new objects until this mode is '.
      'disabled.');

    if ($viewer->getIsAdmin()) {
      $body[] = pht(
        'As an administrator, you can review status information from the '.
        '%s control panel. This may provide more information about the '.
        'current state of affairs.',
        phutil_tag(
          'a',
          array(
            'href' => '/config/cluster/databases/',
          ),
          pht('Cluster Database Status')));
    }

    $dialog = $this->newDialog()
      ->setTitle($title)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->addCancelButton('/', $button);

    foreach ($body as $paragraph) {
      $dialog->appendParagraph($paragraph);
    }

    return $dialog;
  }
}
