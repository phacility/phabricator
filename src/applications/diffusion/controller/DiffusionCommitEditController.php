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

final class DiffusionCommitEditController extends DiffusionController {

  public function willProcessRequest(array $data) {
    $this->diffusionRequest = DiffusionRequest::newFromDictionary($data);
  }

  public function processRequest() {

    $request    = $this->getRequest();
    $user       = $request->getUser();
    $drequest   = $this->getDiffusionRequest();
    $callsign   = $drequest->getRepository()->getCallsign();
    $repository = $drequest->getRepository();
    $commit     = $drequest->loadCommit();
    $page_title = 'Edit Diffusion Commit';

    if (!$commit) {
      return new Aphront404Response();
    }

    $commit_phid        = $commit->getPHID();
    $edge_type          = PhabricatorEdgeConfig::TYPE_COMMIT_HAS_PROJECT;
    $current_proj_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $commit_phid,
      $edge_type
    );
    $handles = $this->loadViewerHandles($current_proj_phids);
    $proj_t_values = mpull($handles, 'getFullName', 'getPHID');

    if ($request->isFormPost()) {
      $proj_phids = $request->getArr('projects');
      $new_proj_phids = array_values($proj_phids);
      $rem_proj_phids = array_diff($current_proj_phids,
                                   $new_proj_phids);
      $editor         = id(new PhabricatorEdgeEditor());
      $editor->setActor($user);
      foreach ($rem_proj_phids as $phid) {
        $editor->removeEdge($commit_phid, $edge_type, $phid);
      }
      foreach ($new_proj_phids as $phid) {
        $editor->addEdge($commit_phid, $edge_type, $phid);
      }
      $editor->save();

      PhabricatorSearchCommitIndexer::indexCommit($commit);

      return id(new AphrontRedirectResponse())
      ->setURI('/r'.$callsign.$commit->getCommitIdentifier());
    }

    $tokenizer_id = celerity_generate_unique_node_id();
    $form         = id(new AphrontFormView())
      ->setUser($user)
      ->setAction($request->getRequestURI()->getPath())
      ->appendChild(
        id(new AphrontFormTokenizerControl())
        ->setLabel('Projects')
        ->setName('projects')
        ->setValue($proj_t_values)
        ->setID($tokenizer_id)
        ->setCaption(
          javelin_render_tag(
            'a',
            array(
              'href'        => '/project/create/',
              'mustcapture' => true,
              'sigil'       => 'project-create',
            ),
            'Create New Project'))
            ->setDatasource('/typeahead/common/projects/'));;

    Javelin::initBehavior('project-create', array(
      'tokenizerID' => $tokenizer_id,
    ));

    $submit = id(new AphrontFormSubmitControl())
      ->setValue('Save')
      ->addCancelButton('/r'.$callsign.$commit->getCommitIdentifier());
    $form->appendChild($submit);

    $panel = id(new AphrontPanelView())
      ->setHeader('Edit Diffusion Commit')
      ->appendChild($form)
      ->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => $page_title,
      ));
  }

}
