<?php

final class PhabricatorRoleListView extends AphrontView {

  private $roles;
  private $showMember;
  private $showWatching;
  private $noDataString;

  public function setRoles(array $roles) {
    $this->roles = $roles;
    return $this;
  }

  public function getRoles() {
    return $this->roles;
  }

  public function setShowWatching($watching) {
    $this->showWatching = $watching;
    return $this;
  }

  public function setShowMember($member) {
    $this->showMember = $member;
    return $this;
  }

  public function setNoDataString($text) {
    $this->noDataString = $text;
    return $this;
  }

  public function renderList() {
    $viewer = $this->getUser();
    $viewer_phid = $viewer->getPHID();
    $roles = $this->getRoles();

    $handles = $viewer->loadHandles(mpull($roles, 'getPHID'));

    $no_data = pht('No roles found.');
    if ($this->noDataString) {
      $no_data = $this->noDataString;
    }

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setNoDataString($no_data);

    foreach ($roles as $key => $role) {
      $id = $role->getID();

      $icon = $role->getDisplayIconIcon();
      $icon_icon = id(new PHUIIconView())
        ->setIcon($icon);

      $icon_name = $role->getDisplayIconName();

      $item = id(new PHUIObjectItemView())
        ->setObject($role)
        ->setHeader($role->getName())
        ->setHref("/role/view/{$id}/")
        ->setImageURI($role->getProfileImageURI())
        ->addAttribute(
          array(
            $icon_icon,
            ' ',
            $icon_name,
          ));

      if ($role->getStatus() == PhabricatorRoleStatus::STATUS_ARCHIVED) {
        $item->addIcon('fa-ban', pht('Archived'));
        $item->setDisabled(true);
      }

      if ($this->showMember) {
        $is_member = $role->isUserMember($viewer_phid);
        if ($is_member) {
          $item->addIcon('fa-user', pht('Member'));
        }
      }

      if ($this->showWatching) {
        $is_watcher = $role->isUserWatcher($viewer_phid);
        if ($is_watcher) {
          $item->addIcon('fa-eye', pht('Watching'));
        }
      }

      $subtype = $role->newSubtypeObject();
      if ($subtype && $subtype->hasTagView()) {
        $subtype_tag = $subtype->newTagView()
          ->setSlimShady(true);
        $item->addAttribute($subtype_tag);
      }

      $list->addItem($item);
    }

    return $list;
  }

  public function render() {
    return $this->renderList();
  }

}
