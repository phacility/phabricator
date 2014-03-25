<?php

final class HarbormasterPlanViewController
  extends HarbormasterPlanController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $this->id;

    $plan = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$plan) {
      return new Aphront404Response();
    }

    $xactions = id(new HarbormasterBuildPlanTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($plan->getPHID()))
      ->execute();

    $xaction_view = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($plan->getPHID())
      ->setTransactions($xactions)
      ->setShouldTerminate(true);

    $title = pht("Plan %d", $id);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($plan);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header);

    $actions = $this->buildActionList($plan);
    $this->buildPropertyLists($box, $plan, $actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht("Plan %d", $id));

    list($step_list, $has_any_conflicts) = $this->buildStepList($plan);

    if ($has_any_conflicts) {
      $box->setFormErrors(
        array(
          pht(
            'This build plan has conflicts in one or more build steps. '.
            'Examine the step list and resolve the listed errors.'),
        ));
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $step_list,
        $xaction_view,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildStepList(HarbormasterBuildPlan $plan) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $list_id = celerity_generate_unique_node_id();

    $steps = id(new HarbormasterBuildStepQuery())
      ->setViewer($viewer)
      ->withBuildPlanPHIDs(array($plan->getPHID()))
      ->execute();

    $can_edit = $this->hasApplicationCapability(
      HarbormasterCapabilityManagePlans::CAPABILITY);

    $i = 1;
    $step_list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setNoDataString(
        pht('This build plan does not have any build steps yet.'))
      ->setID($list_id);
    Javelin::initBehavior(
      'harbormaster-reorder-steps',
      array(
        'listID' => $list_id,
        'orderURI' => '/harbormaster/plan/order/'.$plan->getID().'/',
      ));

    $has_any_conflicts = false;
    foreach ($steps as $step) {
      $implementation = null;
      try {
        $implementation = $step->getStepImplementation();
      } catch (Exception $ex) {
        // We can't initialize the implementation.  This might be because
        // it's been renamed or no longer exists.
        $item = id(new PHUIObjectItemView())
          ->setObjectName(pht('Step %d', $i++))
          ->setHeader(pht('Unknown Implementation'))
          ->setBarColor('red')
          ->addAttribute(pht(
            'This step has an invalid implementation (%s).',
            $step->getClassName()))
          ->addAction(
            id(new PHUIListItemView())
              ->setIcon('delete')
              ->addSigil('harbormaster-build-step-delete')
              ->setWorkflow(true)
              ->setRenderNameAsTooltip(true)
              ->setName(pht("Delete"))
              ->setHref(
                $this->getApplicationURI("step/delete/".$step->getID()."/")));
        $step_list->addItem($item);
        continue;
      }
      $item = id(new PHUIObjectItemView())
        ->setObjectName("Step ".$i++)
        ->setHeader($implementation->getName());

      $item->addAttribute($implementation->getDescription());

      $step_id = $step->getID();
      $edit_uri = $this->getApplicationURI("step/edit/{$step_id}/");
      $delete_uri = $this->getApplicationURI("step/delete/{$step_id}/");

      if ($can_edit) {
        $item->setHref($edit_uri);
        $item->setGrippable(true);
        $item->addSigil('build-step');
        $item->setMetadata(
          array(
            'stepID' => $step->getID(),
          ));
      }

      $item
        ->setHref($edit_uri)
        ->addAction(
          id(new PHUIListItemView())
            ->setIcon('delete')
            ->addSigil('harbormaster-build-step-delete')
            ->setWorkflow(true)
            ->setDisabled(!$can_edit)
            ->setHref(
              $this->getApplicationURI("step/delete/".$step->getID()."/")));

      $inputs = $step->getStepImplementation()->getArtifactInputs();
      $outputs = $step->getStepImplementation()->getArtifactOutputs();

      $has_conflicts = false;
      if ($inputs || $outputs) {
        $available_artifacts =
          HarbormasterBuildStepImplementation::loadAvailableArtifacts(
            $plan,
            $step,
            null);

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
              $inputs_ui,
              $outputs_ui,
            )));
      }

      if ($has_conflicts) {
        $has_any_conflicts = true;
        $item->setBarColor('red');
      }

      $step_list->addItem($item);
    }

    return array($step_list, $has_any_conflicts);
  }

  private function buildActionList(HarbormasterBuildPlan $plan) {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $id = $plan->getID();

    $list = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($plan)
      ->setObjectURI($this->getApplicationURI("plan/{$id}/"));

    $can_edit = $this->hasApplicationCapability(
      HarbormasterCapabilityManagePlans::CAPABILITY);

    $list->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Plan'))
        ->setHref($this->getApplicationURI("plan/edit/{$id}/"))
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit)
        ->setIcon('edit'));

    if ($plan->isDisabled()) {
      $list->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Enable Plan'))
          ->setHref($this->getApplicationURI("plan/disable/{$id}/"))
          ->setWorkflow(true)
          ->setDisabled(!$can_edit)
          ->setIcon('enable'));
    } else {
      $list->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Disable Plan'))
          ->setHref($this->getApplicationURI("plan/disable/{$id}/"))
          ->setWorkflow(true)
          ->setDisabled(!$can_edit)
          ->setIcon('disable'));
    }

    $list->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Add Build Step'))
        ->setHref($this->getApplicationURI("step/add/{$id}/"))
        ->setWorkflow(true)
        ->setDisabled(!$can_edit)
        ->setIcon('new'));

    $list->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Run Plan Manually'))
        ->setHref($this->getApplicationURI("plan/run/{$id}/"))
        ->setWorkflow(true)
        ->setDisabled(!$can_edit)
        ->setIcon('start-sandcastle'));

    return $list;
  }

  private function buildPropertyLists(
    PHUIObjectBoxView $box,
    HarbormasterBuildPlan $plan,
    PhabricatorActionListView $actions) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($plan)
      ->setActionList($actions);
    $box->addPropertyList($properties);

    $properties->addProperty(
      pht('Created'),
      phabricator_datetime($plan->getDateCreated(), $viewer));

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
          $icon = 'warning-red';
          $icon_label = pht('Required Input');
          $has_conflicts = true;
          $error = pht('This input is required, but not configured.');
        } else {
          // This is an unnamed output. Outputs do not necessarily need to be
          // named.
          $icon = 'open';
          $icon_label = pht('Unused Output');
        }
      } else {
        $bound = phutil_tag('strong', array(), $key);
        if ($is_input) {
          if (isset($available_artifacts[$key])) {
            if ($available_artifacts[$key] == idx($artifact, 'type')) {
              $icon = 'accept-green';
              $icon_label = pht('Valid Input');
            } else {
              $icon = 'warning-red';
              $icon_label = pht('Bad Input Type');
              $has_conflicts = true;
              $error = pht(
                'This input is bound to the wrong artifact type. It is bound '.
                'to a "%s" artifact, but should be bound to a "%s" artifact.',
                $available_artifacts[$key],
                idx($artifact, 'type'));
            }
          } else {
            $icon = 'question-red';
            $icon_label = pht('Unknown Input');
            $has_conflicts = true;
            $error = pht(
              'This input is bound to an artifact ("%s") which does not exist '.
              'at this stage in the build process.',
              $key);
          }
        } else {
          $icon = 'down-green';
          $icon_label = pht('Valid Output');
        }
      }

      if ($error) {
        $note = array(
          phutil_tag('strong', array(), pht('ERROR:')),
          ' ',
          $error);
      } else {
        $note = $bound;
      }

      $list->addItem(
        id(new PHUIStatusItemView())
          ->setIcon($icon, $icon_label)
          ->setTarget($artifact['name'])
          ->setNote($note));
    }

    $ui = array(
      $header,
      $list,
    );

    return array($ui, $has_conflicts);
  }

}
