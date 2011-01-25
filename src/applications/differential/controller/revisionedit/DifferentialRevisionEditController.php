<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class DifferentialRevisionEditController extends DifferentialController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    if ($this->id) {
      $revision = id(new DifferentialRevision())->load($this->id);
      if (!$revision) {
        return new Aphront404Response();
      }
    } else {
      $revision = new DifferentialRevision();
    }
/*
    $e_name = true;
    $errors = array();

    $request = $this->getRequest();
    if ($request->isFormPost()) {
      $category->setName($request->getStr('name'));
      $category->setSequence($request->getStr('sequence'));

      if (!strlen($category->getName())) {
        $errors[] = 'Category name is required.';
        $e_name = 'Required';
      }

      if (!$errors) {
        $category->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/directory/category/');
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }
*/
    $e_name = true;

    $form = new AphrontFormView();
    if ($revision->getID()) {
      $form->setAction('/differential/revision/edit/'.$category->getID().'/');
    } else {
      $form->setAction('/differential/revision/edit/');
    }

    $form
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($revision->getName())
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Summary')
          ->setName('summary')
          ->setValue($revision->getSummary()))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Test Plan')
          ->setName('testplan')
          ->setValue($revision->getTestPlan())
          ->setError($e_testplan))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Reviewers')
          ->setName('reviewers'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('CC')
          ->setName('cc'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Blame Revision')
          ->setName('blame')
          ->setValue($revision->getBlameRevision())
          ->setCaption('Revision which broke the stuff which this '.
                       'change fixes.'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Revert')
          ->setName('revert')
          ->setValue($revision->getRevertPlan())
          ->setCaption('Special steps required to safely revert this change.'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save'));

    $panel = new AphrontPanelView();
    if ($revision->getID()) {
      $panel->setHeader('Edit Differential Revision');
    } else {
      $panel->setHeader('Create New Differential Revision');
    }

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    $error_view = null;
    return $this->buildStandardPageResponse(
      array($error_view, $panel),
      array(
        'title' => 'Edit Differential Revision',
      ));
  }

}
