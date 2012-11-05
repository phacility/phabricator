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
      $package->setAuditingEnabled($request->getStr('auditing') === 'enabled');

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

      $path_refs = array();
      for ($ii = 0; $ii < count($paths); $ii++) {
        if (empty($paths[$ii]) || empty($repos[$ii])) {
          continue;
        }
        $path_refs[] = array(
          'repositoryPHID'  => $repos[$ii],
          'path'            => $paths[$ii],
        );
      }

      if (!strlen($package->getName())) {
        $e_name = 'Required';
        $errors[] = 'Package name is required.';
      } else {
        $e_name = null;
      }

      if (!$package->getPrimaryOwnerPHID()) {
        $e_primary = 'Required';
        $errors[] = 'Package must have a primary owner.';
      } else {
        $e_primary = null;
      }

      if (!$path_refs) {
        $errors[] = 'Package must include at least one path.';
      }

      if (!$errors) {
        $package->attachUnsavedOwners($owners);
        $package->attachUnsavedPaths($path_refs);
        $package->attachOldAuditingEnabled($old_auditing_enabled);
        $package->attachOldPrimaryOwnerPHID($old_primary);
        $package->attachActorPHID($user->getPHID());
        try {
          $package->save();
          return id(new AphrontRedirectResponse())
            ->setURI('/owners/package/'.$package->getID().'/');
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $e_name = 'Duplicate';
          $errors[] = 'Package name must be unique.';
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
        );
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Package Errors');
      $error_view->setErrors($errors);
    }

    $handles = $this->loadViewerHandles($owners);

    $primary = $package->getPrimaryOwnerPHID();
    if ($primary && isset($handles[$primary])) {
      $token_primary_owner = array(
        $primary => $handles[$primary]->getFullName(),
      );
    } else {
      $token_primary_owner = array();
    }

    $token_all_owners = array_select_keys($handles, $owners);
    $token_all_owners = mpull($token_all_owners, 'getFullName');

    if ($package->getID()) {
      $title = 'Edit Package';
      $side_nav_filter = 'edit/'.$this->id;
    } else {
      $title = 'New Package';
      $side_nav_filter = 'new';
    }
    $this->setSideNavFilter($side_nav_filter);

    $repos = id(new PhabricatorRepository())->loadAll();

    $default_paths = array();
    foreach ($repos as $repo) {
      $default_path = $repo->getDetail('default-owners-path');
      if ($default_path) {
        $default_paths[$repo->getPHID()] = $default_path;
      }
    }

    $repos = mpull($repos, 'getCallsign', 'getPHID');

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
          ->setLabel('Name')
          ->setName('name')
          ->setValue($package->getName())
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/usersorprojects/')
          ->setLabel('Primary Owner')
          ->setName('primary')
          ->setLimit(1)
          ->setValue($token_primary_owner)
          ->setError($e_primary))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/usersorprojects/')
          ->setLabel('Owners')
          ->setName('owners')
          ->setValue($token_all_owners))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('auditing')
          ->setLabel('Auditing')
          ->setCaption('With auditing enabled, all future commits that touch '.
                       'this package will be reviewed to make sure an owner '.
                       'of the package is involved and the commit message has '.
                       'a valid revision, reviewed by, and author.')
          ->setOptions(array(
            'disabled'  => 'Disabled',
            'enabled'   => 'Enabled',
          ))
          ->setValue(
            $package->getAuditingEnabled()
              ? 'enabled'
              : 'disabled'))
      ->appendChild(
        id(new AphrontFormInsetView())
          ->setTitle('Paths')
          ->addDivAttributes(array('id' => 'path-editor'))
          ->setRightButton(javelin_render_tag(
              'a',
              array(
                'href' => '#',
                'class' => 'button green',
                'sigil' => 'addpath',
                'mustcapture' => true,
              ),
              'Add New Path'))
          ->setDescription('Specify the files and directories which comprise '.
                           'this package.')
          ->setContent(javelin_render_tag(
              'table',
              array(
                'class' => 'owners-path-editor-table',
                'sigil' => 'paths',
              ),
              '')))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Description')
          ->setName('description')
          ->setValue($package->getDescription()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue('Save Package'));

    $panel = new AphrontPanelView();
    $panel->setHeader($title);
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $panel->appendChild($error_view);
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => $title,
      ));
  }

  protected function getExtraPackageViews() {
    if ($this->id) {
      $extra = array(array('name' => 'Edit',
                           'key'  => 'edit/'.$this->id));
    } else {
      $extra = array(array('name' => 'New',
                           'key'  => 'new'));
    }

    return $extra;
  }
}
