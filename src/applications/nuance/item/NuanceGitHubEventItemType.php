<?php

final class NuanceGitHubEventItemType
  extends NuanceItemType {

  const ITEMTYPE = 'github.event';

  private $externalObject;
  private $externalActor;

  public function getItemTypeDisplayName() {
    return pht('GitHub Event');
  }

  public function getItemTypeDisplayIcon() {
    return 'fa-github';
  }

  public function getItemDisplayName(NuanceItem $item) {
    return $this->newRawEvent($item)->getEventFullTitle();
  }

  public function canUpdateItems() {
    return true;
  }

  protected function updateItemFromSource(NuanceItem $item) {
    $viewer = $this->getViewer();
    $is_dirty = false;

    $xobj = $this->reloadExternalObject($item);
    if ($xobj) {
      $item->setItemProperty('doorkeeper.xobj.phid', $xobj->getPHID());
      $is_dirty = true;
    }

    $actor = $this->reloadExternalActor($item);
    if ($actor) {
      $item->setRequestorPHID($actor->getPHID());
      $is_dirty = true;
    }

    if ($item->getStatus() == NuanceItem::STATUS_IMPORTING) {
      $item->setStatus(NuanceItem::STATUS_ROUTING);
      $is_dirty = true;
    }

    if ($is_dirty) {
      $item->save();
    }
  }

  private function getDoorkeeperActorRef(NuanceItem $item) {
    $raw = $this->newRawEvent($item);

    $user_id = $raw->getActorGitHubUserID();
    if (!$user_id) {
      return null;
    }

    $ref_type = DoorkeeperBridgeGitHubUser::OBJTYPE_GITHUB_USER;

    return $this->newDoorkeeperRef()
      ->setObjectType($ref_type)
      ->setObjectID($user_id);
  }

  private function getDoorkeeperRef(NuanceItem $item) {
    $raw = $this->newRawEvent($item);

    $full_repository = $raw->getRepositoryFullName();
    if (!strlen($full_repository)) {
      return null;
    }

    if ($raw->isIssueEvent()) {
      $ref_type = DoorkeeperBridgeGitHubIssue::OBJTYPE_GITHUB_ISSUE;
      $issue_number = $raw->getIssueNumber();
      $full_ref = "{$full_repository}#{$issue_number}";
    } else {
      return null;
    }

    return $this->newDoorkeeperRef()
      ->setObjectType($ref_type)
      ->setObjectID($full_ref);
  }

  private function newDoorkeeperRef() {
    return id(new DoorkeeperObjectRef())
      ->setApplicationType(DoorkeeperBridgeGitHub::APPTYPE_GITHUB)
      ->setApplicationDomain(DoorkeeperBridgeGitHub::APPDOMAIN_GITHUB);
  }

  private function reloadExternalObject(NuanceItem $item, $local = false) {
    $ref = $this->getDoorkeeperRef($item);
    if (!$ref) {
      return null;
    }

    $xobj = $this->reloadExternalRef($item, $ref, $local);

    if ($xobj) {
      $this->externalObject = $xobj;
    }

    return $xobj;
  }

  private function reloadExternalActor(NuanceItem $item, $local = false) {
    $ref = $this->getDoorkeeperActorRef($item);
    if (!$ref) {
      return null;
    }

    $xobj = $this->reloadExternalRef($item, $ref, $local);

    if ($xobj) {
      $this->externalActor = $xobj;
    }

    return $xobj;
  }

  private function reloadExternalRef(
    NuanceItem $item,
    DoorkeeperObjectRef $ref,
    $local) {

    $source = $item->getSource();
    $token = $source->getSourceProperty('github.token');
    $token = new PhutilOpaqueEnvelope($token);

    $viewer = $this->getViewer();

    $ref = id(new DoorkeeperImportEngine())
      ->setViewer($viewer)
      ->setRefs(array($ref))
      ->setThrowOnMissingLink(true)
      ->setContextProperty('github.token', $token)
      ->needLocalOnly($local)
      ->executeOne();

    if ($ref->getSyncFailed()) {
      $xobj = null;
    } else {
      $xobj = $ref->getExternalObject();
    }

    return $xobj;
  }

  private function getExternalObject(NuanceItem $item) {
    if ($this->externalObject === null) {
      $xobj = $this->reloadExternalObject($item, $local = true);
      if ($xobj) {
        $this->externalObject = $xobj;
      } else {
        $this->externalObject = false;
      }
    }

    if ($this->externalObject) {
      return $this->externalObject;
    }

    return null;
  }

  private function getExternalActor(NuanceItem $item) {
    if ($this->externalActor === null) {
      $xobj = $this->reloadExternalActor($item, $local = true);
      if ($xobj) {
        $this->externalActor = $xobj;
      } else {
        $this->externalActor = false;
      }
    }

    if ($this->externalActor) {
      return $this->externalActor;
    }

    return null;
  }

  private function newRawEvent(NuanceItem $item) {
    $type = $item->getItemProperty('api.type');
    $raw = $item->getItemProperty('api.raw', array());

    return NuanceGitHubRawEvent::newEvent($type, $raw);
  }

  public function getItemActions(NuanceItem $item) {
    $actions = array();

    $xobj = $this->getExternalObject($item);
    if ($xobj) {
      $actions[] = $this->newItemAction($item, 'reload')
        ->setName(pht('Reload from GitHub'))
        ->setIcon('fa-refresh')
        ->setWorkflow(true)
        ->setRenderAsForm(true);
    }

    $actions[] = $this->newItemAction($item, 'sync')
      ->setName(pht('Import to Maniphest'))
      ->setIcon('fa-anchor')
      ->setWorkflow(true)
      ->setRenderAsForm(true);

    $actions[] = $this->newItemAction($item, 'raw')
      ->setName(pht('View Raw Event'))
      ->setWorkflow(true)
      ->setIcon('fa-code');

    return $actions;
  }

  public function getItemCurtainPanels(NuanceItem $item) {
    $viewer = $this->getViewer();

    $panels = array();

    $xobj = $this->getExternalObject($item);
    if ($xobj) {
      $xobj_phid = $xobj->getPHID();

      $task = id(new ManiphestTaskQuery())
        ->setViewer($viewer)
        ->withBridgedObjectPHIDs(array($xobj_phid))
        ->executeOne();
      if ($task) {
        $panels[] = $this->newCurtainPanel($item)
          ->setHeaderText(pht('Imported As'))
          ->appendChild($viewer->renderHandle($task->getPHID()));
      }
    }

    $xactor = $this->getExternalActor($item);
    if ($xactor) {
      $panels[] = $this->newCurtainPanel($item)
        ->setHeaderText(pht('GitHub Actor'))
        ->appendChild($viewer->renderHandle($xactor->getPHID()));
    }

    return $panels;
  }

  protected function handleAction(NuanceItem $item, $action) {
    $viewer = $this->getViewer();
    $controller = $this->getController();

    switch ($action) {
      case 'raw':
        $raw = array(
          'api.type' => $item->getItemProperty('api.type'),
          'api.raw' => $item->getItemProperty('api.raw'),
        );

        $raw_output = id(new PhutilJSON())->encodeFormatted($raw);

        $raw_box = id(new AphrontFormTextAreaControl())
          ->setCustomClass('PhabricatorMonospaced')
          ->setLabel(pht('Raw Event'))
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setValue($raw_output);

        $form = id(new AphrontFormView())
          ->appendChild($raw_box);

        return $controller->newDialog()
          ->setWidth(AphrontDialogView::WIDTH_FULL)
          ->setTitle(pht('GitHub Raw Event'))
          ->appendForm($form)
          ->addCancelButton($item->getURI(), pht('Done'));
      case 'sync':
      case 'reload':
        $item->issueCommand($viewer->getPHID(), $action);
        return id(new AphrontRedirectResponse())->setURI($item->getURI());
    }

    return null;
  }

  protected function newItemView(NuanceItem $item) {
    $content = array();

    $content[] = $this->newGitHubEventItemPropertyBox($item);

    return $content;
  }

  private function newGitHubEventItemPropertyBox($item) {
    $viewer = $this->getViewer();

    $property_list = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $event = $this->newRawEvent($item);

    $property_list->addProperty(
      pht('GitHub Event ID'),
      $event->getID());

    $event_uri = $event->getURI();
    if ($event_uri && PhabricatorEnv::isValidRemoteURIForLink($event_uri)) {
      $event_uri = phutil_tag(
        'a',
        array(
          'href' => $event_uri,
          'target' => '_blank',
          'rel' => 'noreferrer',
        ),
        $event_uri);
    }

    if ($event_uri) {
      $property_list->addProperty(
        pht('GitHub Event URI'),
        $event_uri);
    }

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Event Properties'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($property_list);
  }

  protected function handleCommand(
    NuanceItem $item,
    NuanceItemCommand $command) {

    // TODO: This code is no longer reachable, and has moved to
    // CommandImplementation subclasses.

    $action = $command->getCommand();
    switch ($action) {
      case 'sync':
        return $this->syncItem($item, $command);
      case 'reload':
        $this->reloadExternalObject($item);
        return true;
    }

    return null;
  }

  private function syncItem(
    NuanceItem $item,
    NuanceItemCommand $command) {

    $xobj_phid = $item->getItemProperty('doorkeeper.xobj.phid');
    if (!$xobj_phid) {
      throw new Exception(
        pht(
          'Unable to sync: no external object PHID.'));
    }

    // TODO: Write some kind of marker to prevent double-synchronization.

    $viewer = $this->getViewer();

    $xobj = id(new DoorkeeperExternalObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($xobj_phid))
      ->executeOne();
    if (!$xobj) {
      throw new Exception(
        pht(
          'Unable to sync: failed to load object "%s".',
          $xobj_phid));
    }

    $acting_as_phid = $this->getActingAsPHID($item);

    $xactions = array();

    $task = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withBridgedObjectPHIDs(array($xobj_phid))
      ->executeOne();
    if (!$task) {
      $task = ManiphestTask::initializeNewTask($viewer)
        ->setAuthorPHID($acting_as_phid)
        ->setBridgedObjectPHID($xobj_phid);

      $title = $xobj->getProperty('task.title');
      if (!strlen($title)) {
        $title = pht('Nuance Item %d Task', $item->getID());
      }

      $description = $xobj->getProperty('task.description');
      $created = $xobj->getProperty('task.created');
      $state = $xobj->getProperty('task.state');

      $xactions[] = id(new ManiphestTransaction())
        ->setTransactionType(
          ManiphestTaskTitleTransaction::TRANSACTIONTYPE)
        ->setNewValue($title)
        ->setDateCreated($created);

      $xactions[] = id(new ManiphestTransaction())
        ->setTransactionType(
          ManiphestTaskDescriptionTransaction::TRANSACTIONTYPE)
        ->setNewValue($description)
        ->setDateCreated($created);

      $task->setDateCreated($created);

      // TODO: Synchronize state.
    }

    $event = $this->newRawEvent($item);
    $comment = $event->getComment();
    if (strlen($comment)) {
      $xactions[] = id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment(
          id(new ManiphestTransactionComment())
            ->setContent($comment));
    }

    $agent_phid = $command->getAuthorPHID();
    $source = $this->newContentSource($item, $agent_phid);

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
      ->setActingAsPHID($acting_as_phid)
      ->setContentSource($source)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    $xactions = $editor->applyTransactions($task, $xactions);

    return array(
      'objectPHID' => $task->getPHID(),
      'xactionPHIDs' => mpull($xactions, 'getPHID'),
    );
  }

  protected function getActingAsPHID(NuanceItem $item) {
    $actor_phid = $item->getRequestorPHID();

    if ($actor_phid) {
      return $actor_phid;
    }

    return parent::getActingAsPHID($item);
  }

}
