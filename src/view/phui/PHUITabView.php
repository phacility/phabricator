<?php

final class PHUITabView extends AphrontTagView {

  private $name;
  private $key;
  private $keyLocked;
  private $contentID;
  private $color;

  public function setKey($key) {
    if ($this->keyLocked) {
      throw new Exception(
        pht(
          'Attempting to change the key of a tab with a locked key ("%s").',
          $this->key));
    }

    $this->key = $key;
    return $this;
  }

  public function hasKey() {
    return ($this->key !== null);
  }

  public function getKey() {
    if (!$this->hasKey()) {
      throw new PhutilInvalidStateException('setKey');
    }

    return $this->key;
  }

  public function lockKey() {
    if (!$this->hasKey()) {
      throw new PhutilInvalidStateException('setKey');
    }

    $this->keyLocked = true;

    return $this;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function getContentID() {
    if ($this->contentID === null) {
      $this->contentID = celerity_generate_unique_node_id();
    }

    return $this->contentID;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function getColor() {
    return $this->color;
  }

  public function newMenuItem() {
    $item = id(new PHUIListItemView())
      ->setName($this->getName())
      ->setKey($this->getKey())
      ->setType(PHUIListItemView::TYPE_LINK)
      ->setHref('#')
      ->addSigil('phui-tab-view')
      ->setMetadata(
        array(
          'tabKey' => $this->getKey(),
        ));

    $color = $this->getColor();
    if ($color !== null) {
      $item->setStatusColor($color);
    }

    return $item;
  }

}
