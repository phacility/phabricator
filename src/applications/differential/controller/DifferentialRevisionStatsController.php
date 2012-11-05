<?php

final class DifferentialRevisionStatsController extends DifferentialController {
  private $filter;

  private function loadRevisions($phid) {
    $table = new DifferentialRevision();
    $conn_r = $table->establishConnection('r');
    $rows = queryfx_all(
      $conn_r,
      'SELECT revisions.* FROM %T revisions ' .
      'JOIN %T comments ON comments.revisionID = revisions.id ' .
      'JOIN (' .
      ' SELECT revisionID FROM %T WHERE objectPHID = %s ' .
      ' UNION ALL ' .
      ' SELECT id from differential_revision WHERE authorPHID = %s) rel ' .
      'ON (comments.revisionID = rel.revisionID)' .
      'WHERE comments.action = %s' .
      'AND comments.authorPHID = %s',
      $table->getTableName(),
      id(new DifferentialComment())->getTableName(),
      DifferentialRevision::RELATIONSHIP_TABLE,
      $phid,
      $phid,
      $this->filter,
      $phid
    );
    return $table->loadAllFromArray($rows);
  }

  private function loadComments($phid) {
    $table = new DifferentialComment();
    $conn_r = $table->establishConnection('r');
    $rows = queryfx_all(
      $conn_r,
      'SELECT comments.* FROM %T comments ' .
      'JOIN (' .
      ' SELECT revisionID FROM %T WHERE objectPHID = %s ' .
      ' UNION ALL ' .
      ' SELECT id from differential_revision WHERE authorPHID = %s) rel ' .
      'ON (comments.revisionID = rel.revisionID)' .
      'WHERE comments.action = %s' .
      'AND comments.authorPHID = %s',
      $table->getTableName(),
      DifferentialRevision::RELATIONSHIP_TABLE,
      $phid,
      $phid,
      $this->filter,
      $phid
    );

    return $table->loadAllFromArray($rows);
  }

  private function loadDiffs(array $revisions) {
    if (!$revisions) {
      return array();
    }

    $diff_teml = new DifferentialDiff();
    $diffs = $diff_teml->loadAllWhere(
      'revisionID in (%Ld)',
      array_keys($revisions)
    );
    return $diffs;
  }

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      $phid_arr = $request->getArr('view_user');
      $view_target = head($phid_arr);
      return id(new AphrontRedirectResponse())
        ->setURI($request->getRequestURI()->alter('phid', $view_target));
    }

    $params = array_filter(
      array(
        'phid' => $request->getStr('phid'),
      ));

    // Fill in the defaults we'll actually use for calculations if any
    // parameters are missing.
    $params += array(
      'phid' => $user->getPHID(),
    );

    $side_nav = new AphrontSideNavFilterView();
    $side_nav->setBaseURI(id(new PhutilURI('/differential/stats/'))
                          ->alter('phid', $params['phid']));
    foreach (array(
               DifferentialAction::ACTION_CLOSE,
               DifferentialAction::ACTION_ACCEPT,
               DifferentialAction::ACTION_REJECT,
               DifferentialAction::ACTION_UPDATE,
               DifferentialAction::ACTION_COMMENT,
             ) as $action) {
      $verb = ucfirst(DifferentialAction::getActionPastTenseVerb($action));
      $side_nav->addFilter($action, $verb);
    }
    $this->filter =
      $side_nav->selectFilter($this->filter,
                              DifferentialAction::ACTION_CLOSE);

    $panels = array();
    $handles = $this->loadViewerHandles(array($params['phid']));

    $filter_form = id(new AphrontFormView())
      ->setAction('/differential/stats/'.$this->filter.'/')
      ->setUser($user);

    $filter_form->appendChild(
      $this->renderControl($params['phid'], $handles));
    $filter_form->appendChild(id(new AphrontFormSubmitControl())
                              ->setValue('Filter Revisions'));

    $side_nav->appendChild($filter_form);

    $comments = $this->loadComments($params['phid']);
    $revisions = $this->loadRevisions($params['phid']);
    $diffs = $this->loadDiffs($revisions);

    $panel = new AphrontPanelView();
    $panel->setHeader('Differential rate analysis');
    $panel->appendChild(
      id(new DifferentialRevisionStatsView())
      ->setComments($comments)
      ->setFilter($this->filter)
      ->setRevisions($revisions)
      ->setDiffs($diffs)
      ->setUser($user));
    $panels[] = $panel;

    foreach ($panels as $panel) {
      $side_nav->appendChild($panel);
    }

    return $this->buildStandardPageResponse(
      $side_nav,
      array(
        'title' => 'Differential statistics',
      ));
  }

  private function renderControl($view_phid, $handles) {
    $value = array();
    if ($view_phid) {
      $value = array(
        $view_phid => $handles[$view_phid]->getFullName(),
      );
    }
    return id(new AphrontFormTokenizerControl())
      ->setDatasource('/typeahead/common/users/')
      ->setLabel('View User')
      ->setName('view_user')
      ->setValue($value)
      ->setLimit(1);
  }

}
