<?php

final class ManiphestBatchEditController extends ManiphestController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $this->requireApplicationCapability(
      ManiphestBulkEditCapability::CAPABILITY);

    $project = null;
    $board_id = $request->getInt('board');
    if ($board_id) {
      $project = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withIDs(array($board_id))
        ->executeOne();
      if (!$project) {
        return new Aphront404Response();
      }
    }

    $task_ids = $request->getArr('batch');
    if (!$task_ids) {
      $task_ids = $request->getStrList('batch');
    }

    if (!$task_ids) {
      throw new Exception(
        pht(
          'No tasks are selected.'));
    }

    $tasks = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs($task_ids)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->needSubscriberPHIDs(true)
      ->needProjectPHIDs(true)
      ->execute();

    if (!$tasks) {
      throw new Exception(
        pht("You don't have permission to edit any of the selected tasks."));
    }

    if ($project) {
      $cancel_uri = '/project/board/'.$project->getID().'/';
      $redirect_uri = $cancel_uri;
    } else {
      $cancel_uri = '/maniphest/';
      $redirect_uri = '/maniphest/?ids='.implode(',', mpull($tasks, 'getID'));
    }

    $actions = $request->getStr('actions');
    if ($actions) {
      $actions = phutil_json_decode($actions);
    }

    if ($request->isFormPost() && $actions) {
      $job = PhabricatorWorkerBulkJob::initializeNewJob(
        $viewer,
        new ManiphestTaskEditBulkJobType(),
        array(
          'taskPHIDs' => mpull($tasks, 'getPHID'),
          'actions' => $actions,
          'cancelURI' => $cancel_uri,
          'doneURI' => $redirect_uri,
        ));

      $type_status = PhabricatorWorkerBulkJobTransaction::TYPE_STATUS;

      $xactions = array();
      $xactions[] = id(new PhabricatorWorkerBulkJobTransaction())
        ->setTransactionType($type_status)
        ->setNewValue(PhabricatorWorkerBulkJob::STATUS_CONFIRM);

      $editor = id(new PhabricatorWorkerBulkJobEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($job, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($job->getMonitorURI());
    }

    $handles = ManiphestTaskListView::loadTaskHandles($viewer, $tasks);

    $list = new ManiphestTaskListView();
    $list->setTasks($tasks);
    $list->setUser($viewer);
    $list->setHandles($handles);

    $template = new AphrontTokenizerTemplateView();
    $template = $template->render();

    $projects_source = new PhabricatorProjectDatasource();
    $mailable_source = new PhabricatorMetaMTAMailableDatasource();
    $mailable_source->setViewer($viewer);
    $owner_source = new ManiphestAssigneeDatasource();
    $owner_source->setViewer($viewer);
    $spaces_source = id(new PhabricatorSpacesNamespaceDatasource())
      ->setViewer($viewer);

    require_celerity_resource('maniphest-batch-editor');
    Javelin::initBehavior(
      'maniphest-batch-editor',
      array(
        'root' => 'maniphest-batch-edit-form',
        'tokenizerTemplate' => $template,
        'sources' => array(
          'project' => array(
            'src' => $projects_source->getDatasourceURI(),
            'placeholder' => $projects_source->getPlaceholderText(),
            'browseURI' => $projects_source->getBrowseURI(),
          ),
          'owner' => array(
            'src' => $owner_source->getDatasourceURI(),
            'placeholder' => $owner_source->getPlaceholderText(),
            'browseURI' => $owner_source->getBrowseURI(),
            'limit' => 1,
          ),
          'cc' => array(
            'src' => $mailable_source->getDatasourceURI(),
            'placeholder' => $mailable_source->getPlaceholderText(),
            'browseURI' => $mailable_source->getBrowseURI(),
          ),
          'spaces' => array(
            'src' => $spaces_source->getDatasourceURI(),
            'placeholder' => $spaces_source->getPlaceholderText(),
            'browseURI' => $spaces_source->getBrowseURI(),
            'limit' => 1,
          ),
        ),
        'input' => 'batch-form-actions',
        'priorityMap' => ManiphestTaskPriority::getTaskPriorityMap(),
        'statusMap'   => ManiphestTaskStatus::getTaskStatusMap(),
      ));

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('board', $board_id)
      ->setID('maniphest-batch-edit-form');

    foreach ($tasks as $task) {
      $form->appendChild(
        phutil_tag(
          'input',
          array(
            'type' => 'hidden',
            'name' => 'batch[]',
            'value' => $task->getID(),
          )));
    }

    $form->appendChild(
      phutil_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => 'actions',
          'id'   => 'batch-form-actions',
        )));
    $form->appendChild(
      id(new PHUIFormInsetView())
        ->setTitle(pht('Actions'))
        ->setRightButton(javelin_tag(
            'a',
            array(
              'href' => '#',
              'class' => 'button green',
              'sigil' => 'add-action',
              'mustcapture' => true,
            ),
            pht('Add Another Action')))
        ->setContent(javelin_tag(
          'table',
          array(
            'sigil' => 'maniphest-batch-actions',
            'class' => 'maniphest-batch-actions-table',
          ),
          '')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Update Tasks'))
          ->addCancelButton($cancel_uri));

    $title = pht('Batch Editor');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);

    $task_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Selected Tasks'))
      ->appendChild($list);

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Batch Editor'))
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $task_box,
        $form_box,
      ),
      array(
        'title' => $title,
      ));
  }

}
