<?php
// Progress view shared by backup, restore and verify.
// Expects: $op, $title, $lead, $show_dest (bool), $target (string or NULL)
page_header($op, 0, $title, $lead);
?>

<div class="bt-card">
  <div class="bt-prog-top">
    <div class="bt-status" id="details"><?php print t('Please wait...'); ?></div>
    <div class="bt-pct"><span id="overall_pct">0.0</span><small>%</small></div>
  </div>
  <div class="progress" role="progressbar" aria-label="<?php print h(t('Overall progress')); ?>">
    <div id="overall_bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
  </div>
</div>

<div class="bt-stats">
  <div class="bt-stat"><span class="bt-k"><?php print t('Partition'); ?></span><span class="bt-v" id="part_num">–</span></div>
  <div class="bt-stat"><span class="bt-k"><?php print t('Elapsed'); ?></span><span class="bt-v" id="time_elapsed">00:00:00</span></div>
  <div class="bt-stat"><span class="bt-k"><?php print t('Remaining'); ?></span><span class="bt-v" id="time_remaining">–</span></div>
  <div class="bt-stat"><span class="bt-k"><?php print t('Rate'); ?></span><span class="bt-v" id="speed">–</span></div>
</div>

<div class="bt-card">
  <div class="row g-3 align-items-center">
    <div class="col-md-6">
      <div class="bt-label mb-1"><?php print t('Current partition'); ?></div>
      <?php print t('%1$s done · size %2$s · used %3$s', '<span id="part_pct">0.00%</span>', '<span id="part_size">–</span>', '<span id="part_used">–</span>'); ?> · <span id="part_mode" class="bt-tag">–</span>
    </div>
    <div class="col-md-6">
    <?php if ($show_dest) { ?>
      <div class="bt-label mb-1"><?php print t('Destination drive'); ?></div>
      <div class="d-flex justify-content-between small mb-1"><span><?php print t('%s used', '<span id="dest_used">–</span>'); ?></span><span><?php print t('%s free', '<span id="dest_free">–</span>'); ?></span></div>
      <div class="progress bt-thin"><div id="dest_bar" class="progress-bar" style="width: 0%"></div></div>
    <?php } elseif ($target !== NULL) { ?>
      <div class="bt-label mb-1"><?php print t('Target drive'); ?></div>
      <span class="bt-dev" id="target"><?php print h($target); ?></span>
    <?php } ?>
    </div>
  </div>
</div>

<details class="bt-details mt-3">
  <summary><?php print t('Detailed log'); ?></summary>
  <textarea class="form-control bt-log mt-2" rows="6" id="log-box" readonly></textarea>
  <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="copy-log"><i class="fas fa-clipboard-check me-1"></i> <?php print t('Copy to clipboard'); ?></button>
</details>

<div class="bt-actions justify-content-between align-items-center">
  <span class="text-muted small"><?php print t('Keep this screen open until the operation finishes.'); ?></span>
  <span class="d-flex gap-2">
    <button id="cancel" type="button" class="btn btn-outline-danger"><i class="fas fa-times-circle me-1"></i> <?php print t('Cancel'); ?></button>
    <button id="again" type="button" class="btn btn-outline-secondary d-none"><i class="fas fa-redo me-1"></i> <?php print t('Start again'); ?></button>
    <button id="exit" type="button" class="btn btn-primary d-none"><i class="fas fa-check-circle me-1"></i> <?php print t('Exit'); ?></button>
  </span>
</div>
