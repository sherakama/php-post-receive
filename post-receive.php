<?php

require_once dirname(__FILE__) . '/twitter-php/twitter.class.php';
$consumerKey = 'abcd';
$consumerSecret = 'efgh';
$accessToken = 'ijkl';
$accessTokenSecret = 'mnop';

$path = request_path();

// The idea is if only 1 argument is present, treat that as the /var/www/vhosts/<directory_name>
// and if more than 1 argument is given, treat that as the full "cd-able" path

// Edit this string to reflect on the default location of vhost web roots
// DO include the trailing slash
// Example: $default_parent_path = '/var/www/vhosts/';
$default_parent_path = '/var/www/vhosts/';

// The name of the public_html directory
// DO include the leading slash
// DO NOT include the trailing slash
// Example: $default_public_directory = '/public';
$default_public_directory = '/public';

// Checks the api key to see if it is correct, if not then quits
// Currently set to static key '12345abcde'
// Example: http://git.post-receive.ixm.ca/viff.ixm.ca?ak=12345abcde
if (empty($_GET['ak'])) {
  echo '<pre>No API KEY provided</pre>';
  exit;
}
if ($_GET['ak']!='12345abcde') {
  echo '<pre>Wrong API KEY provided</pre>';
  exit;
}

// Specify which branch by appending a branch name variable 'bn' to the end of the URL
// defaults to 'develop' if none specified
// Example: http://git.post-receive.ixm.ca/viff.ixm.ca?cc=cssminusjs&bn=deployment
$default_pull_branch_name = 'develop';
if (empty($_GET['bn'])) {
  $pull_branch_name = $default_pull_branch_name;
}
else {
  $pull_branch_name = $_GET['bn'];
}



$args = explode('/', $path);

if (count($args) === 1) {
  $working_path = $default_parent_path . $path . $default_public_directory;
}
elseif (count($args) > 1) {
  $working_path = $path;
}

// Do the routine only if the path is good
if (!empty($working_path) && file_exists($working_path)) {
  // Check if branch exist before continuing
  $branch_names = shell_exec("git branch");
  if(!stristr($branch_names, $pull_branch_name)) {
    echo "<pre>Branch $pull_branch_name does not exist.</pre>";
    exit;
  };

  // Get tags info only if in stage branch or prod branch
  if ($pull_branch_name == 'stage' || $pull_branch_name == 'prod') {
    // Fetch and check version numbers from tags
    $preoutput = shell_exec("cd $working_path; git fetch origin; git fetch origin --tags; git tag");
    // Finds an array of major versions by reading a string of numbers that comes after '7.x-'
    preg_match_all('/(?<=(7\.x-))[0-9]+/', $preoutput, $matches_majver);
    // Finds the latest major version by taking the version number with the greatest numerical value
    $majver = max($matches_majver[0]);
    // Finds an array of minor versions by reading a string of numbers that comes after '7.x-{$majver}.'
    // where {$majver} is the latest major version number previously found above
    preg_match_all('/(?<=(7\.x-' . $majver . '.))[0-9]+/', $preoutput, $matches_minver);
    // Finds the latest minor version by taking the version number with the greatest numerical value
    $minver = max($matches_minver[0]);

    // Check if on 'stage' branch, if so then include checks for beta version number
    if ($_GET['bn'] == 'stage') {
      // Finds an array of minor versions by reading a string of numbers that comes after '7.x-{$majver}.{$minver}beta'
      // where {$majver} and {$minver} is the latest version numbers previously found above
      preg_match_all('/(?<=(7\.x-' . $majver . '.' . $minver . 'beta))[0-9]+/', $preoutput, $matches_betver);
      // Finds the latest beta version by taking the version number with the greatest numerical value
      $betver = max($matches_betver[0]);
    }

    // Concaternate version numbers together to form the highest version tag
    // If branch is 'stage', then include beta version, otherwise just major version and minor version number
    $topver = ($_GET['bn'] == 'stage') ? '7.x-' . $majver . '.' . $minver . 'beta' . $betver : '7.x-' . $majver . '.' . $minver;
    echo "<pre>The latest version detected is version $topver</pre>";
    // Execute deployment in shell
    $output = shell_exec("cd $working_path; git fetch origin; git reset --hard; git checkout $pull_branch_name; git fetch origin $pull_branch_name; git merge tags/$topver; git submodule init; git submodule update");
    echo "<pre>$output</pre>";
  }
  else {
    $output = shell_exec("cd $working_path; git fetch origin; git reset --hard; git checkout $pull_branch_name; git pull origin $pull_branch_name; git submodule init; git submodule update");
  echo "<pre>$output</pre>";
  }

  // Tweet on success
  if (!empty($_POST) && !empty($_POST['payload'])) {
    error_log(print_r($_POST, 1));
    $payload = json_decode($_POST['payload']);
    error_log(print_r($payload, 1));

    if (!empty($payload)) {
      $twitter = new Twitter($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
      $last_commit = end($payload->commits);
      $twitter->send('[' . $payload->repository->name . ':' . $pull_branch_name . '] ' . count($payload->commits) . ' commit(s) deployed. Last commit: ' . end($payload->commits)->node . ' by ' . end($payload->commits)->raw_author);
    }
  }

  // Drush clear cache commands
  if (!empty($_GET['cc'])) {
    switch ($_GET['cc']) {
      case 'all':
        shell_exec("cd $working_path; drush cc all");
        break;
      case 'cssplusjs':
        shell_exec("cd $working_path; drush cc css+js");
        break;
      case 'cssminusjs':
        shell_exec("cd $working_path; drush cc css-js");
        break;
    }
  }
}

/**
 * Returns the requested URL path of the page being viewed.
 *
 * Examples:
 * - http://example.com/node/306 returns "node/306".
 * - http://example.com/drupalfolder/node/306 returns "node/306" while
 *   base_path() returns "/drupalfolder/".
 * - http://example.com/path/alias (which is a path alias for node/306) returns
 *   "path/alias" as opposed to the internal path.
 * - http://example.com/index.php returns an empty string (meaning: front page).
 * - http://example.com/index.php?page=1 returns an empty string.
 *
 * @return
 *   The requested Drupal URL path.
 *
 * @see current_path()
 */
function request_path() {
  static $path;

  if (isset($path)) {
    return $path;
  }

  if (isset($_GET['q'])) {
    // This is a request with a ?q=foo/bar query string. $_GET['q'] is
    // overwritten in drupal_path_initialize(), but request_path() is called
    // very early in the bootstrap process, so the original value is saved in
    // $path and returned in later calls.
    $path = $_GET['q'];
  }
  elseif (isset($_SERVER['REQUEST_URI'])) {
    // This request is either a clean URL, or 'index.php', or nonsense.
    // Extract the path from REQUEST_URI.
    $request_path = strtok($_SERVER['REQUEST_URI'], '?');
    $base_path_len = strlen(rtrim(dirname($_SERVER['SCRIPT_NAME']), '\/'));
    // Unescape and strip $base_path prefix, leaving q without a leading slash.
    $path = substr(urldecode($request_path), $base_path_len + 1);
    // If the path equals the script filename, either because 'index.php' was
    // explicitly provided in the URL, or because the server added it to
    // $_SERVER['REQUEST_URI'] even when it wasn't provided in the URL (some
    // versions of Microsoft IIS do this), the front page should be served.
    if ($path == basename($_SERVER['PHP_SELF'])) {
      $path = '';
    }
  }
  else {
    // This is the front page.
    $path = '';
  }

  // Under certain conditions Apache's RewriteRule directive prepends the value
  // assigned to $_GET['q'] with a slash. Moreover we can always have a trailing
  // slash in place, hence we need to normalize $_GET['q'].
  $path = trim($path, '/');

  return $path;
}

?>

