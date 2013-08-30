<?php

final class DiffusionCommitEditController extends DiffusionController {

  public function willProcessRequest(array $data) {
    $data['user'] = $this->getRequest()->getUser();
    $this->diffusionRequest = DiffusionRequest::newFromDictionary($data);
  }

  public function processRequest() {

    $request    = $this->getRequest();
    $user       = $request->getUser();
    $drequest   = $this->getDiffusionRequest();
    $callsign   = $drequest->getRepository()->getCallsign();
    $repository = $drequest->getRepository();
    $commit     = $drequest->loadCommit();
    $page_title = pht('Edit Diffusion Commit');

    if (!$commit) {
      return new Aphront404Response();
    }

    $commit_phid        = $commit->getPHID();
    $edge_type          = PhabricatorEdgeConfig::TYPE_COMMIT_HAS_PROJECT;
    $current_proj_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $commit_phid,
      $edge_type);
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

      id(new PhabricatorSearchIndexer())
        ->indexDocumentByPHID($commit->getPHID());

      return id(new AphrontRedirectResponse())
      ->setURI('/r'.$callsign.$commit->getCommitIdentifier());
    }

    $tokenizer_id = celerity_generate_unique_node_id();
    $form         = id(new AphrontFormView())
      ->setUser($user)
      ->setAction($request->getRequestURI()->getPath())
      ->appendChild(
        id(new AphrontFormTokenizerControl())
        ->setLabel(pht('Projects'))
        ->setName('projects')
        ->setValue($proj_t_values)
        ->setID($tokenizer_id)
        ->setCaption(
          javelin_tag(
            'a',
            array(
              'href'        => '/project/create/',
              'mustcapture' => true,
              'sigil'       => 'project-create',
            ),
            pht('Create New Project')))
        ->setDatasource('/typeahead/common/projects/'));;

    Javelin::initBehavior('project-create', array(
      'tokenizerID' => $tokenizer_id,
    ));

    $submit = id(new AphrontFormSubmitControl())
      ->setValue(pht('Save'))
      ->addCancelButton('/r'.$callsign.$commit->getCommitIdentifier());
    $form->appendChild($submit);

    $form_box = id(new PHUIFormBoxView())
      ->setHeaderText($page_title)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $form_box,
      ),
      array(
        'title' => $page_title,
        'device' => true,
      ));
  }

}
