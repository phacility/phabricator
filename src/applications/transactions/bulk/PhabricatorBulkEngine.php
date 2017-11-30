<?php

abstract class PhabricatorBulkEngine extends Phobject {

  private $viewer;
  private $controller;
  private $context = array();
  private $objectList;
  private $savedQuery;
  private $editableList;
  private $targetList;

  abstract public function newSearchEngine();

  public function getCancelURI() {
    $saved_query = $this->savedQuery;
    if ($saved_query) {
      $path = '/query/'.$saved_query->getQueryKey().'/';
    } else {
      $path = '/';
    }

    return $this->getQueryURI($path);
  }

  public function getDoneURI() {
    if ($this->objectList !== null) {
      $ids = mpull($this->objectList, 'getID');
      $path = '/?ids='.implode(',', $ids);
    } else {
      $path = '/';
    }

    return $this->getQueryURI($path);
  }

  protected function getQueryURI($path = '/') {
    $viewer = $this->getViewer();

    $engine = id($this->newSearchEngine())
      ->setViewer($viewer);

    return $engine->getQueryBaseURI().ltrim($path, '/');
  }

  protected function getBulkURI() {
    $saved_query = $this->savedQuery;
    if ($saved_query) {
      $path = '/query/'.$saved_query->getQueryKey().'/';
    } else {
      $path = '/';
    }

    return $this->getBulkBaseURI($path);
  }

  protected function getBulkBaseURI($path) {
    return $this->getQueryURI('bulk/'.ltrim($path, '/'));
  }

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setController(PhabricatorController $controller) {
    $this->controller = $controller;
    return $this;
  }

  final public function getController() {
    return $this->controller;
  }

  final public function addContextParameter($key) {
    $this->context[$key] = true;
    return $this;
  }

  final public function buildResponse() {
    $viewer = $this->getViewer();
    $controller = $this->getController();
    $request = $controller->getRequest();

    $response = $this->loadObjectList();
    if ($response) {
      return $response;
    }

    if ($request->isFormPost() && $request->getBool('bulkEngine')) {
      return $this->buildEditResponse();
    }

    $list_view = $this->newBulkObjectList();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Bulk Editor'))
      ->setHeaderIcon('fa-pencil-square-o');

    $list_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Working Set'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($list_view);

    $form_view = $this->newBulkActionForm();

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Actions'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form_view);

    $complete_form = phabricator_form(
      $viewer,
      array(
        'action' => $this->getBulkURI(),
        'method' => 'POST',
        'id' => 'maniphest-batch-edit-form',
      ),
      array(
        $this->newContextInputs(),
        $list_box,
        $form_box,
      ));

    $column_view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($complete_form);

    // TODO: This is a bit hacky and inflexible.
    $crumbs = $controller->buildApplicationCrumbsForEditEngine();
    $crumbs->addTextCrumb(pht('Query'), $this->getCancelURI());
    $crumbs->addTextCrumb(pht('Bulk Editor'));

    return $controller->newPage()
      ->setTitle(pht('Bulk Edit'))
      ->setCrumbs($crumbs)
      ->appendChild($column_view);
  }

  private function loadObjectList() {
    $viewer = $this->getViewer();
    $controller = $this->getController();
    $request = $controller->getRequest();

    $search_engine = id($this->newSearchEngine())
      ->setViewer($viewer);

    $query_key = $request->getURIData('queryKey');
    if (strlen($query_key)) {
      if ($search_engine->isBuiltinQuery($query_key)) {
        $saved = $search_engine->buildSavedQueryFromBuiltin($query_key);
      } else {
        $saved = id(new PhabricatorSavedQueryQuery())
          ->setViewer($viewer)
          ->withQueryKeys(array($query_key))
          ->executeOne();
        if (!$saved) {
          return new Aphront404Response();
        }
      }
    } else {
      // TODO: For now, since we don't deal gracefully with queries which
      // match a huge result set, just bail if we don't have any query
      // parameters instead of querying for a trillion tasks and timing out.
      $request_data = $request->getPassthroughRequestData();
      if (!$request_data) {
        throw new Exception(
          pht(
            'Expected a query key or a set of query constraints.'));
      }

      $saved = $search_engine->buildSavedQueryFromRequest($request);
      $search_engine->saveQuery($saved);
    }

    $object_query = $search_engine->buildQueryFromSavedQuery($saved)
      ->setViewer($viewer);
    $object_list = $object_query->execute();
    $object_list = mpull($object_list, null, 'getPHID');

    // If the user has submitted the bulk edit form, select only the objects
    // they checked.
    if ($request->getBool('bulkEngine')) {
      $target_phids = $request->getArr('bulkTargetPHIDs');

      // NOTE: It's possible that the underlying query result set has changed
      // between the time we ran the query initially and now: for example, the
      // query was for "Open Tasks" and some tasks were closed while the user
      // was making action selections.

      // This could result in some objects getting dropped from the working set
      // here: we'll have target PHIDs for them, but they will no longer be
      // part of the object list. For now, just go with this since it doesn't
      // seem like a big problem and may even be desirable.

      $this->targetList = array_select_keys($object_list, $target_phids);
    } else {
      $this->targetList = $object_list;
    }

    $this->objectList = $object_list;
    $this->savedQuery = $saved;

    // Filter just the editable objects. We show all the objects which the
    // query matches whether they're editable or not, but indicate which ones
    // can not be edited to the user.

    $editable_list = id(new PhabricatorPolicyFilter())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->apply($object_list);
    $this->editableList = mpull($editable_list, null, 'getPHID');

    return null;
  }

  private function newBulkObjectList() {
    $viewer = $this->getViewer();

    $objects = $this->objectList;
    $objects = mpull($objects, null, 'getPHID');

    $handles = $viewer->loadHandles(array_keys($objects));

    $status_closed = PhabricatorObjectHandle::STATUS_CLOSED;

    $list = id(new PHUIObjectItemListView())
      ->setViewer($viewer)
      ->setFlush(true);

    foreach ($objects as $phid => $object) {
      $handle = $handles[$phid];

      $is_closed = ($handle->getStatus() === $status_closed);
      $can_edit = isset($this->editableList[$phid]);
      $is_disabled = ($is_closed || !$can_edit);
      $is_selected = isset($this->targetList[$phid]);

      $item = id(new PHUIObjectItemView())
        ->setHeader($handle->getFullName())
        ->setHref($handle->getURI())
        ->setDisabled($is_disabled)
        ->setSelectable('bulkTargetPHIDs[]', $phid, $is_selected, !$can_edit);

      if (!$can_edit) {
        $item->addIcon('fa-pencil red', pht('Not Editable'));
      }

      $list->addItem($item);
    }

    return $list;
  }

  private function newContextInputs() {
    $viewer = $this->getViewer();
    $controller = $this->getController();
    $request = $controller->getRequest();

    $parameters = array();
    foreach ($this->context as $key => $value) {
      $parameters[$key] = $request->getStr($key);
    }

    $parameters = array(
      'bulkEngine' => 1,
    ) + $parameters;

    $result = array();
    foreach ($parameters as $key => $value) {
      $result[] = phutil_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => $key,
          'value' => $value,
        ));
    }

    return $result;
  }

  private function newBulkActionForm() {
    $viewer = $this->getViewer();

    $cancel_uri = $this->getCancelURI();

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

    $form = id(new PHUIFormLayoutView())
      ->setUser($viewer);

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
        ->setTitle(pht('Bulk Edit Actions'))
        ->setRightButton(
          javelin_tag(
            'a',
            array(
              'href' => '#',
              'class' => 'button button-green',
              'sigil' => 'add-action',
              'mustcapture' => true,
            ),
            pht('Add Another Action')))
        ->setContent(
          javelin_tag(
            'table',
            array(
              'sigil' => 'maniphest-batch-actions',
              'class' => 'maniphest-batch-actions-table',
            ),
            '')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Apply Bulk Edit'))
          ->addCancelButton($cancel_uri));

    return $form;
  }

  private function buildEditResponse() {
    $viewer = $this->getViewer();
    $controller = $this->getController();
    $request = $controller->getRequest();

    if (!$this->objectList) {
      throw new Exception(pht('Query does not match any objects.'));
    }

    if (!$this->editableList) {
      throw new Exception(
        pht(
          'Query does not match any objects you have permission to edit.'));
    }

    // Restrict the selection set to objects the user can actually edit.
    $objects = array_intersect_key($this->editableList, $this->targetList);

    if (!$objects) {
      throw new Exception(
        pht(
          'You have not selected any objects to edit.'));
    }

    $raw_actions = $request->getStr('actions');
    if ($raw_actions) {
      $actions = phutil_json_decode($raw_actions);
    } else {
      $actions = array();
    }

    if (!$actions) {
      throw new Exception(
        pht(
          'You have not chosen any edits to apply.'));
    }

    $cancel_uri = $this->getCancelURI();
    $done_uri = $this->getDoneURI();

    $job = PhabricatorWorkerBulkJob::initializeNewJob(
      $viewer,
      // TODO: This is a Maniphest-specific job type for now, but will become
      // a generic one so it gets to live here for now instead of in the task
      // specific BulkEngine subclass.
      new ManiphestTaskEditBulkJobType(),
      array(
        'taskPHIDs' => mpull($objects, 'getPHID'),
        'actions' => $actions,
        'cancelURI' => $cancel_uri,
        'doneURI' => $done_uri,
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

}
