<?php

final class HarbormasterBuildableActionController
  extends HarbormasterController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');
    $action = $request->getURIData('action');

    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
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
      switch ($action) {
        case HarbormasterBuildCommand::COMMAND_RESTART:
          if ($build->canRestartBuild()) {
            $issuable[] = $build;
          }
          break;
        case HarbormasterBuildCommand::COMMAND_PAUSE:
          if ($build->canPauseBuild()) {
            $issuable[] = $build;
          }
          break;
        case HarbormasterBuildCommand::COMMAND_RESUME:
          if ($build->canResumeBuild()) {
            $issuable[] = $build;
          }
          break;
        case HarbormasterBuildCommand::COMMAND_ABORT:
          if ($build->canAbortBuild()) {
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
        ->setNewValue($action);

      $editor->applyTransactions($buildable, array($xaction));

      $build_editor = id(new HarbormasterBuildTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      foreach ($issuable as $build) {
        $xaction = id(new HarbormasterBuildTransaction())
          ->setTransactionType(HarbormasterBuildTransaction::TYPE_COMMAND)
          ->setNewValue($action);
        $build_editor->applyTransactions($build, array($xaction));
      }

      return id(new AphrontRedirectResponse())->setURI($return_uri);
    }

    switch ($action) {
      case HarbormasterBuildCommand::COMMAND_RESTART:
        if ($issuable) {
          $title = pht('Really restart all builds?');
          $body = pht(
            'Progress on all builds will be discarded, and all builds will '.
            'restart. Side effects of the builds will occur again. Really '.
            'restart all builds?');
          $submit = pht('Restart All Builds');
        } else {
          $title = pht('Unable to Restart Builds');
          $body = pht('No builds can be restarted.');
        }
        break;
      case HarbormasterBuildCommand::COMMAND_PAUSE:
        if ($issuable) {
          $title = pht('Really pause all builds?');
          $body = pht(
            'If you pause all builds, work will halt once the current steps '.
            'complete. You can resume the builds later.');
          $submit = pht('Pause All Builds');
        } else {
          $title = pht('Unable to Pause Builds');
          $body = pht('No builds can be paused.');
        }
        break;
      case HarbormasterBuildCommand::COMMAND_ABORT:
        if ($issuable) {
          $title = pht('Really abort all builds?');
          $body = pht(
            'If you abort all builds, work will halt immediately. Work '.
            'will be discarded, and builds must be completely restarted.');
          $submit = pht('Abort All Builds');
        } else {
          $title = pht('Unable to Abort Builds');
          $body = pht('No builds can be aborted.');
        }
        break;
      case HarbormasterBuildCommand::COMMAND_RESUME:
        if ($issuable) {
          $title = pht('Really resume all builds?');
          $body = pht('Work will continue on all builds. Really resume?');
          $submit = pht('Resume All Builds');
        } else {
          $title = pht('Unable to Resume Builds');
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
