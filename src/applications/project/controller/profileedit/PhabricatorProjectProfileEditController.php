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

final class PhabricatorProjectProfileEditController
  extends PhabricatorProjectController {

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $project = id(new PhabricatorProject())->load($this->id);
    if (!$project) {
      return new Aphront404Response();
    }
    $profile = $project->loadProfile();

    if (empty($profile)) {
      $profile = new PhabricatorProjectProfile();
    }

    if ($project->getSubprojectPHIDs()) {
      $phids = $project->getSubprojectPHIDs();
      $handles = id(new PhabricatorObjectHandleData($phids))
        ->loadHandles();
      $subprojects = mpull($handles, 'getFullName', 'getPHID');
    } else {
      $subprojects = array();
    }

    $options = PhabricatorProjectStatus::getStatusMap();

    $affiliations = $project->loadAffiliations();
    $affiliations = mpull($affiliations, null, 'getUserPHID');

    $supported_formats = PhabricatorFile::getTransformableImageFormats();

    $e_name = true;
    $e_image = null;

    $errors = array();
    $state = null;
    if ($request->isFormPost()) {

      try {
        $xactions = array();
        $xaction = new PhabricatorProjectTransaction();
        $xaction->setTransactionType(
          PhabricatorProjectTransactionType::TYPE_NAME);
        $xaction->setNewValue($request->getStr('name'));
        $xactions[] = $xaction;

        $xaction = new PhabricatorProjectTransaction();
        $xaction->setTransactionType(
          PhabricatorProjectTransactionType::TYPE_STATUS);
        $xaction->setNewValue($request->getStr('status'));
        $xactions[] = $xaction;

        $editor = new PhabricatorProjectEditor($project);
        $editor->setUser($user);
        $editor->applyTransactions($xactions);
      } catch (PhabricatorProjectNameCollisionException $ex) {
        $e_name = 'Not Unique';
        $errors[] = $ex->getMessage();
      }

      $project->setSubprojectPHIDs($request->getArr('set_subprojects'));
      $profile->setBlurb($request->getStr('blurb'));

      if (!strlen($project->getName())) {
        $e_name = 'Required';
        $errors[] = 'Project name is required.';
      } else {
        $e_name = null;
      }

      if (!empty($_FILES['image'])) {
        $err = idx($_FILES['image'], 'error');
        if ($err != UPLOAD_ERR_NO_FILE) {
          $file = PhabricatorFile::newFromPHPUpload(
            $_FILES['image'],
            array(
              'authorPHID' => $user->getPHID(),
            ));
          $okay = $file->isTransformableImage();
          if ($okay) {
            $xformer = new PhabricatorImageTransformer();
            $xformed = $xformer->executeThumbTransform(
              $file,
              $x = 50,
              $y = 50);
            $profile->setProfileImagePHID($xformed->getPHID());
          } else {
            $e_image = 'Not Supported';
            $errors[] =
              'This server only supports these image formats: '.
              implode(', ', $supported_formats).'.';
          }
        }
      }

      $resources = $request->getStr('resources');
      $resources = json_decode($resources, true);
      if (!is_array($resources)) {
        throw new Exception(
          "Project resource information was not correctly encoded in the ".
          "request.");
      }

      $state = array();
      foreach ($resources as $resource) {
        $user_phid = $resource['phid'];
        if (!$user_phid) {
          continue;
        }
        if (isset($state[$user_phid])) {
          // TODO: We should deal with this better -- the user has entered
          // the same resource more than once.
        }
        $state[$user_phid] = array(
          'phid'    => $user_phid,
          'role'    => $resource['role'],
          'owner'   => $resource['owner'],
        );
      }

      $all_phids = array_merge(array_keys($state), array_keys($affiliations));
      $all_phids = array_unique($all_phids);

      $delete_affiliations = array();
      $save_affiliations = array();
      foreach ($all_phids as $phid) {
        $old = idx($affiliations, $phid);
        $new = idx($state, $phid);

        if ($old && !$new) {
          $delete_affiliations[] = $affiliations[$phid];
          continue;
        }

        if (!$old) {
          $affil = new PhabricatorProjectAffiliation();
          $affil->setUserPHID($phid);
        } else {
          $affil = $old;
        }

        $affil->setRole((string)$new['role']);
        $affil->setIsOwner((int)$new['owner']);

        $save_affiliations[] = $affil;
      }

      if (!$errors) {
        $project->save();
        $profile->setProjectPHID($project->getPHID());
        $profile->save();

        foreach ($delete_affiliations as $affil) {
          $affil->delete();
        }

        foreach ($save_affiliations as $save) {
          $save->setProjectPHID($project->getPHID());
          $save->save();
        }

        return id(new AphrontRedirectResponse())
          ->setURI('/project/view/'.$project->getID().'/');
      } else {
        $phids = array_keys($state);
        $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
        foreach ($state as $phid => $info) {
          $state[$phid]['name'] = $handles[$phid]->getFullName();
        }
      }
    } else {
      $phids = mpull($affiliations, 'getUserPHID');
      $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

      $state = array();
      foreach ($affiliations as $affil) {
        $user_phid = $affil->getUserPHID();
        $state[] = array(
          'phid'    => $user_phid,
          'name'    => $handles[$user_phid]->getFullName(),
          'role'    => $affil->getRole(),
          'owner'   => $affil->getIsOwner(),
        );
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Form Errors');
      $error_view->setErrors($errors);
    }

    $header_name = 'Edit Project';
    $title = 'Edit Project';
    $action = '/project/edit/'.$project->getID().'/';

    require_celerity_resource('project-edit-css');

    $form = new AphrontFormView();
    $form
      ->setID('project-edit-form')
      ->setUser($user)
      ->setAction($action)
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($project->getName())
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Project Status')
          ->setName('status')
          ->setOptions($options)
          ->setValue($project->getStatus()))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Blurb')
          ->setName('blurb')
          ->setValue($profile->getBlurb()))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/projects/')
          ->setLabel('Subprojects')
          ->setName('set_subprojects')
          ->setValue($subprojects))
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel('Change Image')
          ->setName('image')
          ->setError($e_image)
          ->setCaption('Supported formats: '.implode(', ', $supported_formats)))
      ->appendChild(
        id(new AphrontFormInsetView())
          ->setTitle('Resources')
          ->setRightButton(javelin_render_tag(
              'a',
              array(
                'href' => '#',
                'class' => 'button green',
                'sigil' => 'add-resource',
                'mustcapture' => true,
              ),
              'Add New Resource'))
          ->addHiddenInput('resources', 'resources')
          ->setContent(javelin_render_tag(
            'table',
            array(
              'sigil' => 'resources',
              'class' => 'project-resource-table',
            ),
            '')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/project/view/'.$project->getID().'/')
          ->setValue('Save'));

    $template = new AphrontTokenizerTemplateView();
    $template = $template->render();

    Javelin::initBehavior(
      'projects-resource-editor',
      array(
        'root'              => 'project-edit-form',
        'tokenizerTemplate' => $template,
        'tokenizerSource'   => '/typeahead/common/users/',
        'input'             => 'resources',
        'state'             => array_values($state),
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader($header_name);
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => $title,
      ));
  }
}
