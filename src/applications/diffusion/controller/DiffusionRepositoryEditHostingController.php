<?php

final class DiffusionRepositoryEditHostingController
  extends DiffusionRepositoryEditController {

  private $serve;

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContextForEdit();
    if ($response) {
      return $response;
    }

    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $this->serve = $request->getURIData('serve');

    if (!$this->serve) {
      return $this->handleHosting($repository);
    } else {
      return $this->handleProtocols($repository);
    }
  }

  public function handleHosting(PhabricatorRepository $repository) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $v_hosting = $repository->isHosted();

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');
    $next_uri = $this->getRepositoryControllerURI($repository, 'edit/serve/');

    if ($request->isFormPost()) {
      $v_hosting = $request->getBool('hosting');

      $xactions = array();
      $template = id(new PhabricatorRepositoryTransaction());

      $type_hosting = PhabricatorRepositoryTransaction::TYPE_HOSTING;

      $xactions[] = id(clone $template)
        ->setTransactionType($type_hosting)
        ->setNewValue($v_hosting);

      id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($user)
        ->applyTransactions($repository, $xactions);

      return id(new AphrontRedirectResponse())->setURI($next_uri);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Hosting'));

    $title = pht('Edit Hosting (%s)', $repository->getName());
    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-pencil');

    $hosted_control = id(new AphrontFormRadioButtonControl())
        ->setName('hosting')
        ->setLabel(pht('Hosting'))
        ->addButton(
          true,
          pht('Host Repository on Phabricator'),
          pht(
            'Phabricator will host this repository. Users will be able to '.
            'push commits to Phabricator. Phabricator will not pull '.
            'changes from elsewhere.'))
        ->addButton(
          false,
          pht('Host Repository Elsewhere'),
          pht(
            'Phabricator will pull updates to this repository from a master '.
            'repository elsewhere (for example, on GitHub or Bitbucket). '.
            'Users will not be able to push commits to this repository.'))
        ->setValue($v_hosting);

    $doc_href = PhabricatorEnv::getDoclink(
      'Diffusion User Guide: Repository Hosting');

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendRemarkupInstructions(
        pht(
          'Phabricator can host repositories, or it can track repositories '.
          'hosted elsewhere (like on GitHub or Bitbucket). For information '.
          'on configuring hosting, see [[ %s | Diffusion User Guide: '.
          'Repository Hosting]]',
          $doc_href))
      ->appendChild($hosted_control)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save and Continue'))
          ->addCancelButton($edit_uri));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Hosting'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $form_box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  public function handleProtocols(PhabricatorRepository $repository) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $type = $repository->getVersionControlSystem();
    $is_svn = ($type == PhabricatorRepositoryType::REPOSITORY_TYPE_SVN);

    $v_http_mode = $repository->getDetail(
      'serve-over-http',
      PhabricatorRepository::SERVE_OFF);
    $v_ssh_mode = $repository->getDetail(
      'serve-over-ssh',
      PhabricatorRepository::SERVE_OFF);

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');
    $prev_uri = $this->getRepositoryControllerURI($repository, 'edit/hosting/');

    if ($request->isFormPost()) {
      $v_http_mode = $request->getStr('http');
      $v_ssh_mode = $request->getStr('ssh');

      $xactions = array();
      $template = id(new PhabricatorRepositoryTransaction());

      $type_http = PhabricatorRepositoryTransaction::TYPE_PROTOCOL_HTTP;
      $type_ssh = PhabricatorRepositoryTransaction::TYPE_PROTOCOL_SSH;

      if (!$is_svn) {
        $xactions[] = id(clone $template)
          ->setTransactionType($type_http)
          ->setNewValue($v_http_mode);
      }

      $xactions[] = id(clone $template)
        ->setTransactionType($type_ssh)
        ->setNewValue($v_ssh_mode);

      id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($user)
        ->applyTransactions($repository, $xactions);

      return id(new AphrontRedirectResponse())->setURI($edit_uri);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Protocols'));

    $title = pht('Edit Protocols (%s)', $repository->getName());
    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-pencil');

    $rw_message = pht(
      'Phabricator will serve a read-write copy of this repository.');

    if (!$repository->isHosted()) {
      $rw_message = array(
        $rw_message,
        phutil_tag('br'),
        phutil_tag('br'),
        pht(
          '%s: This repository is hosted elsewhere, so Phabricator can not '.
          'perform writes. This mode will act like "Read Only" for '.
          'repositories hosted elsewhere.',
          phutil_tag('strong', array(), pht('WARNING'))),
      );
    }

    $ssh_control =
      id(new AphrontFormRadioButtonControl())
        ->setName('ssh')
        ->setLabel(pht('SSH'))
        ->setValue($v_ssh_mode)
        ->addButton(
          PhabricatorRepository::SERVE_OFF,
          PhabricatorRepository::getProtocolAvailabilityName(
            PhabricatorRepository::SERVE_OFF),
          pht('Phabricator will not serve this repository over SSH.'))
        ->addButton(
          PhabricatorRepository::SERVE_READONLY,
          PhabricatorRepository::getProtocolAvailabilityName(
            PhabricatorRepository::SERVE_READONLY),
          pht(
            'Phabricator will serve a read-only copy of this repository '.
            'over SSH.'))
        ->addButton(
          PhabricatorRepository::SERVE_READWRITE,
          PhabricatorRepository::getProtocolAvailabilityName(
            PhabricatorRepository::SERVE_READWRITE),
          $rw_message);

    $http_control =
      id(new AphrontFormRadioButtonControl())
        ->setName('http')
        ->setLabel(pht('HTTP'))
        ->setValue($v_http_mode)
        ->addButton(
          PhabricatorRepository::SERVE_OFF,
          PhabricatorRepository::getProtocolAvailabilityName(
            PhabricatorRepository::SERVE_OFF),
          pht('Phabricator will not serve this repository over HTTP.'))
        ->addButton(
          PhabricatorRepository::SERVE_READONLY,
          PhabricatorRepository::getProtocolAvailabilityName(
            PhabricatorRepository::SERVE_READONLY),
          pht(
            'Phabricator will serve a read-only copy of this repository '.
            'over HTTP.'))
        ->addButton(
          PhabricatorRepository::SERVE_READWRITE,
          PhabricatorRepository::getProtocolAvailabilityName(
            PhabricatorRepository::SERVE_READWRITE),
          $rw_message);

    if ($is_svn) {
      $http_control = id(new AphrontFormMarkupControl())
        ->setLabel(pht('HTTP'))
        ->setValue(
          phutil_tag(
            'em',
            array(),
            pht(
              'Phabricator does not currently support HTTP access to '.
              'Subversion repositories.')));
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendRemarkupInstructions(
        pht(
          'Phabricator can serve repositories over various protocols. You can '.
          'configure server protocols here.'))
      ->appendChild($ssh_control);

    if (!PhabricatorEnv::getEnvConfig('diffusion.allow-http-auth')) {
      $form->appendRemarkupInstructions(
        pht(
          'NOTE: The configuration setting [[ %s | %s ]] is currently '.
          'disabled. You must enable it to activate authenticated access '.
          'to repositories over HTTP.',
          '/config/edit/diffusion.allow-http-auth/',
          'diffusion.allow-http-auth'));
    }

    $form
      ->appendChild($http_control)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Changes'))
          ->addCancelButton($prev_uri, pht('Back')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Protocols'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $form_box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
