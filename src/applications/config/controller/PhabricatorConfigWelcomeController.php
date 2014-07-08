<?php

final class PhabricatorConfigWelcomeController
  extends PhabricatorConfigController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('welcome/');

    $title = pht('Welcome');

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb(pht('Welcome'));

    $nav->setCrumbs($crumbs);
    $nav->appendChild($this->buildWelcomeScreen($request));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }

  public function buildWelcomeScreen(AphrontRequest $request) {
    $viewer = $request->getUser();
    $this->requireResource('config-welcome-css');

    $content = pht(
      "**Welcome to Phabricator!**\n\n".
      "You have successfully installed Phabricator. This screen will guide ".
      "you through configuration and orientation.\n\n".
      "These steps are optional, and you can go through them in any order.\n\n".
      "If you want to get back to this screen later on, you can find it in ".
      "the **Config** application under **Welcome Screen**.");

    $setup = array();

    $setup[] = $this->newItem(
      $request,
      pht('Install Phabricator'),
      true,
      $content);

    $issues_resolved = !PhabricatorSetupCheck::getOpenSetupIssueCount();

    $setup_href = PhabricatorEnv::getURI('/config/issue/');
    if ($issues_resolved) {
      $content = pht(
        "You've resolved (or ignored) all outstanding setup issues.\n\n".
        "You can review issues in the **Config** application, under ".
        "**[[ %s | Setup Issues ]]**.",
        $setup_href);
    } else {
      $content = pht(
        "You have some unresolved setup issues to take care of. Click ".
        "the link in the yellow banner at the top of the screen to see ".
        "them, or find them in the **Config** application under ".
        "**[[ %s | Setup Issues ]]**.\n\n".
        "Although most setup issues should be resolved, sometimes an issue ".
        "is not applicable to an install.\n\n".
        "If you don't intend to fix a setup issue (or don't want to fix ".
        "it for now), you can use the \"Ignore\" action to mark it as ".
        "something you don't plan to deal with.",
        $setup_href);
    }

    $setup[] = $this->newItem(
      $request,
      pht('Resolve Setup Issues'),
      $issues_resolved,
      $content);

    $configs = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->execute();

    $auth_href = PhabricatorEnv::getURI('/auth/');
    $have_auth = (bool)$configs;
    if ($have_auth) {
      $content = pht(
        "You've configured at least one authentication provider, so users ".
        "can register or log in.\n\n".
        "To configure more providers or adjust settings, use the ".
        "**[[ %s | Auth Application ]]**.",
        $auth_href);
    } else {
      $content = pht(
        "You haven't configured any authentication providers yet.\n\n".
        "Authentication providers allow users to register accounts and ".
        "log in to Phabricator. You can configure Phabricator to accept ".
        "credentials like username and password, LDAP, or Google OAuth.\n\n".
        "You can configure authentication using the ".
        "**[[ %s | Auth Application ]]**.",
        $auth_href);
    }

    $setup[] = $this->newItem(
      $request,
      pht('Login and Registration'),
      $have_auth,
      $content);

    $config_href = PhabricatorEnv::getURI('/config/');

    // Just load any config value at all; if one exists the install has figured
    // out how to configure things.
    $have_config = (bool)id(new PhabricatorConfigEntry())->loadAllWhere(
      '1 = 1 LIMIT 1');

    if ($have_config) {
      $content = pht(
        "You've configured at least one setting from the web interface.\n\n".
        "To configure more settings later, use the ".
        "**[[ %s | Config Application ]]**.",
        $config_href);
    } else {
      $content = pht(
        'Many aspects of Phabricator are configurable. To explore and '.
        'adjust settings, use the **[[ %s | Config Application ]]**.',
        $config_href);
    }

    $setup[] = $this->newItem(
      $request,
      pht('Configure Phabricator Settings'),
      $have_config,
      $content);

    $settings_href = PhabricatorEnv::getURI('/settings/');
    $prefs = $viewer->loadPreferences()->getPreferences();
    $have_settings = !empty($prefs);
    if ($have_settings) {
      $content = pht(
        "You've adjusted at least one setting on your account.\n\n".
        "To make more adjustments, visit the ".
        "**[[ %s | Settings Application ]]**.",
        $settings_href);
    } else {
      $content = pht(
        'You can configure settings for your account by clicking the '.
        'wrench icon in the main menu bar, or visiting the '.
        '**[[ %s | Settings Application ]]** directly.',
        $settings_href);
    }

    $setup[] = $this->newItem(
      $request,
      pht('Adjust Account Settings'),
      $have_settings,
      $content);

    $dashboard_href = PhabricatorEnv::getURI('/dashboard/');
    $have_dashboard = (bool)PhabricatorDashboardInstall::getDashboard(
      $viewer,
      PhabricatorApplicationHome::DASHBOARD_DEFAULT,
      'PhabricatorApplicationHome');
    if ($have_dashboard) {
      $content = pht(
        "You've installed a default dashboard to replace this welcome screen ".
        "on the home page.\n\n".
        "You can still visit the welcome screen here at any time if you ".
        "have steps you want to complete later, or if you feel lonely.\n\n".
        "If you've changed your mind about the dashboard you installed, ".
        "you can install a different default dashboard with the ".
        "**[[ %s | Dashboards Application ]]**.",
        $dashboard_href);
    } else {
      $content = pht(
        "When you're done setting things up, you can create a custom ".
        "dashboard and install it. Your dashboard will replace this ".
        "welcome screen on the Phabricator home page.\n\n".
        "Dashboards can show users the information that's most important to ".
        "your organization. You can configure them to display things like: ".
        "a custom welcome message, a feed of recent activity, or a list of ".
        "open tasks, waiting reviews, recent commits, and so on.\n\n".
        "After you install a default dashboard, it will replace this page. ".
        "You can find this page later by visiting the **Config** ".
        "application, under **Welcome Page**.\n\n".
        "To get started building a dashboard, use the ".
        "**[[ %s | Dashboards Application ]]**.\n\n",
        $dashboard_href);
    }

    $setup[] = $this->newItem(
      $request,
      pht('Customize Home Page'),
      $have_dashboard,
      $content);

    $apps_href = PhabricatorEnv::getURI('/applications/');
    $content = pht(
      "Phabricator is a large suite of applications that work together to ".
      "help you develop software, manage tasks, and communicate. A few of ".
      "the most commonly used applications are pinned to the left navigation ".
      "bar by default.\n\n".
      "To explore all of the Phabricator applications, adjust settings, or ".
      "uninstall applications you don't plan to use, visit the ".
      "**[[ %s | Applications Application ]]**. You can also click the ".
      "**Applications** button in the left navigation menu, or search for an ".
      "application by name in the main menu bar.\n\n",
      $apps_href);

    $explore = array();
    $explore[] = $this->newItem(
      $request,
      pht('Explore Applications'),
      null,
      $content);

    $support_href = PhabricatorEnv::getDoclink('Give Feedback! Get Support!');
    $content = pht(
      'Having trouble getting something set up? See '.
      '**[[ %s | Give Feedback! Get Support! ]]** for ways to get in touch '.
      'to get answers to questions, report bugs, and request features.',
      $support_href);

    $explore[] = $this->newItem(
      $request,
      pht('Need Help with Setup?'),
      null,
      $content);

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
      pht('Quick Start: Code Review'),
      null,
      pht(
        "Review code with **[[ %s | Differential ]]**.\n\n".
        "Engineers can use Differential to share, review, and approve ".
        "changes to source code.\n\n".
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
    $maniphest_create_uri = PhabricatorEnv::getURI('/maniphest/task/create/');
    $maniphest_all_uri = PhabricatorEnv::getURI('/maniphest/query/all/');
    $quick[] = $this->newItem(
      $request,
      pht('Quick Start: Bugs and Tasks'),
      null,
      pht(
        "Track bugs and tasks in Phabricator with ".
        "**[[ %s | Maniphest ]]**.\n\n".
        "Users in all roles can use Maniphest to manage current and ".
        "planned work and to track bugs and issues.\n\n".
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
      pht('Quick Start: Design Review'),
      null,
      pht(
        "Review proposed designs with **[[ %s | Pholio ]]**.\n\n".
        "Designers can use Pholio to share images of what they're working on ".
        "and show off things they've made.\n\n".
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
      pht('Quick Start: Repositories'),
      null,
      pht(
        "Manage and browse source code repositories with ".
        "**[[ %s | Diffusion ]]**.\n\n".
        "Engineers can use Diffusion to browse and audit source code.\n\n".
        "You can configure Phabricator to host repositories, or have it ".
        "track existing repositories hosted elsewhere (like GitHub, ".
        "Bitbucket, or an internal server).\n\n".
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


    return array(
      $this->newColumns(pht('Setup and Configuration'), $setup),
      $this->newColumns(pht('Explore Phabricator'), $explore),
      $this->newColumns(pht('Quick Start Guides'), $quick),
    );
  }

  private function newColumns($title, array $items) {
    $col1 = array();
    $col2 = array();
    for ($ii = 0; $ii < count($items); $ii += 2) {
      $col1[] = $items[$ii];
      if (isset($items[$ii + 1])) {
        $col2[] = $items[$ii + 1];
      }
    }

    $header = id(new PHUIHeaderView())->setHeader($title);

    $columns = id(new AphrontMultiColumnView())
      ->addColumn($col1)
      ->addColumn($col2)
      ->setFluidLayout(true);

    return phutil_tag(
      'div',
      array(
        'class' => 'config-welcome',
      ),
      array(
        $header,
        $columns,
      ));
  }

  private function newItem(AphrontRequest $request, $title, $done, $content) {
    $viewer = $request->getUser();

    $box = new PHUIObjectBoxView();
    $header = new PHUIActionHeaderView();
    $header->setHeaderTitle($title);
    if ($done === true) {
      $box->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTGREEN);
      $header->addAction(id(new PHUIIconView())->setIconFont('fa-check'));
    } else if ($done === false) {
      $box->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTVIOLET);
      $header->addAction(id(new PHUIIconView())->setIconFont('fa-exclamation'));
    }

    $content = PhabricatorMarkupEngine::renderOneObject(
      id(new PhabricatorMarkupOneOff())->setContent($content),
      'default',
      $viewer);

    $content = phutil_tag(
      'div',
      array(
        'class' => 'config-welcome-box-content',
      ),
      $content);

    $box->setHeader($header);
    $box->appendChild($content);

    return $box;
  }

}
