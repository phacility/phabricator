<?php

final class DiffusionCommitEditController extends DiffusionController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $commit = $drequest->loadCommit();

    if (!$commit) {
      return new Aphront404Response();
    }

    $data = $commit->loadCommitData();
    $page_title = pht('Edit Diffusion Commit');

    $commit_phid = $commit->getPHID();
    $edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
    $current_proj_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $commit_phid,
      $edge_type);

    if ($request->isFormPost()) {
      $xactions = array();
      $proj_phids = $request->getArr('projects');
      $xactions[] = id(new PhabricatorAuditTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $edge_type)
        ->setNewValue(array('=' => array_fuse($proj_phids)));

      $editor = id(new PhabricatorAuditEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request);

      $editor->applyTransactions($commit, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($commit->getURI());
    }

    $tokenizer_id = celerity_generate_unique_node_id();
    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->setAction($request->getRequestURI()->getPath())
      ->appendControl(
        id(new AphrontFormTokenizerControl())
        ->setLabel(pht('Projects'))
        ->setName('projects')
        ->setValue($current_proj_phids)
        ->setID($tokenizer_id)
        ->setDatasource(new PhabricatorProjectDatasource()));

    $reason = $data->getCommitDetail('autocloseReason', false);
    $reason = PhabricatorRepository::BECAUSE_AUTOCLOSE_FORCED;
    if ($reason !== false) {
      switch ($reason) {
        case PhabricatorRepository::BECAUSE_REPOSITORY_IMPORTING:
          $desc = pht('No, Repository Importing');
          break;
        case PhabricatorRepository::BECAUSE_AUTOCLOSE_DISABLED:
          $desc = pht('No, Autoclose Disabled');
          break;
        case PhabricatorRepository::BECAUSE_NOT_ON_AUTOCLOSE_BRANCH:
          $desc = pht('No, Not On Autoclose Branch');
          break;
        case PhabricatorRepository::BECAUSE_AUTOCLOSE_FORCED:
          $desc = pht('Yes, Forced Via bin/repository CLI Tool.');
          break;
        case null:
          $desc = pht('Yes');
          break;
        default:
          $desc = pht('Unknown');
          break;
      }

      $doc_href = PhabricatorEnv::getDoclink('Diffusion User Guide: Autoclose');
      $doc_link = phutil_tag(
        'a',
        array(
          'href' => $doc_href,
          'target' => '_blank',
        ),
        pht('Learn More'));

      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Autoclose?'))
          ->setValue(array($desc, " \xC2\xB7 ", $doc_link)));
    }

    $form->appendControl(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save'))
        ->addCancelButton($commit->getURI()));

    $crumbs = $this->buildCrumbs(
      array(
        'commit' => true,
      ));
    $crumbs->addTextCrumb(pht('Edit'));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($page_title)
      ->setForm($form);

    return $this->newPage()
      ->setTitle($page_title)
      ->setCrumbs($crumbs)
      ->appendChild($form_box);
  }

}
