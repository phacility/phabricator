<?php

final class PhutilCalendarRootNode
  extends PhutilCalendarContainerNode {

  const NODETYPE = 'root';

  public function getDocuments() {
    return $this->getChildrenOfType(PhutilCalendarDocumentNode::NODETYPE);
  }

}
