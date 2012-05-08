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

  private function isPullDaemonRunningOnThisMachine() {

    // This is sort of hacky, but should probably work.

    list($stdout) = execx('ps auxwww');
    return preg_match('/PhabricatorRepositoryPullLocalDaemon/', $stdout);
  }

  protected function renderDaemonNotice() {
    $daemon_running = $this->isPullDaemonRunningOnThisMachine();
    if ($daemon_running) {
      return null;
    }

    $documentation = phutil_render_tag(
      'a',
      array(
        'href' => PhabricatorEnv::getDoclink(
          'article/Diffusion_User_Guide.html'),
      ),
      'Diffusion User Guide');

    $view = new AphrontErrorView();
    $view->setSeverity(AphrontErrorView::SEVERITY_WARNING);
    $view->setTitle('Repository Daemon Not Running');
    $view->appendChild(
      "<p>The repository daemon is not running on this machine. Without this ".
      "daemon, Phabricator will not be able to import or update repositories. ".
      "For instructions on starting the daemon, see ".
      "<strong>{$documentation}</strong>.</p>");

    return $view;
  }

}
