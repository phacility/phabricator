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

final class PhabricatorErrorExample extends PhabricatorUIExample {

  public function getName() {
    return 'Errors';
  }

  public function getDescription() {
    return 'Use <tt>AphrontErrorView</tt> to render errors, warnings and '.
           'notices.';
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $sevs = array(
      AphrontErrorView::SEVERITY_ERROR    => 'Error',
      AphrontErrorView::SEVERITY_WARNING  => 'Warning',
      AphrontErrorView::SEVERITY_NOTICE   => 'Notice',
      AphrontErrorView::SEVERITY_NODATA   => 'No Data',
    );

    $views = array();
    foreach ($sevs as $sev => $title) {
      $view = new AphrontErrorView();
      $view->setSeverity($sev);
      $view->setTitle($title);
      $view->appendChild('Several issues were encountered.');
      $view->setErrors(
        array(
          'Overcooked.',
          'Too much salt.',
          'Full of sand.',
        ));
      $views[] = $view;
    }

    return $views;
  }
}
