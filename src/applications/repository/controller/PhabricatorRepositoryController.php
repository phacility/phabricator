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
    $control = new PhabricatorDaemonControl();
    $daemons = $control->loadRunningDaemons();
    foreach ($daemons as $daemon) {
      if ($daemon->isRunning() &&
          $daemon->getName() == 'PhabricatorRepositoryPullLocalDaemon')
        return true;
    }
    return false;
  }

  protected function renderDaemonNotice() {
    $documentation = phutil_render_tag(
      'a',
      array(
        'href' => PhabricatorEnv::getDoclink(
          'article/Diffusion_User_Guide.html'),
      ),
      'Diffusion User Guide');

    $common =
      "Without this daemon, Phabricator will not be able to import or update ".
      "repositories. For instructions on starting the daemon, see ".
      "<strong>{$documentation}</strong>.";

    try {
      $daemon_running = $this->isPullDaemonRunning();
      if ($daemon_running) {
        return null;
      }
      $title = "Repository Daemon Not Running";
      $message =
        "<p>The repository daemon is not running on this machine. ".
        "{$common}</p>";
    } catch (CommandException $ex) {
      $title = "Unable To Verify Repository Daemon";
      $message =
        "<p>Unable to determine if the repository daemon is running on this ".
        "machine. {$common}</p>".
        "<p><strong>Exception:</strong> ".
          phutil_escape_html($ex->getMessage()).
        "</p>";
    }

    $view = new AphrontErrorView();
    $view->setSeverity(AphrontErrorView::SEVERITY_WARNING);
    $view->setTitle($title);
    $view->appendChild($message);

    return $view;
  }

}
