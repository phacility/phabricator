<?php

abstract class PhabricatorProjectUserListView
  extends AphrontView {

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
    $supports_edit = $project->supportsEditMembers();
    $no_data = $this->getNoDataString();

    $list = id(new PHUIObjectItemListView())
      ->setNoDataString($no_data);

    $limit = $this->getLimit();
    $is_panel = (bool)$limit;

    $handles = $viewer->loadHandles($user_phids);

    // Reorder users in display order. We're going to put the viewer first
    // if they're a member, then enabled users, then disabled/invalid users.

    $phid_map = array();
    foreach ($user_phids as $user_phid) {
      $handle = $handles[$user_phid];

      $is_viewer = ($user_phid === $viewer->getPHID());
      $is_enabled = ($handle->isComplete() && !$handle->isDisabled());

      // If we're showing the main member list, show oldest to newest. If we're
      // showing only a slice in a panel, show newest to oldest.
      if ($limit) {
        $order_scalar = 1;
      } else {
        $order_scalar = -1;
      }

      $phid_map[$user_phid] = id(new PhutilSortVector())
        ->addInt($is_viewer ? 0 : 1)
        ->addInt($is_enabled ? 0 : 1)
        ->addInt($order_scalar * count($phid_map));
    }
    $phid_map = msortv($phid_map, 'getSelf');

    $handles = iterator_to_array($handles);
    $handles = array_select_keys($handles, array_keys($phid_map));

    if ($limit) {
      $handles = array_slice($handles, 0, $limit);
    }

    foreach ($handles as $user_phid => $handle) {
      $item = id(new PHUIObjectItemView())
        ->setHeader($handle->getFullName())
        ->setHref($handle->getURI())
        ->setImageURI($handle->getImageURI());

      if ($handle->isDisabled()) {
        if ($is_panel) {
          // Don't show disabled users in the panel view at all.
          continue;
        }

        $item
          ->setDisabled(true)
          ->addAttribute(pht('Disabled'));
      } else {
        $icon = id(new PHUIIconView())
          ->setIcon($handle->getIcon());

        $subtitle = $handle->getSubtitle();

        $item->addAttribute(array($icon, ' ', $subtitle));
      }

      if ($supports_edit && !$is_panel) {
        $remove_uri = $this->getRemoveURI($user_phid);

        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-times')
            ->setName(pht('Remove'))
            ->setHref($remove_uri)
            ->setDisabled(!$can_edit)
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
      $list->newTailButton()
        ->setText(pht('View All'))
        ->setHref("/project/members/{$id}/");
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
