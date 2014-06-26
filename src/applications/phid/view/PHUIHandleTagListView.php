<?php

final class PHUIHandleTagListView extends AphrontTagView {

  private $handles;
  private $annotations = array();
  private $limit;
  private $noDataString;

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setAnnotations(array $annotations) {
    $this->annotations = $annotations;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function setNoDataString($no_data) {
    $this->noDataString = $no_data;
    return $this;
  }

  protected function getTagName() {
    return 'ul';
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'phabricator-handle-tag-list',
    );
  }

  protected function getTagContent() {
    $handles = $this->handles;

    // If the list is empty, we may render a "No Projects" tag.
    if (!$handles) {
      if (strlen($this->noDataString)) {
        $no_data_tag = $this->newPlaceholderTag()
          ->setName($this->noDataString);
        return $this->newItem($no_data_tag);
      }
    }

    if ($this->limit) {
      $handles = array_slice($handles, 0, $this->limit);
    }

    $list = array();
    foreach ($handles as $handle) {
      $list[] = $this->newItem(
        array(
          $handle->renderTag(),
          idx($this->annotations, $handle->getPHID(), null),
        ));
    }

    if ($this->limit) {
      if ($this->limit < count($this->handles)) {
        $tip_text = implode(', ', mpull($this->handles, 'getName'));

        $more = $this->newPlaceholderTag()
          ->setName("\xE2\x80\xA6")
          ->addSigil('has-tooltip')
          ->setMetadata(
            array(
              'tip' => $tip_text,
              'size' => 200,
            ));

        $list[] = $this->newItem($more);
      }
    }

    return $list;
  }

  private function newItem($content) {
    return phutil_tag(
      'li',
      array(
        'class' => 'phabricator-handle-tag-list-item',
      ),
      $content);
  }

  private function newPlaceholderTag() {
    return id(new PHUITagView())
      ->setType(PHUITagView::TYPE_OBJECT)
      ->setShade(PHUITagView::COLOR_DISABLED);
  }

}
