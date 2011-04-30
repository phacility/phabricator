<?php

/*
 * Copyright 2011 Facebook, Inc.
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

/**
 * This is the source for <http://www.phabricator.com/>.
 */

$path = $_REQUEST['__path__'];
$host = $_SERVER['HTTP_HOST'];
if ($host == 'secure.phabricator.com') {
  // If the user is requesting a secure.phabricator.com resource over HTTP,
  // redirect them to HTTPS.
  header('Location: https://secure.phabricator.com/'.$path);
}

?>
<!doctype html>
  <head>
    <title>Phabricator</title>
    <style type="text/css">

      body, html {
        margin: 0;
        background: #e9e9e9;
        font: normal normal normal 13px/1.231 'lucida grande', tahoma, verdana,
              arial, sans-serif;
      }

      h1 {
        margin: 0;
        padding: 0;
        font-weight: normal;
        font-size: 16px;
      }

      table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
      }

      td {
        vertical-align: top;
      }

      .tables-for-layout {
        border-bottom: 1px solid #999999;
      }

      .tables-for-layout .content-pane {
        width: 960px;
        padding: 10px 20px;
      }

      .layout-head td.not-a-content-pane {
        background: #697D8A;
      }

      .layout-head td.content-pane {
        background: #005588;
        color: white;
      }

      .layout-head td.content-pane a {
        color: white;
        font-size: 13px;
      }

      .layout-body td.not-a-content-pane {
        background: #f9f9f9;
      }

      .layout-body td.content-pane {
        background: #ffffff;
      }

      .inner-right-column {
        width: 240px;
        padding: 0 0 0 20px;
        border-left: 1px solid #dfdfdf;
      }

      .pull-quote {
        font-size: 14px;
        color: #444444;
        line-height: 1.6em;
        padding: 10px 15px;
        margin: 5px 15px 25px;
        background: #E6EFFF;
      }

      a, a:link, a:visited, a:hover {
        color: #3B5998;
        text-decoration: none;
      }

      a:hover {
        text-decoration: underline;
      }

      h2 {
        font-size: 14px;
        margin: 0;
      }

      h3 {
        font-size: 12px;
        margin: 0;
      }

      .link-list a {
        display: block;
        margin: 3px 0 3px 1em;
      }

      .inner-right-column h2 {
        padding-bottom: 4px;
        margin: 15px 0 4px 0;
        border-bottom: 1px solid #efefef;
      }

      .inner-right-column h3 {
        margin: 8px 0 4px;
      }

    </style>
  </head>
  <body>
    <table class="tables-for-layout">
      <tr class="layout-head">
        <td class="not-a-content-pane"></td>
        <td class="content-pane">
          <div style="float: right;">
            <a href="https://github.com/facebook/phabricator/">Github</a>
              &middot;
            <a href="#documentation">Documentation</a>
          </div>

          <h1>Phabricator</h1>
        </td>
        <td class="not-a-content-pane"></td>
      </tr>
      <tr class="layout-body">
        <td class="not-a-content-pane"></td>
        <td class="content-pane">
          <table class="more-tables-for-layout">
            <tr>
              <td class="inner-left-column">

                <div class="pull-quote">Phabricator is a collection of web
                applications which make it easier to write, review, and share
                source code. It is currently available as a <strong>preview
                release</strong>.</div>

                <h2>Phabricator, a software fabricator</h2>

                <p>Phabricator is the Open Source release of
                  <a href="https://www.facebook.com/">Facebook's</a> internal
                tools for code review, repository browsing and change
                management. It contains two major applications:
                <strong>Differential</strong>, a code review tool, and
                <strong>Diffusion</strong>, a repository browser.</p>

                <a name="documentation"></a>
                <h2>Documentation</h2>

                <p>Phabricator is split into three subprojects: Phabricator
                itself is the web application, Arcanist is the CLI interface,
                and libphutil is libraries shared between them.</p>

                <ul>
                  <li><a href="docs/phabricator/">Phabricator Docs</a></li>
                  <li><a href="docs/arcanist/">Arcanist Docs</a></li>
                  <li><a href="docs/libphutil/">libphutil Docs</a></li>
                </ul>

                <p>Phabricator also uses the
                  <a href="http://www.javelinjs.com/">Javelin</a> Javascript
                library.</p>

                <ul>
                  <li><a href="docs/javelin/">Javelin Docs</a></li>
                </ul>

              </td>
              <td class="inner-right-column">
                <h2>Phabricator</h2>
                <a href="https://secure.phabricator.com/">Phabricator
                  for Phabricator</a>


                <h2>IRC</h2>
                <a href="irc://chat.freenode.net/phabricator">#phabricator</a>
                  on FreeNode
              </td>
            </tr>
          </table>
        </td>
        <td class="not-a-content-pane"></td>
      </tr>
    </table>
  </body>
</html>
