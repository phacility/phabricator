<?php

abstract class PhabricatorProjectUserListView extends AphrontView {

  private $project;
  private $userPHIDs;
  private $limit;
  private $background;
  private $showNote;

  public function setProject(PhabricatorProject $project) {
    $this->project = $project;
    return $this;
  }

  public function getProject() {
    return $this->project;
  }

  public function setUserPHIDs(array $user_phids) {
    $this->userPHIDs = $user_phids;
    return $this;
  }

  public function getUserPHIDs() {
    return $this->userPHIDs;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function getLimit() {
    return $this->limit;
  }

  public function setBackground($color) {
    $this->background = $color;
    return $this;
  }

  public function setShowNote($show) {
    $this->showNote = $show;
    return $this;
  }

  abstract protected function canEditList();
  abstract protected function getNoDataString();
  abstract protected function getRemoveURI($phid);
  abstract protected function getHeaderText();
  abstract protected function getMembershipNote();

  public function render() {
    $viewer = $this->getViewer();
    $project = $this->getProject();
    $user_phids = $this->getUserPHIDs();

    $can_edit = $this->canEditList();
    $no_data = $this->getNoDataString();

    $list = id(new PHUIObjectItemListView())
      ->setNoDataString($no_data);

    $limit = $this->getLimit();

    // If we're showing everything, show oldest to newest. If we're showing
    // only a slice, show newest to oldest.
    if (!$limit) {
      $user_phids = array_reverse($user_phids);
    }

    $handles = $viewer->loadHandles($user_phids);

    // Always put the viewer first if they are on the list.
    $user_phids = array_fuse($user_phids);
    $user_phids =
      array_select_keys($user_phids, array($viewer->getPHID())) +
      $user_phids;

    if ($limit) {
      $render_phids = array_slice($user_phids, 0, $limit);
    } else {
      $render_phids = $user_phids;
    }

    foreach ($render_phids as $user_phid) {
      $handle = $handles[$user_phid];

      $item = id(new PHUIObjectItemView())
        ->setHeader($handle->getFullName())
        ->setHref($handle->getURI())
        ->setImageURI($handle->getImageURI());

      $icon = id(new PHUIIconView())
        ->setIcon($handle->getIcon());

      $subtitle = $handle->getSubtitle();

      $item->addAttribute(array($icon, ' ', $subtitle));

      if ($can_edit && !$limit) {
        $remove_uri = $this->getRemoveURI($user_phid);

        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-times')
            ->setName(pht('Remove'))
            ->setHref($remove_uri)
            ->setWorkflow(true));
      }

      $list->addItem($item);
    }

    if ($user_phids) {
      $header_text = pht(
        '%s (%s)',
        $this->getHeaderText(),
        phutil_count($user_phids));
    } else {
      $header_text = $this->getHeaderText();
    }

    $id = $project->getID();

    $header = id(new PHUIHeaderView())
      ->setHeader($header_text);

    if ($limit) {
      $header->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setIcon(
            id(new PHUIIconView())
              ->setIcon('fa-list-ul'))
          ->setText(pht('View All'))
          ->setHref("/project/members/{$id}/"));
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setObjectList($list);

    if ($this->showNote) {
      if ($this->getMembershipNote()) {
        $info = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_PLAIN)
        ->appendChild($this->getMembershipNote());
        $box->setInfoView($info);
      }
    }

    if ($this->background) {
      $box->setBackground($this->background);
    }

    return $box;
  }

}
