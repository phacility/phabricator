<?php

final class HarbormasterPlanViewController extends HarbormasterPlanController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $plan = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$plan) {
      return new Aphront404Response();
    }

    $timeline = $this->buildTransactionTimeline(
      $plan,
      new HarbormasterBuildPlanTransactionQuery());
    $timeline->setShouldTerminate(true);

    $title = $plan->getName();

    $header = id(new PHUIHeaderView())
      ->setHeader($plan->getName())
      ->setUser($viewer)
      ->setPolicyObject($plan)
      ->setHeaderIcon('fa-ship');

    $curtain = $this->buildCurtainView($plan);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Plan %d', $id));
    $crumbs->setBorder(true);

    list($step_list, $has_any_conflicts, $would_deadlock) =
      $this->buildStepList($plan);

    $error = null;
    if ($would_deadlock) {
      $error = pht('This build plan will deadlock when executed, due to '.
                   'circular dependencies present in the build plan. '.
                   'Examine the step list and resolve the deadlock.');
    } else if ($has_any_conflicts) {
      // A deadlocking build will also cause all the artifacts to be
      // invalid, so we just skip showing this message if that's the
      // case.
      $error = pht('This build plan has conflicts in one or more build steps. '.
                   'Examine the step list and resolve the listed errors.');
    }

    if ($error) {
      $error = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->appendChild($error);
    }

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $error,
        $step_list,
        $timeline,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildStepList(HarbormasterBuildPlan $plan) {
    $viewer = $this->getViewer();

    $run_order = HarbormasterBuildGraph::determineDependencyExecution($plan);

    $steps = id(new HarbormasterBuildStepQuery())
      ->setViewer($viewer)
      ->withBuildPlanPHIDs(array($plan->getPHID()))
      ->execute();
    $steps = mpull($steps, null, 'getPHID');

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $plan,
      PhabricatorPolicyCapability::CAN_EDIT);

    $step_list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setNoDataString(
        pht('This build plan does not have any build steps yet.'));

    $i = 1;
    $last_depth = 0;
    $has_any_conflicts = false;
    $is_deadlocking = false;
    foreach ($run_order as $run_ref) {
      $step = $steps[$run_ref['node']->getPHID()];
      $depth = $run_ref['depth'] + 1;
      if ($last_depth !== $depth) {
        $last_depth = $depth;
        $i = 1;
      } else {
        $i++;
      }

      $step_id = $step->getID();
      $view_uri = $this->getApplicationURI("step/view/{$step_id}/");

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Step %d.%d', $depth, $i))
        ->setHeader($step->getName())
        ->setHref($view_uri);

      $step_list->addItem($item);

      $implementation = null;
      try {
        $implementation = $step->getStepImplementation();
      } catch (Exception $ex) {
        // We can't initialize the implementation. This might be because
        // it's been renamed or no longer exists.
        $item
          ->setStatusIcon('fa-warning red')
          ->addAttribute(pht(
            'This step has an invalid implementation (%s).',
            $step->getClassName()));
        continue;
      }

      $item->addAttribute($implementation->getDescription());
      $item->setHref($view_uri);

      $depends = $step->getStepImplementation()->getDependencies($step);
      $inputs = $step->getStepImplementation()->getArtifactInputs();
      $outputs = $step->getStepImplementation()->getArtifactOutputs();

      $has_conflicts = false;
      if ($depends || $inputs || $outputs) {
        $available_artifacts =
          HarbormasterBuildStepImplementation::getAvailableArtifacts(
            $plan,
            $step,
            null);
        $available_artifacts = ipull($available_artifacts, 'type');

        list($depends_ui, $has_conflicts) = $this->buildDependsOnList(
            $depends,
            pht('Depends On'),
            $steps);

        list($inputs_ui, $has_conflicts) = $this->buildArtifactList(
            $inputs,
            'in',
            pht('Input Artifacts'),
            $available_artifacts);

        list($outputs_ui) = $this->buildArtifactList(
            $outputs,
            'out',
            pht('Output Artifacts'),
            array());

        $item->appendChild(
          phutil_tag(
            'div',
            array(
              'class' => 'harbormaster-artifact-io',
            ),
            array(
              $depends_ui,
              $inputs_ui,
              $outputs_ui,
            )));
      }

      if ($has_conflicts) {
        $has_any_conflicts = true;
        $item->setStatusIcon('fa-warning red');
      }

      if ($run_ref['cycle']) {
        $is_deadlocking = true;
      }

      if ($is_deadlocking) {
        $item->setStatusIcon('fa-warning red');
      }
    }

    $step_list->setFlush(true);

    $plan_id = $plan->getID();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Build Steps'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setText(pht('Add Build Step'))
          ->setHref($this->getApplicationURI("step/add/{$plan_id}/"))
          ->setTag('a')
          ->setIcon('fa-plus')
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit));

    $step_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($step_list);

    return array($step_box, $has_any_conflicts, $is_deadlocking);
  }

  private function buildCurtainView(HarbormasterBuildPlan $plan) {
    $viewer = $this->getViewer();
    $id = $plan->getID();

    $curtain = $this->newCurtainView($plan);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $plan,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Plan'))
        ->setHref($this->getApplicationURI("plan/edit/{$id}/"))
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit)
        ->setIcon('fa-pencil'));

    if ($plan->isDisabled()) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Enable Plan'))
          ->setHref($this->getApplicationURI("plan/disable/{$id}/"))
          ->setWorkflow(true)
          ->setDisabled(!$can_edit)
          ->setIcon('fa-check'));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Disable Plan'))
          ->setHref($this->getApplicationURI("plan/disable/{$id}/"))
          ->setWorkflow(true)
          ->setDisabled(!$can_edit)
          ->setIcon('fa-ban'));
    }

    $can_run = ($can_edit && $plan->canRunManually());

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Run Plan Manually'))
        ->setHref($this->getApplicationURI("plan/run/{$id}/"))
        ->setWorkflow(true)
        ->setDisabled(!$can_run)
        ->setIcon('fa-play-circle'));

    $curtain->addPanel(
      id(new PHUICurtainPanelView())
        ->setHeaderText(pht('Created'))
        ->appendChild(phabricator_datetime($plan->getDateCreated(), $viewer)));

    return $curtain;
  }

  private function buildArtifactList(
    array $artifacts,
    $kind,
    $name,
    array $available_artifacts) {
    $has_conflicts = false;

    if (!$artifacts) {
      return array(null, $has_conflicts);
    }

    $this->requireResource('harbormaster-css');

    $header = phutil_tag(
      'div',
      array(
        'class' => 'harbormaster-artifact-summary-header',
      ),
      $name);

    $is_input = ($kind == 'in');

    $list = new PHUIStatusListView();
    foreach ($artifacts as $artifact) {
      $error = null;

      $key = idx($artifact, 'key');
      if (!strlen($key)) {
        $bound = phutil_tag('em', array(), pht('(null)'));
        if ($is_input) {
          // This is an unbound input. For now, all inputs are always required.
          $icon = PHUIStatusItemView::ICON_WARNING;
          $color = 'red';
          $icon_label = pht('Required Input');
          $has_conflicts = true;
          $error = pht('This input is required, but not configured.');
        } else {
          // This is an unnamed output. Outputs do not necessarily need to be
          // named.
          $icon = PHUIStatusItemView::ICON_OPEN;
          $color = 'bluegrey';
          $icon_label = pht('Unused Output');
        }
      } else {
        $bound = phutil_tag('strong', array(), $key);
        if ($is_input) {
          if (isset($available_artifacts[$key])) {
            if ($available_artifacts[$key] == idx($artifact, 'type')) {
              $icon = PHUIStatusItemView::ICON_ACCEPT;
              $color = 'green';
              $icon_label = pht('Valid Input');
            } else {
              $icon = PHUIStatusItemView::ICON_WARNING;
              $color = 'red';
              $icon_label = pht('Bad Input Type');
              $has_conflicts = true;
              $error = pht(
                'This input is bound to the wrong artifact type. It is bound '.
                'to a "%s" artifact, but should be bound to a "%s" artifact.',
                $available_artifacts[$key],
                idx($artifact, 'type'));
            }
          } else {
            $icon = PHUIStatusItemView::ICON_QUESTION;
            $color = 'red';
            $icon_label = pht('Unknown Input');
            $has_conflicts = true;
            $error = pht(
              'This input is bound to an artifact ("%s") which does not exist '.
              'at this stage in the build process.',
              $key);
          }
        } else {
          $icon = PHUIStatusItemView::ICON_DOWN;
          $color = 'green';
          $icon_label = pht('Valid Output');
        }
      }

      if ($error) {
        $note = array(
          phutil_tag('strong', array(), pht('ERROR:')),
          ' ',
          $error,
        );
      } else {
        $note = $bound;
      }

      $list->addItem(
        id(new PHUIStatusItemView())
          ->setIcon($icon, $color, $icon_label)
          ->setTarget($artifact['name'])
          ->setNote($note));
    }

    $ui = array(
      $header,
      $list,
    );

    return array($ui, $has_conflicts);
  }

  private function buildDependsOnList(
    array $step_phids,
    $name,
    array $steps) {
    $has_conflicts = false;

    if (count($step_phids) === 0) {
      return null;
    }

    $this->requireResource('harbormaster-css');

    $steps = mpull($steps, null, 'getPHID');

    $header = phutil_tag(
      'div',
      array(
        'class' => 'harbormaster-artifact-summary-header',
      ),
      $name);

    $list = new PHUIStatusListView();
    foreach ($step_phids as $step_phid) {
      $error = null;

      if (idx($steps, $step_phid) === null) {
        $icon = PHUIStatusItemView::ICON_WARNING;
        $color = 'red';
        $icon_label = pht('Missing Dependency');
        $has_conflicts = true;
        $error = pht(
          "This dependency specifies a build step which doesn't exist.");
      } else {
        $bound = phutil_tag(
          'strong',
          array(),
          idx($steps, $step_phid)->getName());
        $icon = PHUIStatusItemView::ICON_ACCEPT;
        $color = 'green';
        $icon_label = pht('Valid Input');
      }

      if ($error) {
        $note = array(
          phutil_tag('strong', array(), pht('ERROR:')),
          ' ',
          $error,
        );
      } else {
        $note = $bound;
      }

      $list->addItem(
        id(new PHUIStatusItemView())
          ->setIcon($icon, $color, $icon_label)
          ->setTarget(pht('Build Step'))
          ->setNote($note));
    }

    $ui = array(
      $header,
      $list,
    );

    return array($ui, $has_conflicts);
  }
}
