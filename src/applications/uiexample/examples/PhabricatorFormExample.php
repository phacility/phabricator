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

final class PhabricatorFormExample extends PhabricatorUIExample {

  public function getName() {
    return 'Form';
  }

  public function getDescription() {
    return 'Use <tt>AphrontFormView</tt> to render forms.';
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $start_time = id(new AphrontFormDateControl())
      ->setUser($user)
      ->setName('start')
      ->setLabel('Start')
      ->setInitialTime(AphrontFormDateControl::TIME_START_OF_BUSINESS);
    $start_value = $start_time->readValueFromRequest($request);

    $end_time = id(new AphrontFormDateControl())
      ->setUser($user)
      ->setName('end')
      ->setLabel('End')
      ->setInitialTime(AphrontFormDateControl::TIME_END_OF_BUSINESS);
    $end_value = $end_time->readValueFromRequest($request);

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setFlexible(true)
      ->appendChild($start_time)
      ->appendChild($end_time)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Submit'));

    return $form;
  }
}
