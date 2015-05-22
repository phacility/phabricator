<?php

final class ManiphestQueryConduitAPIMethod extends ManiphestConduitAPIMethod {

  public function getAPIMethodName() {
    return 'maniphest.query';
  }

  public function getMethodDescription() {
    return pht('Execute complex searches for Maniphest tasks.');
  }

  protected function defineParamTypes() {
    $statuses = array(
      ManiphestTaskQuery::STATUS_ANY,
      ManiphestTaskQuery::STATUS_OPEN,
      ManiphestTaskQuery::STATUS_CLOSED,
      ManiphestTaskQuery::STATUS_RESOLVED,
      ManiphestTaskQuery::STATUS_WONTFIX,
      ManiphestTaskQuery::STATUS_INVALID,
      ManiphestTaskQuery::STATUS_SPITE,
      ManiphestTaskQuery::STATUS_DUPLICATE,
    );
    $status_const = $this->formatStringConstants($statuses);

    $orders = array(
      ManiphestTaskQuery::ORDER_PRIORITY,
      ManiphestTaskQuery::ORDER_CREATED,
      ManiphestTaskQuery::ORDER_MODIFIED,
    );
    $order_const = $this->formatStringConstants($orders);

    return array(
      'ids'               => 'optional list<uint>',
      'phids'             => 'optional list<phid>',
      'ownerPHIDs'        => 'optional list<phid>',
      'authorPHIDs'       => 'optional list<phid>',
      'projectPHIDs'      => 'optional list<phid>',
      'ccPHIDs'           => 'optional list<phid>',
      'fullText'          => 'optional string',

      'status'            => 'optional '.$status_const,
      'order'             => 'optional '.$order_const,

      'limit'             => 'optional int',
      'offset'            => 'optional int',
    );
  }

  protected function defineReturnType() {
    return 'list';
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = id(new ManiphestTaskQuery())
      ->setViewer($request->getUser())
      ->needProjectPHIDs(true)
      ->needSubscriberPHIDs(true);

    $task_ids = $request->getValue('ids');
    if ($task_ids) {
      $query->withIDs($task_ids);
    }

    $task_phids = $request->getValue('phids');
    if ($task_phids) {
      $query->withPHIDs($task_phids);
    }

    $owners = $request->getValue('ownerPHIDs');
    if ($owners) {
      $query->withOwners($owners);
    }

    $authors = $request->getValue('authorPHIDs');
    if ($authors) {
      $query->withAuthors($authors);
    }

    $projects = $request->getValue('projectPHIDs');
    if ($projects) {
      $query->withEdgeLogicPHIDs(
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        PhabricatorQueryConstraint::OPERATOR_AND,
        $projects);
    }

    $ccs = $request->getValue('ccPHIDs');
    if ($ccs) {
      $query->withSubscribers($ccs);
    }

    $full_text = $request->getValue('fullText');
    if ($full_text) {
      $query->withFullTextSearch($full_text);
    }

    $status = $request->getValue('status');
    if ($status) {
      $query->withStatus($status);
    }

    $order = $request->getValue('order');
    if ($order) {
      $query->setOrderBy($order);
    }

    $limit = $request->getValue('limit');
    if ($limit) {
      $query->setLimit($limit);
    }

    $offset = $request->getValue('offset');
    if ($offset) {
      $query->setOffset($offset);
    }

    $results = $query->execute();
    return $this->buildTaskInfoDictionaries($results);
  }

}
