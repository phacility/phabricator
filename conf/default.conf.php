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

return array(

  // The root URI which Phabricator is installed on.
  // Example: "http://phabricator.example.com/"
  'phabricator.base-uri'        => null,


  'phabricator.csrf-key'        => '0b7ec0592e0a2829d8b71df2fa269b2c6172eca3',
  
  'phabricator.version'         => 'UNSTABLE',


  'user.default-profile-image-phid' => 'PHID-FILE-f57aaefce707fc4060ef',

  // When email is sent, try to hand it off to the MTA immediately. The only
  // reason to disable this is if your MTA infrastructure is completely
  // terrible. If you disable this option, you must run the 'metamta_mta.php'
  // daemon or mail won't be handed off to the MTA.
  'metamta.send-immediately'    => true,


// -- DarkConsole ----------------------------------------------------------- //

  // DarkConsole is a administrative debugging/profiling tool built into
  // Phabricator. You can leave it disabled unless you're developing against
  // Phabricator.
  
  // Determines whether or not DarkConsole is available. DarkConsole exposes
  // some data like queries and stack traces, so you should be careful about
  // turning it on in production (although users can not normally see it, even
  // if the deployment configuration enables it).
  'darkconsole.enabled'         => true,
  
  // Always enable DarkConsole, even for logged out users. This potentially
  // exposes sensitive information to users, so make sure untrusted users can
  // not access an install running in this mode. You should definitely leave
  // this off in production. It is only really useful for using DarkConsole
  // utilties to debug or profile logged-out pages. You must set
  // 'darkconsole.enabled' to use this option.
  'darkconsole.always-on'       => false,


// --  MySQL  --------------------------------------------------------------- //

  // The username to use when connecting to MySQL.
  'mysql.user' => 'root',

  // The password to use when connecting to MySQL.
  'mysql.pass' => '',

  // The MySQL server to connect to.
  'mysql.host' => 'localhost',


// --  Facebook  ------------------------------------------------------------ //

  // Can users use Facebook credentials to login to Phabricator?
  'facebook.auth-enabled'       => false,

  // The Facebook "Application ID" to use for Facebook API access.
  'facebook.application-id'     => null,

  // The Facebook "Application Secret" to use for Facebook API access.
  'facebook.application-secret' => null,



// -- Recaptcha ------------------------------------------------------------- //

  // Is Recaptcha enabled? If disabled, captchas will not appear.
  'recaptcha.enabled'           => false,

  // Your Recaptcha public key, obtained from Recaptcha.
  'recaptcha.public-key'        => null,

  // Your Recaptcha private key, obtained from Recaptcha.
  'recaptcha.private-key'       => null,



);
