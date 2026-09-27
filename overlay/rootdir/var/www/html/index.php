<?php
#
# Backtrail, based on Redo Rescue <redorescue.com>
# Copyright (C) 2010-2023 Zebradots Software
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <http://www.gnu.org/licenses/>.
#

require_once('functions.inc.php');

// A language change reloads the app on the page that was shown; keep the
// saved selections in that case. Otherwise start over on the welcome page.
$page = preg_replace('/[^a-z0-9\-]/', '', $_GET['page'] ?? '');
if ($page === '' || preg_match('/-progress$/', $page) || !is_file(__DIR__.'/pages/'.$page.'.inc.php') || !file_exists(STATUS_FILE)) {
	$page = 'welcome';

	// Show welcome notice once
	if (!file_exists(STATUS_FILE)) {
		system_notice(
			t('Welcome to Backtrail'),
			t('Additional tools can be found through the start menu'),
			"dialog-information"
		);
	}

	// Initiate variable storage
	$status = new stdClass();
} else {
	$status = get_status();
}

// Set host details
$host_info = get_host_details();
$status->ip = $host_info['ip'];
$status->hostname = $host_info['name'];

// Save status
set_status($status);

$vnc_pass = is_readable(VNCPASS_FILE) ? trim(file_get_contents(VNCPASS_FILE)) : '';
$bits = (PHP_INT_SIZE == 8) ? t('64-bit') : t('32-bit');
$languages = languages();
$current = $languages[lang()];
?>
<!doctype html>
<html lang="<?php print h(lang()); ?>">
  <head>
    <title>Backtrail</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="favicon.png">
    <script>
      (function () {
        var t = null;
        try { t = localStorage.getItem('bt-theme'); } catch (e) {}
        if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-bs-theme', t);
      })();
    </script>
    <link rel="stylesheet" href="/assets/bootstrap-5.3.8/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome-free-5.12.1-web/css/fontawesome.min.css">
    <link rel="stylesheet" href="/assets/fontawesome-free-5.12.1-web/css/solid.min.css">
    <link rel="stylesheet" href="/assets/backtrail/app.css">
    <script>window.BT_STRINGS = <?php print json_encode((object) lang_strings(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;</script>
  </head>
  <body>

    <header class="bt-bar">
      <img class="bt-logo" src="/images/backtrail-logo-dark.svg" alt="Backtrail">
      <span class="bt-pill"><?php print h(get_version()); ?> · <?php print $bits; ?></span>
      <span class="bt-spacer"></span>
      <?php if (!empty($status->ip)) { ?>
      <span class="bt-remote" title="<?php print h(t('Connect with a VNC viewer for remote assistance')); ?>">
        <i class="fas fa-desktop"></i> <?php print t('Remote access'); ?> <code><?php print h($status->ip); ?></code>
        <?php if ($vnc_pass !== '') { ?>· <?php print t('password'); ?> <code><?php print h($vnc_pass); ?></code><?php } ?>
      </span>
      <?php } ?>
      <div class="dropdown">
        <button type="button" id="lang-menu" class="bt-lang-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?php print h(t('Language').': '.$current['name']); ?>">
          <img class="bt-flag" src="/images/flags/<?php print h($current['flag']); ?>.svg" alt=""><span><?php print h($current['name']); ?></span><i class="fas fa-chevron-down"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end bt-lang-menu" aria-labelledby="lang-menu">
          <?php foreach ($languages as $code => $l) { ?>
          <li><button type="button" class="dropdown-item<?php print ($code === lang()) ? ' active' : ''; ?>" data-lang="<?php print h($code); ?>" lang="<?php print h($code); ?>"<?php print ($code === lang()) ? ' aria-current="true"' : ''; ?>>
            <img class="bt-flag" src="/images/flags/<?php print h($l['flag']); ?>.svg" alt=""><span class="bt-lang-name"><?php print h($l['name']); ?></span><span class="bt-lang-en" lang="en"><?php print h($l['english']); ?></span>
          </button></li>
          <?php } ?>
        </ul>
      </div>
      <button type="button" id="theme-toggle" class="bt-icon-btn" aria-label="<?php print h(t('Switch between light and dark mode')); ?>"><i class="fas fa-moon"></i></button>
    </header>

    <main class="bt-main">
      <div id="content" class="container"></div>
    </main>

    <footer class="bt-footer">
      <span>Backtrail <?php print h(get_version()); ?></span>
      <span><?php print t('Based on Redo Rescue by Zebradots Software'); ?> · GNU GPLv3</span>
    </footer>

    <script src="/assets/jquery-3.7.1/jquery.min.js"></script>
    <script src="/assets/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/bootbox-6.0.4/bootbox.min.js"></script>
    <script src="/assets/backtrail/app.js"></script>
    <script>
      $(function () { BT.show(<?php print js($page); ?>); });
    </script>

  </body>
</html>
