<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
