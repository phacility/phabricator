<?php

final class PhabricatorOwnersEditController
  extends PhabricatorOwnersController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($this->id) {
      $package = id(new PhabricatorOwnersPackage())->load($this->id);
      if (!$package) {
        return new Aphront404Response();
      }
    } else {
      $package = new PhabricatorOwnersPackage();
      $package->setPrimaryOwnerPHID($user->getPHID());
    }

    $e_name = true;
    $e_primary = true;

    $errors = array();

    if ($request->isFormPost()) {
      $package->setName($request->getStr('name'));
      $package->setDescription($request->getStr('description'));
      $old_auditing_enabled = $package->getAuditingEnabled();
      $package->setAuditingEnabled(
        ($request->getStr('auditing') === 'enabled')
          ? 1
          : 0);

      $primary = $request->getArr('primary');
      $primary = reset($primary);
      $old_primary = $package->getPrimaryOwnerPHID();
      $package->setPrimaryOwnerPHID($primary);

      $owners = $request->getArr('owners');
      if ($primary) {
        array_unshift($owners, $primary);
      }
      $owners = array_unique($owners);

      $paths = $request->getArr('path');
      $repos = $request->getArr('repo');
      $excludes = $request->getArr('exclude');

      $path_refs = array();
      for ($ii = 0; $ii < count($paths); $ii++) {
        if (empty($paths[$ii]) || empty($repos[$ii])) {
          continue;
        }
        $path_refs[] = array(
          'repositoryPHID'  => $repos[$ii],
          'path'            => $paths[$ii],
          'excluded'        => $excludes[$ii],
        );
      }

      if (!strlen($package->getName())) {
        $e_name = pht('Required');
        $errors[] = pht('Package name is required.');
      } else {
        $e_name = null;
      }

      if (!$package->getPrimaryOwnerPHID()) {
        $e_primary = pht('Required');
        $errors[] = pht('Package must have a primary owner.');
      } else {
        $e_primary = null;
      }

      if (!$path_refs) {
        $errors[] = pht('Package must include at least one path.');
      }

      if (!$errors) {
        $package->attachUnsavedOwners($owners);
        $package->attachUnsavedPaths($path_refs);
        $package->attachOldAuditingEnabled($old_auditing_enabled);
        $package->attachOldPrimaryOwnerPHID($old_primary);
        try {
          id(new PhabricatorOwnersPackageEditor())
            ->setActor($user)
            ->setPackage($package)
            ->save();
          return id(new AphrontRedirectResponse())
            ->setURI('/owners/package/'.$package->getID().'/');
        } catch (AphrontDuplicateKeyQueryException $ex) {
          $e_name = pht('Duplicate');
          $errors[] = pht('Package name must be unique.');
        }
      }
    } else {
      $owners = $package->loadOwners();
      $owners = mpull($owners, 'getUserPHID');

      $paths = $package->loadPaths();
      $path_refs = array();
      foreach ($paths as $path) {
        $path_refs[] = array(
          'repositoryPHID' => $path->getRepositoryPHID(),
          'path' => $path->getPath(),
          'excluded' => $path->getExcluded(),
        );
      }
    }

    $primary = $package->getPrimaryOwnerPHID();
    if ($primary) {
      $value_primary_owner = array($primary);
    } else {
      $value_primary_owner = array();
    }

    if ($package->getID()) {
      $title = pht('Edit Package');
      $side_nav_filter = 'edit/'.$this->id;
    } else {
      $title = pht('New Package');
      $side_nav_filter = 'new';
    }
    $this->setSideNavFilter($side_nav_filter);

    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($user)
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

    $cancel_uri = $package->getID()
      ? '/owners/package/'.$package->getID().'/'
      : '/owners/';

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($package->getName())
          ->setError($e_name))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorProjectOrUserDatasource())
          ->setLabel(pht('Primary Owner'))
          ->setName('primary')
          ->setLimit(1)
          ->setValue($value_primary_owner)
          ->setError($e_primary))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorProjectOrUserDatasource())
          ->setLabel(pht('Owners'))
          ->setName('owners')
          ->setValue($owners))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('auditing')
          ->setLabel(pht('Auditing'))
          ->setCaption(
            pht('With auditing enabled, all future commits that touch '.
                'this package will be reviewed to make sure an owner '.
                'of the package is involved and the commit message has '.
                'a valid revision, reviewed by, and author.'))
          ->setOptions(array(
            'disabled'  => pht('Disabled'),
            'enabled'   => pht('Enabled'),
          ))
          ->setValue(
            $package->getAuditingEnabled()
              ? 'enabled'
              : 'disabled'))
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
            pht('Specify the files and directories which comprise '.
                'this package.'))
          ->setContent(javelin_tag(
              'table',
              array(
                'class' => 'owners-path-editor-table',
                'sigil' => 'paths',
              ),
              '')))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Description'))
          ->setName('description')
          ->setValue($package->getDescription()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue(pht('Save Package')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    if ($package->getID()) {
      $crumbs->addTextCrumb(pht('Edit %s', $package->getName()));
    } else {
      $crumbs->addTextCrumb(pht('New Package'));
    }

    $nav = $this->buildSideNavView();
    $nav->appendChild($crumbs);
    $nav->appendChild($form_box);

    return $this->buildApplicationPage(
      array(
        $nav,
      ),
      array(
        'title' => $title,
      ));
  }

  protected function getExtraPackageViews(AphrontSideNavFilterView $view) {
    if ($this->id) {
      $view->addFilter('edit/'.$this->id, pht('Edit'));
    } else {
      $view->addFilter('new', pht('New'));
    }
  }
}
