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

  // If you have multiple environments, provide the production environment URI
  // here so that emails, etc., generated in development/sandbox environments
  // contain the right links.
  'phabricator.production-uri'  => null,

  // The default PHID for users who haven't uploaded a profile image. It should
  // be 50x50px.
  'user.default-profile-image-phid' => 'PHID-FILE-f57aaefce707fc4060ef',

// -- Access Control -------------------------------------------------------- //

  // Phabricator users have one of three access levels: "anyone", "verified",
  // or "admin". "anyone" means every user, including users who do not have
  // accounts or are not logged into the system. "verified" is users who have
  // accounts, are logged in, and have satisfied whatever verification steps
  // the configuration requires (e.g., email verification and/or manual
  // approval). "admin" is verified users with the "administrator" flag set.

  // These configuration options control which access level is required to read
  // data from Phabricator (e.g., view revisions and comments in Differential)
  // and write data to Phabricator (e.g., upload files and create diffs). By
  // default they are both set to "verified", meaning only verified user
  // accounts can interact with the system in any meaningful way.

  // If you are configuring an install for an open source project, you may
  // want to reduce the "phabricator.read-access" requirement to "anyone". This
  // will allow anyone to browse Phabricator content, even without logging in.

  // Alternatively, you could raise the "phabricator.write-access" requirement
  // to "admin", effectively creating a read-only install.


  // Controls the minimum access level required to read data from Phabricator
  // (e.g., view revisions in Differential). Allowed values are "anyone",
  // "verified", or "admin". Note that "anyone" includes users who are not
  // logged in! You should leave this at 'verified' unless you want your data
  // to be publicly readable (e.g., you are developing open source software).
  'phabricator.read-access'     => 'verified',

  // Controls the minimum access level required to write data to Phabricator
  // (e.g., create new revisions in Differential). Allowed values are
  // "verified" or "admin". Setting this to "admin" will effectively create a
  // read-only install.
  'phabricator.write-access'    => 'verified',


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


  // Allows you to mask certain configuration values from appearing in the
  // "Config" tab of DarkConsole.
  'darkconsole.config-mask'     => array(
    'mysql.pass',
    'amazon-ses.secret-key',
    'recaptcha.private-key',
    'phabricator.csrf-key',
    'facebook.application-secret',
    'github.secret',
  ),

// --  MySQL  --------------------------------------------------------------- //

  // The username to use when connecting to MySQL.
  'mysql.user' => 'root',

  // The password to use when connecting to MySQL.
  'mysql.pass' => '',

  // The MySQL server to connect to.
  'mysql.host' => 'localhost',


// -- Email ----------------------------------------------------------------- //

  // Some Phabricator tools send email notifications, e.g. when Differential
  // revisions are updated or Maniphest tasks are changed. These options allow
  // you to configure how email is delivered.

  // You can test your mail setup by going to "MetaMTA" in the web interface,
  // clicking "Send New Message", and then composing a message.

  // Default address to send mail "From".
  'metamta.default-address'     => 'noreply@example.com',

  // Domain used to generate Message-IDs.
  'metamta.domain'              => 'example.com',

  // When a user takes an action which generates an email notification (like
  // commenting on a Differential revision), Phabricator can either send that
  // mail "From" the user's email address (like "alincoln@logcabin.com") or
  // "From" the 'metamta.default-address' address. The user experience is
  // generally better if Phabricator uses the user's real address as the "From"
  // since the messages are easier to organize when they appear in mail clients,
  // but this will only work if the server is authorized to send email on behalf
  // of the "From" domain. Practically, this means:
  //    - If you are doing an install for Example Corp and all the users will
  //      have corporate @corp.example.com addresses and any hosts Phabricator
  //      is running on are authorized to send email from corp.example.com,
  //      you can enable this to make the user experience a little better.
  //    - If you are doing an install for an open source project and your
  //      users will be registering via Facebook and using personal email
  //      addresses, you MUST NOT enable this or virtually all of your outgoing
  //      email will vanish into SFP blackholes.
  //    - If your install is anything else, you're much safer leaving this
  //      off since the risk in turning it on is that your outgoing mail will
  //      mostly never arrive.
  'metamta.can-send-as-user'    => false,

  // Adapter class to use to transmit mail to the MTA. The default uses
  // PHPMailerLite, which will invoke PHP's mail() function. This is appropriate
  // if mail() actually works on your host, but if you haven't configured mail
  // it may not be so great. You can also use Amazon SES, by changing this to
  // 'PhabricatorMailImplementationAmazonSESAdapter', signing up for SES, and
  // filling in your 'amazon-ses.access-key' and 'amazon-ses.secret-key' below.
  'metamta.mail-adapter'        =>
    'PhabricatorMailImplementationPHPMailerLiteAdapter',

  // When email is sent, try to hand it off to the MTA immediately. This may
  // be worth disabling if your MTA infrastructure is slow or unreliable. If you
  // disable this option, you must run the 'metamta_mta.php' daemon or mail
  // won't be handed off to the MTA. If you're using Amazon SES it can be a
  // little slugish sometimes so it may be worth disabling this and moving to
  // the daemon after you've got your install up and running. If you have a
  // properly configured local MTA it should not be necessary to disable this.
  'metamta.send-immediately'    => true,

  // If you're using Amazon SES to send email, provide your AWS access key
  // and AWS secret key here. To set up Amazon SES with Phabricator, you need
  // to:
  //  - Make sure 'metamta.mail-adapter' is set to:
  //    "PhabricatorMailImplementationAmazonSESAdapter"
  //  - Make sure 'metamta.can-send-as-user' is false.
  //  - Make sure 'metamta.default-address' is configured to something sensible.
  //  - Make sure 'metamta.default-address' is a validated SES "From" address.
  'amazon-ses.access-key'       =>  null,
  'amazon-ses.secret-key'       =>  null,


// -- Auth ------------------------------------------------------------------ //

  // Can users login with a username/password, or by following the link from
  // a password reset email? You can disable this and configure one or more
  // OAuth providers instead.
  'auth.password-auth-enabled'  => true,


// -- Accounts -------------------------------------------------------------- //

  // Is basic account information (email, real name, profile picture) editable?
  // If you set up Phabricator to automatically synchronize account information
  // from some other authoritative system, you can disable this to ensure
  // information remains consistent across both systems.
  'account.editable'            => true,


// --  Facebook  ------------------------------------------------------------ //

  // Can users use Facebook credentials to login to Phabricator?
  'facebook.auth-enabled'       => false,

  // Can users use Facebook credentials to create new Phabricator accounts?
  'facebook.registration-enabled' => true,

  // Are Facebook accounts permanently linked to Phabricator accounts, or can
  // the user unlink them?
  'facebook.auth-permanent'     => false,

  // The Facebook "Application ID" to use for Facebook API access.
  'facebook.application-id'     => null,

  // The Facebook "Application Secret" to use for Facebook API access.
  'facebook.application-secret' => null,


// -- Github ---------------------------------------------------------------- //

  // Can users use Github credentials to login to Phabricator?
  'github.auth-enabled'         => false,

  // Can users use Github credentials to create new Phabricator accounts?
  'github.registration-enabled' => true,

  // Are Github accounts permanently linked to Phabricator accounts, or can
  // the user unlink them?
  'github.auth-permanent'       => false,

  // The Github "Client ID" to use for Github API access.
  'github.application-id'       => null,

  // The Github "Secret" to use for Github API access.
  'github.application-secret'   => null,


// -- Recaptcha ------------------------------------------------------------- //

  // Is Recaptcha enabled? If disabled, captchas will not appear.
  'recaptcha.enabled'           => false,

  // Your Recaptcha public key, obtained from Recaptcha.
  'recaptcha.public-key'        => null,

  // Your Recaptcha private key, obtained from Recaptcha.
  'recaptcha.private-key'       => null,


// -- Misc ------------------------------------------------------------------ //

  // This is hashed with other inputs to generate CSRF tokens. If you want, you
  // can change it to some other string which is unique to your install. This
  // will make your install more secure in a vague, mostly theoretical way. But
  // it will take you like 3 seconds of mashing on your keyboard to set it up so
  // you might as well.
  'phabricator.csrf-key'        => '0b7ec0592e0a2829d8b71df2fa269b2c6172eca3',

  // Version string displayed in the footer. You probably should leave this
  // alone.
  'phabricator.version'         => 'UNSTABLE',

  // PHP requires that you set a timezone in your php.ini before using date
  // functions, or it will emit a warning. If this isn't possible (for instance,
  // because you are using HPHP) you can set some valid constant for
  // date_default_timezone_set() here and Phabricator will set it on your
  // behalf, silencing the warning.
  'phabricator.timezone'        => null,


// -- Files ----------------------------------------------------------------- //

  // Lists which uploaded file types may be viewed in the browser. If a file
  // has a mime type which does not appear in this list, it will always be
  // downloaded instead of displayed. This is a security consideration: if a
  // user uploads a file of type "text/html" and it is displayed as
  // "text/html", they can eaily execute XSS attacks. This is also a usability
  // consideration, since browsers tend to freak out when viewing enormous
  // binary files.
  //
  // The keys in this array are viewable mime types; the values are the mime
  // types they will be delivered as when they are viewed in the browser.
  'files.viewable-mime-types' => array(
    'image/jpeg'  => 'image/jpeg',
    'image/jpg'   => 'image/jpg',
    'image/png'   => 'image/png',
    'image/gif'   => 'image/gif',
    'text/plain'  => 'text/plain; charset=utf-8',
  ),

// -- Differential ---------------------------------------------------------- //

  'differential.revision-custom-detail-renderer'  => null,


// -- Maniphest ------------------------------------------------------------- //

  'maniphest.enabled' => true,

// -- Customization --------------------------------------------------------- //

  // Paths to additional phutil libraries to load.
  'load-libraries' => array(),

  'aphront.default-application-configuration-class' =>
    'AphrontDefaultApplicationConfiguration',

  'controller.oauth-registration' =>
    'PhabricatorOAuthDefaultRegistrationController',


  // Directory that phd (the Phabricator daemon control script) should use to
  // track running daemons.
  'phd.pid-directory' => '/var/tmp/phd',

);
