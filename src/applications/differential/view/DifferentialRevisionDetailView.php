<?php

final class DifferentialRevisionDetailView extends AphrontView {

  private $revision;
  private $actions;
  private $auxiliaryFields = array();
  private $diff;

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

  public function setAuxiliaryFields(array $fields) {
    assert_instances_of($fields, 'DifferentialFieldSpecification');
    $this->auxiliaryFields = $fields;
    return $this;
  }

  public function render() {

    require_celerity_resource('differential-core-view-css');

    $revision = $this->revision;
    $user = $this->getUser();

    $header = $this->renderHeader($revision);

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObject($revision);
    foreach ($this->getActions() as $action) {
      $obj = id(new PhabricatorActionView())
        ->setIcon(idx($action, 'icon', 'edit'))
        ->setName($action['name'])
        ->setHref(idx($action, 'href'))
        ->setWorkflow(idx($action, 'sigil') == 'workflow')
        ->setRenderAsForm(!empty($action['instant']))
        ->setUser($user)
        ->setDisabled(idx($action, 'disabled', false));
      $actions->addAction($obj);
    }

    $properties = id(new PhabricatorPropertyListView())
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

    foreach ($this->auxiliaryFields as $field) {
      $value = $field->renderValueForRevisionView();
      if ($value !== null) {
        $label = rtrim($field->renderLabelForRevisionView(), ':');
        $properties->addProperty($label, $value);
      }
    }
    $properties->setHasKeyboardShortcuts(true);

    return hsprintf(
      '%s%s%s',
      $header->render(),
      $actions->render(),
      $properties->render());
  }

  private function renderHeader(DifferentialRevision $revision) {
    $view = id(new PhabricatorHeaderView())
      ->setHeader($revision->getTitle($revision));

    $view->addTag(self::renderTagForRevision($revision));

    return $view;
  }

  public static function renderTagForRevision(
    DifferentialRevision $revision) {

    $status = $revision->getStatus();
    $status_name =
      ArcanistDifferentialRevisionStatus::getNameForRevisionStatus($status);
    $status_color =
      DifferentialRevisionStatus::getRevisionStatusTagColor($status);

    return id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setName($status_name)
      ->setBackgroundColor($status_color);
  }
}
