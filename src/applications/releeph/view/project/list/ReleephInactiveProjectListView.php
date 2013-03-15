<?php

final class ReleephInactiveProjectListView extends AphrontView {

  private $releephProjects;

  public function setReleephProjects(array $releeph_projects) {
    $this->releephProjects = $releeph_projects;
    return $this;
  }

  public function render() {
    $rows = array();

    $phids = array();
    foreach ($this->releephProjects as $releeph_project) {
      $phids[] = $releeph_project->getCreatedByUserPHID();
      if ($phid = $releeph_project->getDetail('last_deactivated_user')) {
        $phids[] = $phid;
      }
    }

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($this->getUser())
      ->loadHandles();

    foreach ($this->releephProjects as $releeph_project) {
      $repository = $releeph_project->loadPhabricatorRepository();

      if (!$repository) {
        // Ignore projects referring to repositories that have been deleted.
        continue;
      }

      $activate_link = javelin_tag(
        'a',
        array(
          'href'  => $releeph_project->getURI('action/activate/'),
          'class' => 'small grey button',
          'sigil' => 'workflow',
        ),
        'Revive');

      $delete_link = javelin_tag(
        'a',
        array(
          'href'  => $releeph_project->getURI('action/delete/'),
          'class' => 'small grey button',
          'sigil' => 'workflow',
        ),
        'Delete');

      $rows[] = array(
        $releeph_project->getName(),
        $repository->getName(),
        $this->renderCreationInfo($releeph_project, $handles),
        $this->renderDeletionInfo($releeph_project, $handles),
        $activate_link,
        $delete_link,
      );
    }

    $table = new AphrontTableView($rows);

    $table->setHeaders(array(
      'Name',
      'Repository',
      'Created',
      'Deleted',
      '',
      '',
    ));

    $table->setColumnClasses(array(
      null,
      null,
      null,
      'wide',
      'action',
      'action',
    ));

    return $table->render();
  }

  private function renderCreationInfo($releeph_project, $handles) {
    $creator = $handles[$releeph_project->getCreatedByUserPHID()];
    $when = $releeph_project->getDateCreated();
    return hsprintf(
      '%s by %s',
      phabricator_relative_date($when, $this->user),
      $creator->getName());
  }

  private function renderDeletionInfo($releeph_project, $handles) {
    $deleted_on = $releeph_project->getDetail('last_deactivated_time');

    $deleted_by_name = null;
    $deleted_by_phid = $releeph_project->getDetail('last_deactivated_user');
    if ($deleted_by_phid) {
      $deleted_by_name = $handles[$deleted_by_phid]->getName();
    } else {
      $deleted_by_name = 'unknown';
    }

    return hsprintf(
      '%s by %s',
      phabricator_relative_date($deleted_on, $this->user),
      $deleted_by_name);
  }

}
