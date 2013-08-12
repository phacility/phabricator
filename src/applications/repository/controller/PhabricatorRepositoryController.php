<?php

abstract class PhabricatorRepositoryController extends PhabricatorController {

  public function shouldRequireAdmin() {
    // Most of these controllers are admin-only.
    return true;
  }

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Repositories');
    $page->setBaseURI('/repository/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("rX");
    $page->appendChild($view);


    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  private function isPullDaemonRunning() {
    $daemons = id(new PhabricatorDaemonLogQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withStatus(PhabricatorDaemonLogQuery::STATUS_ALIVE)
      ->withDaemonClasses(array('PhabricatorRepositoryPullLocalDaemon'))
      ->setLimit(1)
      ->execute();

    return (bool)$daemons;
  }

  protected function renderDaemonNotice() {
    $documentation = phutil_tag(
      'a',
      array(
        'href' => PhabricatorEnv::getDoclink(
          'article/Diffusion_User_Guide.html'),
      ),
      'Diffusion User Guide');

    $common = hsprintf(
      "Without this daemon, Phabricator will not be able to import or update ".
      "repositories. For instructions on starting the daemon, see %s.",
      phutil_tag('strong', array(), $documentation));

    $daemon_running = $this->isPullDaemonRunning();
    if ($daemon_running) {
      return null;
    }
    $title = "Repository Daemon Not Running";
    $message = hsprintf(
      "<p>The repository daemon is not running. %s</p>",
      $common);

    $view = new AphrontErrorView();
    $view->setSeverity(AphrontErrorView::SEVERITY_WARNING);
    $view->setTitle($title);
    $view->appendChild($message);

    return $view;
  }

}
