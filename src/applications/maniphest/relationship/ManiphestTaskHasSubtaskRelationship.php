<?php

final class ManiphestTaskHasSubtaskRelationship
  extends ManiphestTaskRelationship {

  const RELATIONSHIPKEY = 'task.has-subtask';

  public function getEdgeConstant() {
    return ManiphestTaskDependsOnTaskEdgeType::EDGECONST;
  }

  protected function getActionName() {
    return pht('Edit Subtasks');
  }

  protected function getActionIcon() {
    return 'fa-chevron-circle-down';
  }

  public function canRelateObjects($src, $dst) {
    return ($dst instanceof ManiphestTask);
  }

  public function shouldAppearInActionMenu() {
    return false;
  }

  public function getDialogTitleText() {
    return pht('Edit Subtasks');
  }

  public function getDialogHeaderText() {
    return pht('Current Subtasks');
  }

  public function getDialogButtonText() {
    return pht('Save Subtasks');
  }

  protected function newRelationshipSource() {
    return id(new ManiphestTaskRelationshipSource())
      ->setSelectedFilter('open');
  }

}
