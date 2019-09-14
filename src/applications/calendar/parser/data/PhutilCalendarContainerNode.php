<?php

abstract class PhutilCalendarContainerNode
   extends PhutilCalendarNode {

  private $children = array();

  final public function getChildren() {
    return $this->children;
  }

  final public function getChildrenOfType($type) {
    $result = array();

    foreach ($this->getChildren() as $key => $child) {
      if ($child->getNodeType() != $type) {
        continue;
      }
      $result[$key] = $child;
    }

    return $result;
  }

  final public function appendChild(PhutilCalendarNode $node) {
    $this->children[] = $node;
    return $this;
  }

}
