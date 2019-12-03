<?php

final class PhutilCalendarDocumentNode
  extends PhutilCalendarContainerNode {

  const NODETYPE = 'document';

  public function getEvents() {
    return $this->getChildrenOfType(PhutilCalendarEventNode::NODETYPE);
  }

}
