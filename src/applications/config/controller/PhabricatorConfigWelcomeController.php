<?php

final class PhabricatorConfigWelcomeController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('welcome/');

    $title = pht('Welcome');

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb(pht('Welcome'));

    $nav->setCrumbs($crumbs);
    $nav->appendChild($this->buildWelcomeScreen($request));

    return $this->newPage()
      ->setTitle($title)
      ->appendChild($nav);
  }

  public function buildWelcomeScreen(AphrontRequest $request) {
    $viewer = $request->getUser();
    $this->requireResource('config-welcome-css');

    $content = pht(
      "=== Install Phabricator ===\n\n".
      "You have successfully installed Phabricator. This screen will guide ".
      "you through configuration and orientation. ".
      "These steps are optional, and you can go through them in any order. ".
      "If you want to get back to this screen later on, you can find it in ".
      "the **Config** application under **Welcome Screen**.");

    $setup = array();

    $setup[] = $this->newItem(
      $request,
      'fa-check-square-o green',
      $content);

    $issues_resolved = !PhabricatorSetupCheck::getOpenSetupIssueKeys();

    $setup_href = PhabricatorEnv::getURI('/config/issue/');
    if ($issues_resolved) {
      $content = pht(
        "=== Resolve Setup Issues ===\n\n".
        "You've resolved (or ignored) all outstanding setup issues. ".
        "You can review issues in the **Config** application, under ".
        "**[[ %s | Setup Issues ]]**.",
        $setup_href);
        $icon = 'fa-check-square-o green';
    } else {
      $content = pht(
        "=== Resolve Setup Issues ===\n\n".
        "You have some unresolved setup issues to take care of. Click ".
        "the link in the yellow banner at the top of the screen to see ".
        "them, or find them in the **Config** application under ".
        "**[[ %s | Setup Issues ]]**. ".
        "Although most setup issues should be resolved, sometimes an issue ".
        "is not applicable to an install. ".
        "If you don't intend to fix a setup issue (or don't want to fix ".
        "it for now), you can use the \"Ignore\" action to mark it as ".
        "something you don't plan to deal with.",
        $setup_href);
        $icon = 'fa-warning red';
    }

    $setup[] = $this->newItem(
      $request,
      $icon,
      $content);

    $configs = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->execute();

    $auth_href = PhabricatorEnv::getURI('/auth/');
    $have_auth = (bool)$configs;
    if ($have_auth) {
      $content = pht(
        "=== Login and Registration ===\n\n".
        "You've configured at least one authentication provider, so users ".
        "can register or log in. ".
        "To configure more providers or adjust settings, use the ".
        "**[[ %s | Auth Application ]]**.",
        $auth_href);
        $icon = 'fa-check-square-o green';
    } else {
      $content = pht(
        "=== Login and Registration ===\n\n".
        "You haven't configured any authentication providers yet. ".
        "Authentication providers allow users to register accounts and ".
        "log in to Phabricator. You can configure Phabricator to accept ".
        "credentials like username and password, LDAP, or Google OAuth. ".
        "You can configure authentication using the ".
        "**[[ %s | Auth Application ]]**.",
        $auth_href);
        $icon = 'fa-warning red';
    }

    $setup[] = $this->newItem(
      $request,
      $icon,
      $content);

    $config_href = PhabricatorEnv::getURI('/config/');

    // Just load any config value at all; if one exists the install has figured
    // out how to configure things.
    $have_config = (bool)id(new PhabricatorConfigEntry())->loadAllWhere(
      '1 = 1 LIMIT 1');

    if ($have_config) {
      $content = pht(
        "=== Configure Phabricator Settings ===\n\n".
        "You've configured at least one setting from the web interface. ".
        "To configure more settings later, use the ".
        "**[[ %s | Config Application ]]**.",
        $config_href);
        $icon = 'fa-check-square-o green';
    } else {
      $content = pht(
        "=== Configure Phabricator Settings ===\n\n".
        'Many aspects of Phabricator are configurable. To explore and '.
        'adjust settings, use the **[[ %s | Config Application ]]**.',
        $config_href);
        $icon = 'fa-info-circle';
    }

    $setup[] = $this->newItem(
      $request,
      $icon,
      $content);

    $settings_href = PhabricatorEnv::getURI('/settings/');
    $prefs = $viewer->loadPreferences()->getPreferences();
    $have_settings = !empty($prefs);
    if ($have_settings) {
      $content = pht(
        "=== Adjust Account Settings ===\n\n".
        "You've adjusted at least one setting on your account. ".
        "To make more adjustments, visit the ".
        "**[[ %s | Settings Application ]]**.",
        $settings_href);
        $icon = 'fa-check-square-o green';
    } else {
      $content = pht(
        "=== Adjust Account Settings ===\n\n".
        'You can configure settings for your account by clicking the '.
        'wrench icon in the main menu bar, or visiting the '.
        '**[[ %s | Settings Application ]]** directly.',
        $settings_href);
        $icon = 'fa-info-circle';
    }

    $setup[] = $this->newItem(
      $request,
      $icon,
      $content);

    $dashboard_href = PhabricatorEnv::getURI('/dashboard/');
    $have_dashboard = (bool)PhabricatorDashboardInstall::getDashboard(
      $viewer,
      PhabricatorHomeApplication::DASHBOARD_DEFAULT,
      'PhabricatorHomeApplication');
    if ($have_dashboard) {
      $content = pht(
        "=== Customize Home Page ===\n\n".
        "You've installed a default dashboard to replace this welcome screen ".
        "on the home page. ".
        "You can still visit the welcome screen here at any time if you ".
        "have steps you want to complete later, or if you feel lonely. ".
        "If you've changed your mind about the dashboard you installed, ".
        "you can install a different default dashboard with the ".
        "**[[ %s | Dashboards Application ]]**.",
        $dashboard_href);
        $icon = 'fa-check-square-o green';
    } else {
      $content = pht(
        "=== Customize Home Page ===\n\n".
        "When you're done setting things up, you can create a custom ".
        "dashboard and install it. Your dashboard will replace this ".
        "welcome screen on the Phabricator home page. ".
        "Dashboards can show users the information that's most important to ".
        "your organization. You can configure them to display things like: ".
        "a custom welcome message, a feed of recent activity, or a list of ".
        "open tasks, waiting reviews, recent commits, and so on. ".
        "After you install a default dashboard, it will replace this page. ".
        "You can find this page later by visiting the **Config** ".
        "application, under **Welcome Page**. ".
        "To get started building a dashboard, use the ".
        "**[[ %s | Dashboards Application ]]**. ",
        $dashboard_href);
        $icon = 'fa-info-circle';
    }

    $setup[] = $this->newItem(
      $request,
      $icon,
      $content);

    $apps_href = PhabricatorEnv::getURI('/applications/');
    $content = pht(
      "=== Explore Applications ===\n\n".
      "Phabricator is a large suite of applications that work together to ".
      "help you develop software, manage tasks, and communicate. A few of ".
      "the most commonly used applications are pinned to the left navigation ".
      "bar by default.\n\n".
      "To explore all of the Phabricator applications, adjust settings, or ".
      "uninstall applications you don't plan to use, visit the ".
      "**[[ %s | Applications Application ]]**. You can also click the ".
      "**Applications** button in the left navigation menu, or search for an ".
      "application by name in the main menu bar. ",
      $apps_href);

    $explore = array();
    $explore[] = $this->newItem(
      $request,
      'fa-globe',
      $content);

    // TODO: Restore some sort of "Support" link here, but just nuke it for
    // now as we figure stuff out.

    $differential_uri = PhabricatorEnv::getURI('/differential/');
    $differential_create_uri = PhabricatorEnv::getURI(
      '/differential/diff/create/');
    $differential_all_uri = PhabricatorEnv::getURI('/differential/query/all/');

    $differential_user_guide = PhabricatorEnv::getDoclink(
      'Differential User Guide');
    $differential_vs_uri = PhabricatorEnv::getDoclink(
      'User Guide: Review vs Audit');

    $quick = array();
    $quick[] = $this->newItem(
      $request,
      'fa-gear',
      pht(
        "=== Quick Start: Code Review ===\n\n".
        "Review code with **[[ %s | Differential ]]**. ".
        "Engineers can use Differential to share, review, and approve ".
        "changes to source code. ".
        "To get started with code review:\n\n".
        "  - **[[ %s | Create a Revision ]]** //(Copy and paste a diff from ".
        "    the command line into the web UI to quickly get a feel for ".
        "    review.)//\n".
        "  - **[[ %s | View All Revisions ]]**\n\n".
        "For more information, see these articles in the documentation:\n\n".
        "  - **[[ %s | Differential User Guide ]]**, for a general overview ".
        "    of Differential.\n".
        "  - **[[ %s | User Guide: Review vs Audit ]]**, for a discussion ".
        "    of different code review workflows.",
        $differential_uri,
        $differential_create_uri,
        $differential_all_uri,
        $differential_user_guide,
        $differential_vs_uri));


    $maniphest_uri = PhabricatorEnv::getURI('/maniphest/');
    $maniphest_create_uri = PhabricatorEnv::getURI('/maniphest/task/edit/');
    $maniphest_all_uri = PhabricatorEnv::getURI('/maniphest/query/all/');
    $quick[] = $this->newItem(
      $request,
      'fa-anchor',
      pht(
        "=== Quick Start: Bugs and Tasks ===\n\n".
        "Track bugs and tasks in Phabricator with ".
        "**[[ %s | Maniphest ]]**. ".
        "Users in all roles can use Maniphest to manage current and ".
        "planned work and to track bugs and issues. ".
        "To get started with bugs and tasks:\n\n".
        "  - **[[ %s | Create a Task ]]**\n".
        "  - **[[ %s | View All Tasks ]]**\n",
        $maniphest_uri,
        $maniphest_create_uri,
        $maniphest_all_uri));


    $pholio_uri = PhabricatorEnv::getURI('/pholio/');
    $pholio_create_uri = PhabricatorEnv::getURI('/pholio/new/');
    $pholio_all_uri = PhabricatorEnv::getURI('/pholio/query/all/');

    $quick[] = $this->newItem(
      $request,
      'fa-camera-retro',
      pht(
        "=== Quick Start: Design Review ===\n\n".
        "Review proposed designs with **[[ %s | Pholio ]]**. ".
        "Designers can use Pholio to share images of what they're working on ".
        "and show off things they've made. ".
        "To get started with design review:\n\n".
        "  - **[[ %s | Create a Mock ]]**\n".
        "  - **[[ %s | View All Mocks ]]**",
        $pholio_uri,
        $pholio_create_uri,
        $pholio_all_uri));


    $diffusion_uri = PhabricatorEnv::getURI('/diffusion/');
    $diffusion_create_uri = PhabricatorEnv::getURI('/diffusion/create/');
    $diffusion_all_uri = PhabricatorEnv::getURI('/diffusion/query/all/');

    $diffusion_user_guide = PhabricatorEnv::getDoclink('Diffusion User Guide');
    $diffusion_setup_guide = PhabricatorEnv::getDoclink(
      'Diffusion User Guide: Repository Hosting');

    $quick[] = $this->newItem(
      $request,
      'fa-code',
      pht(
        "=== Quick Start: Repositories ===\n\n".
        "Manage and browse source code repositories with ".
        "**[[ %s | Diffusion ]]**. ".
        "Engineers can use Diffusion to browse and audit source code. ".
        "You can configure Phabricator to host repositories, or have it ".
        "track existing repositories hosted elsewhere (like GitHub, ".
        "Bitbucket, or an internal server). ".
        "To get started with repositories:\n\n".
        "  - **[[ %s | Create a New Repository ]]**\n".
        "  - **[[ %s | View All Repositories ]]**\n\n".
        "For more information, see these articles in the documentation:\n\n".
        "  - **[[ %s | Diffusion User Guide ]]**, for a general overview of ".
        "    Diffusion.\n".
        "  - **[[ %s | Diffusion User Guide: Repository Hosting ]]**, ".
        "    for instructions on configuring repository hosting.\n\n".
        "Phabricator supports Git, Mercurial and Subversion.",
        $diffusion_uri,
        $diffusion_create_uri,
        $diffusion_all_uri,
        $diffusion_user_guide,
        $diffusion_setup_guide));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Welcome to Phabricator'));

    $setup_header = new PHUIRemarkupView(
      $viewer, pht('=Setup and Configuration'));

    $explore_header = new PHUIRemarkupView(
      $viewer, pht('=Explore Phabricator'));

    $quick_header = new PHUIRemarkupView(
      $viewer, pht('=Quick Start Guide'));

    return id(new PHUIDocumentView())
      ->setHeader($header)
      ->setFluid(true)
      ->appendChild($setup_header)
      ->appendChild($setup)
      ->appendChild($explore_header)
      ->appendChild($explore)
      ->appendChild($quick_header)
      ->appendChild($quick);
  }

  private function newItem(AphrontRequest $request, $icon, $content) {
    $viewer = $request->getUser();

    $icon = id(new PHUIIconView())
      ->setIcon($icon.' fa-2x');

    $content = new PHUIRemarkupView($viewer, $content);

    $icon = phutil_tag(
      'div',
      array(
        'class' => 'config-welcome-icon',
      ),
      $icon);

    $content = phutil_tag(
      'div',
      array(
        'class' => 'config-welcome-content',
      ),
      $content);

    $view = phutil_tag(
      'div',
      array(
        'class' => 'config-welcome-box grouped',
      ),
      array(
        $icon,
        $content,
      ));

    return $view;
  }

}
