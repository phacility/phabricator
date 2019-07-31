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

    $builds = $buildable->getBuilds();
    foreach ($builds as $key => $build) {
      switch ($action) {
        case HarbormasterBuildCommand::COMMAND_RESTART:
          if ($build->canRestartBuild()) {
            $issuable[$key] = $build;
          }
          break;
        case HarbormasterBuildCommand::COMMAND_PAUSE:
          if ($build->canPauseBuild()) {
            $issuable[$key] = $build;
          }
          break;
        case HarbormasterBuildCommand::COMMAND_RESUME:
          if ($build->canResumeBuild()) {
            $issuable[$key] = $build;
          }
          break;
        case HarbormasterBuildCommand::COMMAND_ABORT:
          if ($build->canAbortBuild()) {
            $issuable[$key] = $build;
          }
          break;
        default:
          return new Aphront400Response();
      }
    }

    $restricted = false;
    foreach ($issuable as $key => $build) {
      if (!$build->canIssueCommand($viewer, $action)) {
        $restricted = true;
        unset($issuable[$key]);
      }
    }

    $building = false;
    foreach ($issuable as $key => $build) {
      if ($build->isBuilding()) {
        $building = true;
        break;
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

    $width = AphrontDialogView::WIDTH_DEFAULT;

    switch ($action) {
      case HarbormasterBuildCommand::COMMAND_RESTART:
        // See T13348. The "Restart Builds" action may restart only a subset
        // of builds, so show the user a preview of which builds will actually
        // restart.

        $body = array();

        if ($issuable) {
          $title = pht('Restart Builds');
          $submit = pht('Restart Builds');
        } else {
          $title = pht('Unable to Restart Builds');
        }

        if ($builds) {
          $width = AphrontDialogView::WIDTH_FORM;

          $body[] = pht('Builds for this buildable:');

          $rows = array();
          foreach ($builds as $key => $build) {
            if (isset($issuable[$key])) {
              $icon = id(new PHUIIconView())
                ->setIcon('fa-repeat green');
              $build_note = pht('Will Restart');
            } else {
              $icon = null;

              try {
                $build->assertCanRestartBuild();
              } catch (HarbormasterRestartException $ex) {
                $icon = id(new PHUIIconView())
                  ->setIcon('fa-times red');
                $build_note = pht(
                  '%s: %s',
                  phutil_tag('strong', array(), pht('Not Restartable')),
                  $ex->getTitle());
              }

              if (!$icon) {
                try {
                  $build->assertCanIssueCommand($viewer, $action);
                } catch (PhabricatorPolicyException $ex) {
                  $icon = id(new PHUIIconView())
                    ->setIcon('fa-lock red');
                  $build_note = pht(
                    '%s: %s',
                    phutil_tag('strong', array(), pht('Not Restartable')),
                    pht('You do not have permission to restart this build.'));
                }
              }

              if (!$icon) {
                $icon = id(new PHUIIconView())
                  ->setIcon('fa-times red');
                $build_note = pht('Will Not Restart');
              }
            }

            $build_name = phutil_tag(
              'a',
              array(
                'href' => $build->getURI(),
                'target' => '_blank',
              ),
              pht('%s %s', $build->getObjectName(), $build->getName()));

            $rows[] = array(
              $icon,
              $build_name,
              $build_note,
            );
          }

          $table = id(new AphrontTableView($rows))
            ->setHeaders(
              array(
                null,
                pht('Build'),
                pht('Action'),
              ))
            ->setColumnClasses(
              array(
                null,
                'pri',
                'wide',
              ));

          $table = phutil_tag(
            'div',
            array(
              'class' => 'mlt mlb',
            ),
            $table);

          $body[] = $table;
        }

        if ($issuable) {
          $warnings = array();

          if ($restricted) {
            $warnings[] = pht(
              'You only have permission to restart some builds.');
          }

          if ($building) {
            $warnings[] = pht(
              'Progress on running builds will be discarded.');
          }

          $warnings[] = pht(
            'When a build is restarted, side effects associated with '.
            'the build may occur again.');

          $body[] = id(new PHUIInfoView())
            ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
            ->setErrors($warnings);

          $body[] = pht('Really restart builds?');
        } else {
          if ($restricted) {
            $body[] = pht('You do not have permission to restart any builds.');
          } else {
            $body[] = pht('No builds can be restarted.');
          }
        }

        break;
      case HarbormasterBuildCommand::COMMAND_PAUSE:
        if ($issuable) {
          $title = pht('Really pause builds?');

          if ($restricted) {
            $body = pht(
              'You only have permission to pause some builds. Once the '.
              'current steps complete, work will halt on builds you have '.
              'permission to pause. You can resume the builds later.');
          } else {
            $body = pht(
              'If you pause all builds, work will halt once the current steps '.
              'complete. You can resume the builds later.');
          }
          $submit = pht('Pause Builds');
        } else {
          $title = pht('Unable to Pause Builds');

          if ($restricted) {
            $body = pht('You do not have permission to pause any builds.');
          } else {
            $body = pht('No builds can be paused.');
          }
        }
        break;
      case HarbormasterBuildCommand::COMMAND_ABORT:
        if ($issuable) {
          $title = pht('Really abort builds?');
          if ($restricted) {
            $body = pht(
              'You only have permission to abort some builds. Work will '.
              'halt immediately on builds you have permission to abort. '.
              'Progress will be discarded, and builds must be completely '.
              'restarted if you want them to complete.');
          } else {
            $body = pht(
              'If you abort all builds, work will halt immediately. Work '.
              'will be discarded, and builds must be completely restarted.');
          }
          $submit = pht('Abort Builds');
        } else {
          $title = pht('Unable to Abort Builds');

          if ($restricted) {
            $body = pht('You do not have permission to abort any builds.');
          } else {
            $body = pht('No builds can be aborted.');
          }
        }
        break;
      case HarbormasterBuildCommand::COMMAND_RESUME:
        if ($issuable) {
          $title = pht('Really resume builds?');
          if ($restricted) {
            $body = pht(
              'You only have permission to resume some builds. Work will '.
              'continue on builds you have permission to resume.');
          } else {
            $body = pht('Work will continue on all builds. Really resume?');
          }

          $submit = pht('Resume Builds');
        } else {
          $title = pht('Unable to Resume Builds');
          if ($restricted) {
            $body = pht('You do not have permission to resume any builds.');
          } else {
            $body = pht('No builds can be resumed.');
          }
        }
        break;
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setWidth($width)
      ->setTitle($title)
      ->appendChild($body)
      ->addCancelButton($return_uri);

    if ($issuable) {
      $dialog->addSubmitButton($submit);
    }

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
