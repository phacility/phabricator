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
      $spec->setFieldType(idx($info, 'type'));
      $spec->setRequired(idx($info, 'required'));

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

}
