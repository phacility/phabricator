<?php

final class ManiphestTaskSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'assignedPHIDs',
      $this->readUsersFromRequest($request, 'assigned'));

    $saved->setParameter('withUnassigned', $request->getBool('withUnassigned'));

    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    $saved->setParameter('statuses', $request->getArr('statuses'));
    $saved->setParameter('priorities', $request->getArr('priorities'));
    $saved->setParameter('order', $request->getStr('order'));

    $ids = $request->getStrList('ids');
    foreach ($ids as $key => $id) {
      $id = trim($id, ' Tt');
      if (!$id || !is_numeric($id)) {
        unset($ids[$key]);
      } else {
        $ids[$key] = $id;
      }
    }
    $saved->setParameter('ids', $ids);

    $saved->setParameter('fulltext', $request->getStr('fulltext'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new ManiphestTaskQuery());

    $author_phids = $saved->getParameter('authorPHIDs');
    if ($author_phids) {
      $query->withAuthors($author_phids);
    }

    $with_unassigned = $saved->getParameter('withUnassigned');
    if ($with_unassigned) {
      $query->withOwners(array(null));
    } else {
      $assigned_phids = $saved->getParameter('assignedPHIDs', array());
      if ($assigned_phids) {
        $query->withOwners($assigned_phids);
      }
    }

    $statuses = $saved->getParameter('statuses');
    if ($statuses) {
      $query->withStatuses($statuses);
    }

    $priorities = $saved->getParameter('priorities');
    if ($priorities) {
      $query->withPriorities($priorities);
    }

    $order = $saved->getParameter('order');
    $order = idx($this->getOrderValues(), $order);
    if ($order) {
      $query->setOrderBy($order);
    } else {
      $query->setOrderBy(head($this->getOrderValues()));
    }

    $ids = $saved->getParameter('ids');
    if ($ids) {
      $query->withIDs($ids);
    }

    $fulltext = $saved->getParameter('fulltext');
    if (strlen($fulltext)) {
      $query->withFullTextSearch($fulltext);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $assigned_phids = $saved->getParameter('assignedPHIDs', array());
    $author_phids = $saved->getParameter('authorPHIDs', array());

    $all_phids = array_merge($assigned_phids, $author_phids);

    if ($all_phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->requireViewer())
        ->withPHIDs($all_phids)
        ->execute();
    } else {
      $handles = array();
    }

    $assigned_tokens = array_select_keys($handles, $assigned_phids);
    $assigned_tokens = mpull($assigned_tokens, 'getFullName', 'getPHID');

    $author_tokens = array_select_keys($handles, $author_phids);
    $author_tokens = mpull($author_tokens, 'getFullName', 'getPHID');

    $with_unassigned = $saved->getParameter('withUnassigned');

    $statuses = $saved->getParameter('statuses', array());
    $statuses = array_fuse($statuses);
    $status_control = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Status'));
    foreach (ManiphestTaskStatus::getTaskStatusMap() as $status => $name) {
      $status_control->addCheckbox(
        'statuses[]',
        $status,
        $name,
        isset($statuses[$status]));
    }

    $priorities = $saved->getParameter('priorities', array());
    $priorities = array_fuse($priorities);
    $priority_control = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Priority'));
    foreach (ManiphestTaskPriority::getTaskPriorityMap() as $pri => $name) {
      $priority_control->addCheckbox(
        'priorities[]',
        $pri,
        $name,
        isset($priorities[$pri]));
    }

    $ids = $saved->getParameter('ids', array());

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/accounts/')
          ->setName('assigned')
          ->setLabel(pht('Assigned To'))
          ->setValue($assigned_tokens))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'withUnassigned',
            1,
            pht('Show only unassigned tasks.'),
            $with_unassigned))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/accounts/')
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_tokens))
      ->appendChild($status_control)
      ->appendChild($priority_control)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('order')
          ->setLabel(pht('Order'))
          ->setValue($saved->getParameter('order'))
          ->setOptions($this->getOrderOptions()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('fulltext')
          ->setLabel(pht('Contains Text'))
          ->setValue($saved->getParameter('fulltext')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('ids')
          ->setLabel(pht('Task IDs'))
          ->setValue(implode(', ', $ids)));
  }

  protected function getURI($path) {
    return '/maniphest/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['assigned'] = pht('Assigned');
      $names['authored'] = pht('Authored');
    }

    $names['open'] = pht('Open Tasks');
    $names['all'] = pht('All Tasks');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    $viewer_phid = $this->requireViewer()->getPHID();

    switch ($query_key) {
      case 'all':
        return $query;
      case 'assigned':
        return $query
          ->setParameter('assignedPHIDs', array($viewer_phid))
          ->setParameter('statuses', array(ManiphestTaskStatus::STATUS_OPEN));
      case 'open':
        return $query
          ->setParameter('statuses', array(ManiphestTaskStatus::STATUS_OPEN));
      case 'authored':
        return $query
          ->setParameter('authorPHIDs', array($viewer_phid))
          ->setParameter('statuses', array(ManiphestTaskStatus::STATUS_OPEN));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getOrderOptions() {
    return array(
      'priority' => pht('Priority'),
      'updated' => pht('Date Updated'),
      'created' => pht('Date Created'),
      'title' => pht('Title'),
    );
  }

  private function getOrderValues() {
    return array(
      'priority' => ManiphestTaskQuery::ORDER_PRIORITY,
      'updated' => ManiphestTaskQuery::ORDER_MODIFIED,
      'created' => ManiphestTaskQuery::ORDER_CREATED,
      'title' => ManiphestTaskQuery::ORDER_TITLE,
    );
  }

}
