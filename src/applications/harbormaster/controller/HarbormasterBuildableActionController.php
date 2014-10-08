<?php

final class HarbormasterBuildableActionController
  extends HarbormasterController {

  private $id;
  private $action;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->action = $data['action'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $command = $this->action;

    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needBuilds(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$buildable) {
      return new Aphront404Response();
    }

    $issuable = array();

    foreach ($buildable->getBuilds() as $build) {
      switch ($command) {
        case HarbormasterBuildCommand::COMMAND_RESTART:
          if ($build->canRestartBuild()) {
            $issuable[] = $build;
          }
          break;
        case HarbormasterBuildCommand::COMMAND_STOP:
          if ($build->canStopBuild()) {
            $issuable[] = $build;
          }
          break;
        case HarbormasterBuildCommand::COMMAND_RESUME:
          if ($build->canResumeBuild()) {
            $issuable[] = $build;
          }
          break;
        default:
          return new Aphront400Response();
      }
    }

    $return_uri = '/'.$buildable->getMonogram();
    if ($request->isDialogFormPost() && $issuable) {
      $editor = id(new HarbormasterBuildableTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      $xaction = id(new HarbormasterBuildableTransaction())
        ->setTransactionType(HarbormasterBuildableTransaction::TYPE_COMMAND)
        ->setNewValue($command);

      $editor->applyTransactions($buildable, array($xaction));

      $build_editor = id(new HarbormasterBuildTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      foreach ($issuable as $build) {
        $xaction = id(new HarbormasterBuildTransaction())
          ->setTransactionType(HarbormasterBuildTransaction::TYPE_COMMAND)
          ->setNewValue($command);
        $build_editor->applyTransactions($build, array($xaction));
      }

      return id(new AphrontRedirectResponse())->setURI($return_uri);
    }

    switch ($command) {
      case HarbormasterBuildCommand::COMMAND_RESTART:
        if ($issuable) {
          $title = pht('Really restart all builds?');
          $body = pht(
            'Progress on all builds will be discarded, and all builds will '.
            'restart. Side effects of the builds will occur again. Really '.
            'restart all builds?');
          $submit = pht('Restart All Builds');
        } else {
          $title = pht('Unable to Restart Build');
          $body = pht('No builds can be restarted.');
        }
        break;
      case HarbormasterBuildCommand::COMMAND_STOP:
        if ($issuable) {
          $title = pht('Really stop all builds?');
          $body = pht(
            'If you stop all build, work will halt once the current steps '.
            'complete. You can resume the builds later.');
          $submit = pht('Stop All Builds');
        } else {
          $title = pht('Unable to Stop Build');
          $body = pht('No builds can be stopped.');
        }
        break;
      case HarbormasterBuildCommand::COMMAND_RESUME:
        if ($issuable) {
          $title = pht('Really resume all builds?');
          $body = pht('Work will continue on all builds. Really resume?');
          $submit = pht('Resume All Builds');
        } else {
          $title = pht('Unable to Resume Build');
          $body = pht('No builds can be resumed.');
        }
        break;
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle($title)
      ->appendChild($body)
      ->addCancelButton($return_uri);

    if ($issuable) {
      $dialog->addSubmitButton($submit);
    }

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
