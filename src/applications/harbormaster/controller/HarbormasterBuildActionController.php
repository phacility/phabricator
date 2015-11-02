<?php

final class HarbormasterBuildActionController
  extends HarbormasterController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');
    $action = $request->getURIData('action');
    $via = $request->getURIData('via');

    $build = id(new HarbormasterBuildQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$build) {
      return new Aphront404Response();
    }

    switch ($action) {
      case HarbormasterBuildCommand::COMMAND_RESTART:
        $can_issue = $build->canRestartBuild();
        break;
      case HarbormasterBuildCommand::COMMAND_PAUSE:
        $can_issue = $build->canPauseBuild();
        break;
      case HarbormasterBuildCommand::COMMAND_RESUME:
        $can_issue = $build->canResumeBuild();
        break;
      case HarbormasterBuildCommand::COMMAND_ABORT:
        $can_issue = $build->canAbortBuild();
        break;
      default:
        return new Aphront400Response();
    }

    switch ($via) {
      case 'buildable':
        $return_uri = '/'.$build->getBuildable()->getMonogram();
        break;
      default:
        $return_uri = $this->getApplicationURI('/build/'.$build->getID().'/');
        break;
    }

    if ($request->isDialogFormPost() && $can_issue) {
      $editor = id(new HarbormasterBuildTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      $xaction = id(new HarbormasterBuildTransaction())
        ->setTransactionType(HarbormasterBuildTransaction::TYPE_COMMAND)
        ->setNewValue($action);

      $editor->applyTransactions($build, array($xaction));

      return id(new AphrontRedirectResponse())->setURI($return_uri);
    }

    switch ($action) {
      case HarbormasterBuildCommand::COMMAND_RESTART:
        if ($can_issue) {
          $title = pht('Really restart build?');
          $body = pht(
            'Progress on this build will be discarded and the build will '.
            'restart. Side effects of the build will occur again. Really '.
            'restart build?');
          $submit = pht('Restart Build');
        } else {
          $title = pht('Unable to Restart Build');
          if ($build->isRestarting()) {
            $body = pht(
              'This build is already restarting. You can not reissue a '.
              'restart command to a restarting build.');
          } else {
            $body = pht('You can not restart this build.');
          }
        }
        break;
      case HarbormasterBuildCommand::COMMAND_ABORT:
        if ($can_issue) {
          $title = pht('Really abort build?');
          $body = pht(
            'Progress on this build will be discarded. Really '.
            'abort build?');
          $submit = pht('Abort Build');
        } else {
          $title = pht('Unable to Abort Build');
          $body = pht('You can not abort this build.');
        }
        break;
      case HarbormasterBuildCommand::COMMAND_PAUSE:
        if ($can_issue) {
          $title = pht('Really pause build?');
          $body = pht(
            'If you pause this build, work will halt once the current steps '.
            'complete. You can resume the build later.');
          $submit = pht('Pause Build');
        } else {
          $title = pht('Unable to Pause Build');
          if ($build->isComplete()) {
            $body = pht(
              'This build is already complete. You can not pause a completed '.
              'build.');
          } else if ($build->isPaused()) {
            $body = pht(
              'This build is already paused. You can not pause a build which '.
              'has already been paused.');
          } else if ($build->isPausing()) {
            $body = pht(
              'This build is already pausing. You can not reissue a pause '.
              'command to a pausing build.');
          } else {
            $body = pht(
              'This build can not be paused.');
          }
        }
        break;
      case HarbormasterBuildCommand::COMMAND_RESUME:
        if ($can_issue) {
          $title = pht('Really resume build?');
          $body = pht(
            'Work will continue on the build. Really resume?');
          $submit = pht('Resume Build');
        } else {
          $title = pht('Unable to Resume Build');
          if ($build->isResuming()) {
            $body = pht(
              'This build is already resuming. You can not reissue a resume '.
              'command to a resuming build.');
          } else if (!$build->isPaused()) {
            $body = pht(
              'This build is not paused. You can only resume a paused '.
              'build.');
          }
        }
        break;
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle($title)
      ->appendChild($body)
      ->addCancelButton($return_uri);

    if ($can_issue) {
      $dialog->addSubmitButton($submit);
    }

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
