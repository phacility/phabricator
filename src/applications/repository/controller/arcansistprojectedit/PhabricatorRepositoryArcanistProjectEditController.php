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

class PhabricatorRepositoryArcanistProjectEditController
  extends PhabricatorRepositoryController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $project = id(new PhabricatorRepositoryArcanistProject())->load($this->id);
    if (!$project) {
      return new Aphront404Response();
    }

    $repositories = id(new PhabricatorRepository())->loadAll();
    $repos = array(
      0 => 'None',
    );
    foreach ($repositories as $repository) {
      $callsign = $repository->getCallsign();
      $name = $repository->getname();
      $repos[$repository->getID()] = "r{$callsign} ({$name})";
    }

    if ($request->isFormPost()) {
      $repo_id = $request->getInt('repository', 0);
      if (isset($repos[$repo_id])) {
        $project->setRepositoryID($repo_id);
        $project->save();

        return id(new AphrontRedirectResponse())
          ->setURI('/repository/');
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Name')
          ->setValue($project->getName()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('PHID')
          ->setValue($project->getPHID()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Repository')
          ->setOptions($repos)
          ->setName('repository')
          ->setValue($project->getRepositoryID()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/repository/')
          ->setValue('Save'));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setHeader('Edit Arcanist Project');
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Edit Project',
      ));
  }

}
