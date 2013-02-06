<?php

/**
 * @group maniphest
 */
final class ManiphestDefaultTaskExtensions
  extends ManiphestTaskExtensions {

  public function getAuxiliaryFieldSpecifications() {
    $fields = PhabricatorEnv::getEnvConfig('maniphest.custom-fields');
    $specs = array();
    foreach ($fields as $aux => $info) {
      $spec = new ManiphestAuxiliaryFieldDefaultSpecification();
      $spec->setAuxiliaryKey($aux);
      $spec->setLabel(idx($info, 'label'));
      $spec->setCaption(idx($info, 'caption'));
      $spec->setGroup(idx($info, 'group'));
      $spec->setFieldType(idx($info, 'type'));
      $spec->setRequired(idx($info, 'required'));
      $spec->setReadonly(idx($info, 'readonly'));

      $spec->setCheckboxLabel(idx($info, 'checkbox-label'));
      $spec->setCheckboxValue(idx($info, 'checkbox-value', 1));

      if ($spec->getFieldType() ==
        ManiphestAuxiliaryFieldDefaultSpecification::TYPE_SELECT) {
        $spec->setSelectOptions(idx($info, 'options'));
      }

      $spec->setShouldCopyWhenCreatingSimilarTask(idx($info, 'copy'));
      $specs[] = $spec;
    }

    return $specs;
  }

  public function getGroupedAuxiliaryFieldSpecifications(array $aux_fields) {
    $aux_groups = array();
    $aux_groups["!global"] = array();
    foreach ($aux_fields as $aux_field) {
      
      // Determine if in global group.
      $group = $aux_field->getGroup();
      if ($group == null)
        $group = "!global";

      // Create the group if needed.
      if (!array_key_exists($group, $aux_groups))
        $aux_groups[$group] = array();

      // Add aux field to group.
      $value = $aux_groups[$group];
      $value[] = $aux_field;
      $aux_groups[$group] = $value;
    }
    return $aux_groups;
  }

  public function renderGroupedFields(array $aux_groups, $task, $user, $userdata, $skip_empty, $skip_desc, $group_callback, $field_callback) {
    foreach ($aux_groups as $group => $aux_groups_fields) {
      $display_group = false;
      foreach ($aux_groups_fields as $aux_field) {
        if ($aux_field->getFieldType() == ManiphestAuxiliaryFieldDefaultSpecification::TYPE_DESC && $skip_desc)
          continue;
        if ($skip_empty) {
          if ($task != null && $user != null) {
            $aux_key = $aux_field->getAuxiliaryKey();
            $aux_field->setValue($task->getAuxiliaryAttribute($aux_key));
            $value = $aux_field->renderForDetailView($user);
            if (strlen($value)) {
              $display_group = true;
            }
          }
        } else {
          $display_group = true;
        }
      }
      if ($group != "!global" && $display_group) {
        $group_callback($userdata, $group);
      }
      foreach ($aux_groups_fields as $aux_field) {
        if ($aux_field->getFieldType() == ManiphestAuxiliaryFieldDefaultSpecification::TYPE_DESC && $skip_desc)
          continue;
        $field_callback($userdata, $aux_field);
      }
    }
  }
}
