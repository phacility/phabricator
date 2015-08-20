<?php

final class PhabricatorOwnersPathsController
  extends PhabricatorOwnersController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $package = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          // TODO: Support this capability.
          // PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->needPaths(true)
      ->executeOne();
    if (!$package) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $paths = $request->getArr('path');
      $repos = $request->getArr('repo');
      $excludes = $request->getArr('exclude');

      $path_refs = array();
      foreach ($paths as $key => $path) {
        if (!isset($repos[$key])) {
          throw new Exception(
            pht(
              'No repository PHID for path "%s"!',
              $key));
        }

        if (!isset($excludes[$key])) {
          throw new Exception(
            pht(
              'No exclusion value for path "%s"!',
              $key));
        }

        $path_refs[] = array(
          'repositoryPHID' => $repos[$key],
          'path' => $path,
          'excluded' => (int)$excludes[$key],
        );
      }

      $type_paths = PhabricatorOwnersPackageTransaction::TYPE_PATHS;

      $xactions = array();
      $xactions[] = id(new PhabricatorOwnersPackageTransaction())
        ->setTransactionType($type_paths)
        ->setNewValue($path_refs);

      $editor = id(new PhabricatorOwnersPackageTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      $editor->applyTransactions($package, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI('/owners/package/'.$package->getID().'/');
    } else {
      $paths = $package->getPaths();
      $path_refs = mpull($paths, 'getRef');
    }

    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->execute();

    $default_paths = array();
    foreach ($repos as $repo) {
      $default_path = $repo->getDetail('default-owners-path');
      if ($default_path) {
        $default_paths[$repo->getPHID()] = $default_path;
      }
    }

    $repos = mpull($repos, 'getCallsign', 'getPHID');
    asort($repos);

    $template = new AphrontTypeaheadTemplateView();
    $template = $template->render();

    Javelin::initBehavior(
      'owners-path-editor',
      array(
        'root'                => 'path-editor',
        'table'               => 'paths',
        'add_button'          => 'addpath',
        'repositories'        => $repos,
        'input_template'      => $template,
        'pathRefs'            => $path_refs,

        'completeURI'         => '/diffusion/services/path/complete/',
        'validateURI'         => '/diffusion/services/path/validate/',

        'repositoryDefaultPaths' => $default_paths,
      ));

    require_celerity_resource('owners-path-editor-css');

    $cancel_uri = '/owners/package/'.$package->getID().'/';

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new PHUIFormInsetView())
          ->setTitle(pht('Paths'))
          ->addDivAttributes(array('id' => 'path-editor'))
          ->setRightButton(javelin_tag(
              'a',
              array(
                'href' => '#',
                'class' => 'button green',
                'sigil' => 'addpath',
                'mustcapture' => true,
              ),
              pht('Add New Path')))
          ->setDescription(
            pht(
              'Specify the files and directories which comprise '.
              'this package.'))
          ->setContent(javelin_tag(
              'table',
              array(
                'class' => 'owners-path-editor-table',
                'sigil' => 'paths',
              ),
              '')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue(pht('Save Paths')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Edit Paths'))
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      $package->getName(),
      $this->getApplicationURI('package/'.$package->getID().'/'));
    $crumbs->addTextCrumb(pht('Edit Paths'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => array(
          $package->getName(),
          pht('Edit Paths'),
        ),
      ));
  }

}
