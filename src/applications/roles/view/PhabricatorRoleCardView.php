<?php

final class PhabricatorRoleCardView extends AphrontTagView {

  private $role;
  private $viewer;
  private $tag;

  public function setRole(PhabricatorRole $role) {
    $this->role = $role;
    return $this;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function setTag($tag) {
    $this->tag = $tag;
    return $this;
  }

  protected function getTagName() {
    if ($this->tag) {
      return $this->tag;
    }
    return 'div';
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'project-card-view';

    $color = $this->role->getColor();
    $classes[] = 'project-card-'.$color;

    return array(
      'class' => implode(' ', $classes),
    );
  }

  protected function getTagContent() {

    $role = $this->role;
    $viewer = $this->viewer;
    require_celerity_resource('project-card-view-css');

    $icon = $role->getDisplayIconIcon();
    $icon_name = $role->getDisplayIconName();
    $tag = id(new PHUITagView())
      ->setIcon($icon)
      ->setName($icon_name)
      ->addClass('project-view-header-tag')
      ->setType(PHUITagView::TYPE_SHADE);

    $header = id(new PHUIHeaderView())
      ->setHeader(array($role->getDisplayName(), $tag))
      ->setUser($viewer)
      ->setPolicyObject($role)
      ->setImage($role->getProfileImageURI());

    if ($role->getStatus() == PhabricatorRoleStatus::STATUS_ACTIVE) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'red', pht('Archived'));
    }

    $description = null;

    $card = phutil_tag(
      'div',
      array(
        'class' => 'project-card-inner',
      ),
      array(
        $header,
        $description,
      ));

    return $card;
  }

}
