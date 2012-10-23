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

final class PhabricatorActionListExample extends PhabricatorUIExample {

  public function getName() {
    return 'Action List';
  }

  public function getDescription() {
    return 'Use <tt>PhabricatorActionListView</tt> to render object actions.';
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $notices = array();
    if ($request->isFormPost()) {
      $notices[] = 'You just submitted a valid form POST.';
    }

    if ($request->isJavelinWorkflow()) {
      $notices[] = 'You just submitted a Workflow request.';
    }

    if ($notices) {
      $notices = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setErrors($notices);
    } else {
      $notices = null;
    }

    if ($request->isJavelinWorkflow()) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($user);
      $dialog->setTitle('Request Information');
      $dialog->appendChild($notices);
      $dialog->addCancelButton($request->getRequestURI(), 'Close');
      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $view = new PhabricatorActionListView();
    $view->setUser($user);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setUser($user)
        ->setHref($request->getRequestURI())
        ->setName('Normal Action')
        ->setIcon('file'));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setUser($user)
        ->setHref($request->getRequestURI())
        ->setDisabled(true)
        ->setName('Disabled Action')
        ->setIcon('file'));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setUser($user)
        ->setHref($request->getRequestURI())
        ->setRenderAsForm(true)
        ->setName('Form Action')
        ->setIcon('file'));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setUser($user)
        ->setHref($request->getRequestURI())
        ->setRenderAsForm(true)
        ->setDisabled(true)
        ->setName('Disabled Form Action')
        ->setIcon('file'));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setUser($user)
        ->setHref($request->getRequestURI())
        ->setWorkflow(true)
        ->setName('Workflow Action')
        ->setIcon('file'));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setUser($user)
        ->setHref($request->getRequestURI())
        ->setRenderAsForm(true)
        ->setWorkflow(true)
        ->setName('Form + Workflow Action')
        ->setIcon('file'));

    foreach (PhabricatorActionView::getAvailableIcons() as $icon) {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setUser($user)
          ->setHref('#')
          ->setDisabled(true)
          ->setName('Icon "'.$icon.'"')
          ->setIcon($icon));
    }

    return array(
      $view,
      '<div style="clear: both;"></div>',
      $notices,
    );
  }
}
