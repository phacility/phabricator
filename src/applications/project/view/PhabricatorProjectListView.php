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
    $viewer_phid = $viewer->getPHID();
    $projects = $this->getProjects();

    $handles = $viewer->loadHandles(mpull($projects, 'getPHID'));

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($projects as $key => $project) {
      $id = $project->getID();

      $icon = $project->getDisplayIconIcon();
      $icon_icon = id(new PHUIIconView())
        ->setIcon($icon);

      $icon_name = $project->getDisplayIconName();

      $item = id(new PHUIObjectItemView())
        ->setHeader($project->getName())
        ->setHref("/project/view/{$id}/")
        ->setImageURI($project->getProfileImageURI())
        ->addAttribute(
          array(
            $icon_icon,
            ' ',
            $icon_name,
          ));

      if ($project->getStatus() == PhabricatorProjectStatus::STATUS_ARCHIVED) {
        $item->addIcon('delete-grey', pht('Archived'));
        $item->setDisabled(true);
      }

      $is_member = $project->isUserMember($viewer_phid);
      $is_watcher = $project->isUserWatcher($viewer_phid);

      if ($is_member) {
        $item->addIcon('fa-user', pht('Member'));
      }

      if ($is_watcher) {
        $item->addIcon('fa-eye', pht('Watching'));
      }

      $list->addItem($item);
    }

    return $list;
  }

  public function render() {
    return $this->renderList();
  }

}
