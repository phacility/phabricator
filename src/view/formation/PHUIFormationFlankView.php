<?php

final class PHUIFormationFlankView
  extends PHUIFormationColumnDynamicView {

  private $isFixed;

  private $head;
  private $body;
  private $tail;

  private $headID;
  private $bodyID;
  private $tailID;

  private $headerText;

  public function setIsFixed($fixed) {
    $this->isFixed = $fixed;
    return $this;
  }

  public function getIsFixed() {
    return $this->isFixed;
  }

  public function setHead($head) {
    $this->head = $head;
    return $this;
  }

  public function setBody($body) {
    $this->body = $body;
    return $this;
  }

  public function setTail($tail) {
    $this->tail = $tail;
    return $this;
  }

  public function getHeadID() {
    if (!$this->headID) {
      $this->headID = celerity_generate_unique_node_id();
    }
    return $this->headID;
  }

  public function getBodyID() {
    if (!$this->bodyID) {
      $this->bodyID = celerity_generate_unique_node_id();
    }
    return $this->bodyID;
  }

  public function getTailID() {
    if (!$this->tailID) {
      $this->tailID = celerity_generate_unique_node_id();
    }
    return $this->tailID;
  }

  public function setHeaderText($header_text) {
    $this->headerText = $header_text;
    return $this;
  }

  public function getHeaderText() {
    return $this->headerText;
  }

  public function newClientProperties() {
    return array(
      'type' => 'flank',
      'nodeID' => $this->getID(),
      'isFixed' => (bool)$this->getIsFixed(),
      'headID' => $this->getHeadID(),
      'bodyID' => $this->getBodyID(),
      'tailID' => $this->getTailID(),
    );
  }

  public function render() {
    require_celerity_resource('phui-formation-view-css');

    $width = $this->getWidth();

    $style = array();
    $style[] = sprintf('width: %dpx;', $width);

    $classes = array();
    $classes[] = 'phui-flank-view';

    if ($this->getIsFixed()) {
      $classes[] = 'phui-flank-view-fixed';
    }

    $head_id = $this->getHeadID();
    $body_id = $this->getBodyID();
    $tail_id = $this->getTailID();

    $header = phutil_tag(
      'div',
      array(
        'class' => 'phui-flank-header',
      ),
      array(
        phutil_tag(
          'div',
          array(
            'class' => 'phui-flank-header-text',
          ),
          $this->getHeaderText()),
        $this->newHideButton(),
      ));

    $content = phutil_tag(
      'div',
      array(
        'id' => $this->getID(),
        'class' => implode(' ', $classes),
        'style' => implode(' ', $style),
      ),
      array(
        phutil_tag(
          'div',
          array(
            'id' => $head_id,
            'class' => 'phui-flank-view-head',
          ),
          array(
            $header,
            $this->head,
          )),
        phutil_tag(
          'div',
          array(
            'id' => $body_id,
            'class' => 'phui-flank-view-body',
          ),
          $this->body),
        phutil_tag(
          'div',
          array(
            'id' => $tail_id,
            'class' => 'phui-flank-view-tail',
          ),
          $this->tail),
      ));

    return $content;
  }

  private function newHideButton() {
    $item = $this->getColumnItem();
    $is_right = $item->getIsRightAligned();

    $hide_classes = array();
    $hide_classes[] = 'phui-flank-header-hide';

    if ($is_right) {
      $hide_icon = id(new PHUIIconView())
        ->setIcon('fa-chevron-right grey');
      $hide_classes[] = 'phui-flank-header-hide-right';
    } else {
      $hide_icon = id(new PHUIIconView())
        ->setIcon('fa-chevron-left grey');
      $hide_classes[] = 'phui-flank-header-hide-left';
    }

    return javelin_tag(
      'div',
      array(
        'sigil' => 'phui-flank-header-hide',
        'class' => implode(' ', $hide_classes),
      ),
      $hide_icon);
  }

}
