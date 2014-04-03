<?php

final class DifferentialRevisionDetailView extends AphrontView {

  private $revision;
  private $actions;
  private $customFields;
  private $diff;
  private $uri;
  private $actionList;

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }
  public function getURI() {
    return $this->uri;
  }

  public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }
  private function getDiff() {
    return $this->diff;
  }

  public function setRevision(DifferentialRevision $revision) {
    $this->revision = $revision;
    return $this;
  }

  public function setActions(array $actions) {
    $this->actions = $actions;
    return $this;
  }
  private function getActions() {
    return $this->actions;
  }

  public function setActionList(PhabricatorActionListView $list) {
    $this->actionList = $list;
    return $this;
  }

  public function getActionList() {
    return $this->actionList;
  }

  public function setCustomFields(PhabricatorCustomFieldList $list) {
    $this->customFields = $list;
    return $this;
  }

  public function render() {

    $this->requireResource('differential-core-view-css');

    $revision = $this->revision;
    $user = $this->getUser();

    $header = $this->renderHeader($revision);

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObject($revision)
      ->setObjectURI($this->getURI());
    foreach ($this->getActions() as $action) {
      $actions->addAction($action);
    }

    $properties = id(new PHUIPropertyListView())
      ->setUser($user)
      ->setObject($revision);

    $status = $revision->getStatus();
    $local_vcs = $this->getDiff()->getSourceControlSystem();

    $next_step = null;
    if ($status == ArcanistDifferentialRevisionStatus::ACCEPTED) {
      switch ($local_vcs) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $bookmark = $this->getDiff()->getBookmark();
          $next_step = ($bookmark != ''
            ? csprintf('arc land %s', $bookmark)
            : 'arc land');
          break;

        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $branch = $this->getDiff()->getBranch();
          $next_step = ($branch != ''
            ? csprintf('arc land %s', $branch)
            : 'arc land');
          break;

        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $next_step = 'arc commit';
          break;
      }
    }
    if ($next_step) {
      $next_step = phutil_tag('tt', array(), $next_step);
      $properties->addProperty(pht('Next Step'), $next_step);
    }

    $properties->setHasKeyboardShortcuts(true);
    $properties->setActionList($actions);
    $this->setActionList($actions);

    $field_list = $this->customFields;
    if ($field_list) {
      $field_list->appendFieldsToPropertyList(
        $revision,
        $user,
        $properties);
    }

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $object_box;
  }

  private function renderHeader(DifferentialRevision $revision) {
    $view = id(new PHUIHeaderView())
      ->setHeader($revision->getTitle($revision))
      ->setUser($this->getUser())
      ->setPolicyObject($revision);

    $status = $revision->getStatus();
    $status_name =
      DifferentialRevisionStatus::renderFullDescription($status);

    $view->addProperty(PHUIHeaderView::PROPERTY_STATUS, $status_name);

    return $view;
  }

  public static function renderTagForRevision(
    DifferentialRevision $revision) {

    $status = $revision->getStatus();
    $status_name =
      ArcanistDifferentialRevisionStatus::getNameForRevisionStatus($status);

    return id(new PHUITagView())
      ->setType(PHUITagView::TYPE_STATE)
      ->setName($status_name);
  }

}
