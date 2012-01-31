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

class HeraldNewController extends HeraldController {

  private $contentType;

  public function getFilter() {
    return 'new';
  }

  public function willProcessRequest(array $data) {
    $this->contentType = idx($data, 'type');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $content_type_map = HeraldContentTypeConfig::getContentTypeMap();
    if (empty($content_type_map[$this->contentType])) {
      reset($content_type_map);
      $this->contentType = key($content_type_map);
    }

    $rule_type_map = HeraldRuleTypeConfig::getRuleTypeMap();

    // Reorder array to put "personal" first.
    $rule_type_map = array_select_keys(
      $rule_type_map,
      array(
        HeraldRuleTypeConfig::RULE_TYPE_PERSONAL,
      )) + $rule_type_map;

    $captions = array(
      HeraldRuleTypeConfig::RULE_TYPE_PERSONAL =>
        'Personal rules notify you about events. You own them, but they can '.
        'only affect you.',
      HeraldRuleTypeConfig::RULE_TYPE_GLOBAL =>
        'Global rules notify anyone about events. No one owns them, and '.
        'anyone can edit them. Usually, Global rules are used to notify '.
        'mailing lists.',
    );

    $radio = id(new AphrontFormRadioButtonControl())
      ->setLabel('Type')
      ->setName('rule_type')
      ->setValue(HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);

    foreach ($rule_type_map as $value => $name) {
      $radio->addButton(
        $value,
        $name,
        idx($captions, $value));
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction('/herald/rule/')
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('New rule for')
          ->setName('content_type')
          ->setValue($this->contentType)
          ->setOptions($content_type_map))
      ->appendChild($radio)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Create Rule')
          ->addCancelButton('/herald/view/'.$this->contentType.'/'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Create New Herald Rule');
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Create Herald Rule',
      ));
  }

}
