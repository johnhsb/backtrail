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

// Show welcome notice once
if (!file_exists(STATUS_FILE)) {
	system_notice(
		"Welcome to Backtrail",
		"Additional tools can be found through the start menu",
		"dialog-information"
	);
}

// Initiate variable storage
$status = new stdClass();

// Set host details
$host_info = get_host_details();
$status->ip = $host_info['ip'];
$status->hostname = $host_info['name'];

// Save status
set_status($status);

$vnc_pass = is_readable(VNCPASS_FILE) ? trim(file_get_contents(VNCPASS_FILE)) : '';
$bits = (PHP_INT_SIZE == 8) ? '64-bit' : '32-bit';
?>
<!doctype html>
<html lang="en">
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
  </head>
  <body>

    <header class="bt-bar">
      <img class="bt-logo" src="/images/backtrail-logo-dark.svg" alt="Backtrail">
      <span class="bt-pill"><?php print h(get_version()); ?> · <?php print $bits; ?></span>
      <span class="bt-spacer"></span>
      <?php if (!empty($status->ip)) { ?>
      <span class="bt-remote" title="Connect with a VNC viewer for remote assistance">
        <i class="fas fa-desktop"></i> Remote access <code><?php print h($status->ip); ?></code>
        <?php if ($vnc_pass !== '') { ?>· password <code><?php print h($vnc_pass); ?></code><?php } ?>
      </span>
      <?php } ?>
      <button type="button" id="theme-toggle" class="bt-icon-btn" aria-label="Switch between light and dark mode"><i class="fas fa-moon"></i></button>
    </header>

    <main class="bt-main">
      <div id="content" class="container"></div>
    </main>

    <footer class="bt-footer">
      <span>Backtrail <?php print h(get_version()); ?></span>
      <span>Based on Redo Rescue by Zebradots Software · GNU GPLv3</span>
    </footer>

    <script src="/assets/jquery-3.7.1/jquery.min.js"></script>
    <script src="/assets/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/bootbox-6.0.4/bootbox.min.js"></script>
    <script src="/assets/backtrail/app.js"></script>
    <script>
      $(function () {
        $('#content').load('action.php', function (responseTxt, statusTxt, xhr) {
          if (statusTxt === 'error') {
            bootbox.alert({ title: 'Unable to load the page', message: xhr.status + ': ' + xhr.statusText });
          }
        });
      });
    </script>

  </body>
</html>
