<?php

final class PhabricatorProjectTriggerViewController
  extends PhabricatorProjectTriggerController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $request = $this->getRequest();
    $viewer = $request->getViewer();

    $id = $request->getURIData('id');

    $trigger = id(new PhabricatorProjectTriggerQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$trigger) {
      return new Aphront404Response();
    }

    $rules_view = $this->newRulesView($trigger);
    $columns_view = $this->newColumnsView($trigger);

    $title = $trigger->getObjectName();

    $header = id(new PHUIHeaderView())
      ->setHeader($trigger->getDisplayName());

    $timeline = $this->buildTransactionTimeline(
      $trigger,
      new PhabricatorProjectTriggerTransactionQuery());
    $timeline->setShouldTerminate(true);

    $curtain = $this->newCurtain($trigger);

    $column_view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $rules_view,
          $columns_view,
          $timeline,
        ));

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($trigger->getObjectName())
      ->setBorder(true);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($column_view);
  }

  private function newColumnsView(PhabricatorProjectTrigger $trigger) {
    $viewer = $this->getViewer();

    // NOTE: When showing columns which use this trigger, we want to represent
    // all columns the trigger is used by: even columns the user can't see.

    // If we hide columns the viewer can't see, they might think that the
    // trigger isn't widely used and is safe to edit, when it may actually
    // be in use on workboards they don't have access to.

    // Query the columns with the omnipotent viewer first, then pull out their
    // PHIDs and throw the actual objects away. Re-query with the real viewer
    // so we load only the columns they can actually see, but have a list of
    // all the impacted column PHIDs.

    $omnipotent_viewer = PhabricatorUser::getOmnipotentUser();
    $all_columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($omnipotent_viewer)
      ->withTriggerPHIDs(array($trigger->getPHID()))
      ->execute();
    $column_phids = mpull($all_columns, 'getPHID');

    if ($column_phids) {
      $visible_columns = id(new PhabricatorProjectColumnQuery())
        ->setViewer($viewer)
        ->withPHIDs($column_phids)
        ->execute();
      $visible_columns = mpull($visible_columns, null, 'getPHID');
    } else {
      $visible_columns = array();
    }

    $rows = array();
    foreach ($column_phids as $column_phid) {
      $column = idx($visible_columns, $column_phid);

      if ($column) {
        $project = $column->getProject();

        $project_name = phutil_tag(
          'a',
          array(
            'href' => $project->getURI(),
          ),
          $project->getDisplayName());

        $column_name = phutil_tag(
          'a',
          array(
            'href' => $column->getBoardURI(),
          ),
          $column->getDisplayName());
      } else {
        $project_name = null;
        $column_name = phutil_tag('em', array(), pht('Restricted Column'));
      }

      $rows[] = array(
        $project_name,
        $column_name,
      );
    }

    $table_view = id(new AphrontTableView($rows))
      ->setNoDataString(pht('This trigger is not used by any columns.'))
      ->setHeaders(
        array(
          pht('Project'),
          pht('Column'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide pri',
        ));

    $header_view = id(new PHUIHeaderView())
      ->setHeader(pht('Used by Columns'));

    return id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setHeader($header_view)
      ->setTable($table_view);
  }

  private function newRulesView(PhabricatorProjectTrigger $trigger) {
    $viewer = $this->getViewer();
    $rules = $trigger->getTriggerRules();

    $rows = array();
    foreach ($rules as $rule) {
      $value = $rule->getRecord()->getValue();

      $rows[] = array(
        $rule->getRuleViewIcon($value),
        $rule->getRuleViewLabel(),
        $rule->getRuleViewDescription($value),
      );
    }

    $table_view = id(new AphrontTableView($rows))
      ->setNoDataString(pht('This trigger has no rules.'))
      ->setHeaders(
        array(
          null,
          pht('Rule'),
          pht('Action'),
        ))
      ->setColumnClasses(
        array(
          null,
          'pri',
          'wide',
        ));

    $header_view = id(new PHUIHeaderView())
      ->setHeader(pht('Trigger Rules'))
      ->setSubheader(
        pht(
          'When a card is dropped into a column that uses this trigger, '.
          'these actions will be taken.'));

    return id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setHeader($header_view)
      ->setTable($table_view);
  }
  private function newCurtain(PhabricatorProjectTrigger $trigger) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $trigger,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($trigger);

    $edit_uri = $this->getApplicationURI(
      urisprintf(
        'trigger/edit/%d/',
        $trigger->getID()));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Trigger'))
        ->setIcon('fa-pencil')
        ->setHref($edit_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $curtain;
  }

}
