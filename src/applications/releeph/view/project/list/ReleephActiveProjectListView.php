<?php

final class ReleephActiveProjectListView extends AphrontView {

  private $releephProjects;

  public function setReleephProjects(array $releeph_projects) {
    $this->releephProjects = $releeph_projects;
    return $this;
  }

  public function render() {
    $rows = array();
    foreach ($this->releephProjects as $releeph_project) {
      $project_uri = $releeph_project->getURI();

      $name_link = phutil_tag(
        'a',
        array(
          'href' => $project_uri,
          'style' => 'font-weight: bold;',
        ),
        $releeph_project->getName());

      $edit_button = phutil_tag(
        'a',
        array(
          'href'  => $releeph_project->getURI('edit/'),
          'class' => 'small grey button',
        ),
        'Edit');

      $deactivate_button = javelin_tag(
        'a',
        array(
          'href'  => $releeph_project->getURI('action/deactivate/'),
          'class' => 'small grey button',
          'sigil' => 'workflow',
        ),
        'Remove');

      $arc_project = $releeph_project->loadArcanistProject();
      if ($arc_project) {
        $arc_project_name = $arc_project->getName();
      } else {
        $arc_project_name = phutil_tag(
          'i',
          array(),
          'Deleted Arcanist Project');
      }

      $repo = $releeph_project->loadPhabricatorRepository();

      if ($repo) {
        $vcs_type =
          PhabricatorRepositoryType::getNameForRepositoryType(
            $repo->getVersionControlSystem());

        $rows[] = array(
          $name_link,
          $repo->getName(),
          $arc_project_name,
          $vcs_type,
          $edit_button,
          $deactivate_button,
        );
      } else {
        $rows[] = array(
          $name_link,
          phutil_tag('i', array(), 'Deleted Repository'),
          $arc_project_name,
          null,
          null,
          $deactivate_button,
        );
      }
    }

    $table = new AphrontTableView($rows);

    $table->setHeaders(array(
      'Name',
      'Repository',
      'Arcanist Project',
      'Type',
      '',
      ''
    ));

    $table->setColumnClasses(array(
      null,
      null,
      'wide',
      null,
      'action',
      'action'
    ));

    return $table->render();
  }

}
