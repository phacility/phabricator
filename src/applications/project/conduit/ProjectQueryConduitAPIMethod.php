<?php

final class ProjectQueryConduitAPIMethod extends ProjectConduitAPIMethod {

  public function getAPIMethodName() {
    return 'project.query';
  }

  public function getMethodDescription() {
    return pht('Execute searches for Projects.');
  }

  protected function defineParamTypes() {

    $statuses = array(
      PhabricatorProjectQuery::STATUS_ANY,
      PhabricatorProjectQuery::STATUS_OPEN,
      PhabricatorProjectQuery::STATUS_CLOSED,
      PhabricatorProjectQuery::STATUS_ACTIVE,
      PhabricatorProjectQuery::STATUS_ARCHIVED,
    );

    $status_const = $this->formatStringConstants($statuses);

    return array(
      'ids'               => 'optional list<int>',
      'names'             => 'optional list<string>',
      'phids'             => 'optional list<phid>',
      'slugs'             => 'optional list<string>',
      'icons'             => 'optional list<string>',
      'colors'            => 'optional list<string>',
      'status'            => 'optional '.$status_const,

      'members'           => 'optional list<phid>',

      'limit'             => 'optional int',
      'offset'            => 'optional int',
    );
  }

  protected function defineReturnType() {
    return 'list';
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = new PhabricatorProjectQuery();
    $query->setViewer($request->getUser());
    $query->needMembers(true);
    $query->needSlugs(true);

    $ids = $request->getValue('ids');
    if ($ids) {
      $query->withIDs($ids);
    }

    $names = $request->getValue('names');
    if ($names) {
      $query->withNames($names);
    }

    $status = $request->getValue('status');
    if ($status) {
      $query->withStatus($status);
    }

    $phids = $request->getValue('phids');
    if ($phids) {
      $query->withPHIDs($phids);
    }

    $slugs = $request->getValue('slugs');
    if ($slugs) {
      $query->withSlugs($slugs);
    }

    $request->getValue('icons');
    if ($request->getValue('icons')) {
      $icons = array();
      $query->withIcons($icons);
    }

    $colors = $request->getValue('colors');
    if ($colors) {
      $query->withColors($colors);
    }

    $members = $request->getValue('members');
    if ($members) {
      $query->withMemberPHIDs($members);
    }

    $limit = $request->getValue('limit');
    if ($limit) {
      $query->setLimit($limit);
    }

    $offset = $request->getValue('offset');
    if ($offset) {
      $query->setOffset($offset);
    }

    $pager = $this->newPager($request);
    $results = $query->executeWithCursorPager($pager);
    $projects = $this->buildProjectInfoDictionaries($results);

    // TODO: This is pretty hideous.
    $slug_map = array();
    if ($slugs) {
      foreach ($slugs as $slug) {
        $normal = PhabricatorSlug::normalizeProjectSlug($slug);
        foreach ($projects as $project) {
          if (in_array($normal, $project['slugs'])) {
            $slug_map[$slug] = $project['phid'];
          }
        }
      }
    }

    $result = array(
      'data' => $projects,
      'slugMap' => $slug_map,
    );

    return $this->addPagerResults($result, $pager);
  }

}
