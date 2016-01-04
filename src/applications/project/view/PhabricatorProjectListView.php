<?php

final class PhabricatorProjectListView extends AphrontView {

  private $projects;

  public function setProjects(array $projects) {
    $this->projects = $projects;
    return $this;
  }

  public function getProjects() {
    return $this->projects;
  }

  public function renderList() {
    $viewer = $this->getUser();
    $projects = $this->getProjects();

    $handles = $viewer->loadHandles(mpull($projects, 'getPHID'));

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($projects as $key => $project) {
      $id = $project->getID();

      $tag_list = id(new PHUIHandleTagListView())
        ->setSlim(true)
        ->setHandles(array($handles[$project->getPHID()]));

      $item = id(new PHUIObjectItemView())
        ->setHeader($project->getName())
        ->setHref("/project/view/{$id}/")
        ->setImageURI($project->getProfileImageURI())
        ->addAttribute($tag_list);

      if ($project->getStatus() == PhabricatorProjectStatus::STATUS_ARCHIVED) {
        $item->addIcon('delete-grey', pht('Archived'));
        $item->setDisabled(true);
      }

      $list->addItem($item);
    }

    return $list;
  }

  public function render() {
    return $this->renderList();
  }

}
