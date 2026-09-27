<?php
// Progress view shared by backup, restore and verify.
// Expects: $op, $title, $lead, $show_dest (bool), $target (string or NULL)
page_header($op, 0, $title, $lead);
?>

<div class="bt-card">
  <div class="bt-prog-top">
    <div class="bt-status" id="details">Please wait...</div>
    <div class="bt-pct"><span id="overall_pct">0.0</span><small>%</small></div>
  </div>
  <div class="progress" role="progressbar" aria-label="Overall progress">
    <div id="overall_bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
  </div>
</div>

<div class="bt-stats">
  <div class="bt-stat"><span class="bt-k">Partition</span><span class="bt-v" id="part_num">–</span></div>
  <div class="bt-stat"><span class="bt-k">Elapsed</span><span class="bt-v" id="time_elapsed">00:00:00</span></div>
  <div class="bt-stat"><span class="bt-k">Remaining</span><span class="bt-v" id="time_remaining">–</span></div>
  <div class="bt-stat"><span class="bt-k">Rate</span><span class="bt-v" id="speed">–</span></div>
</div>

<div class="bt-card">
  <div class="row g-3 align-items-center">
    <div class="col-md-6">
      <div class="bt-label mb-1">Current partition</div>
      <span id="part_pct">0.00%</span> done · size <span id="part_size">–</span> · used <span id="part_used">–</span> · <span id="part_mode" class="bt-tag">–</span>
    </div>
    <div class="col-md-6">
    <?php if ($show_dest) { ?>
      <div class="bt-label mb-1">Destination drive</div>
      <div class="d-flex justify-content-between small mb-1"><span><span id="dest_used">–</span> used</span><span><span id="dest_free">–</span> free</span></div>
      <div class="progress bt-thin"><div id="dest_bar" class="progress-bar" style="width: 0%"></div></div>
    <?php } elseif ($target !== NULL) { ?>
      <div class="bt-label mb-1">Target drive</div>
      <span class="bt-dev" id="target"><?php print h($target); ?></span>
    <?php } ?>
    </div>
  </div>
</div>

<details class="bt-details mt-3">
  <summary>Detailed log</summary>
  <textarea class="form-control bt-log mt-2" rows="6" id="log-box" readonly></textarea>
  <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="copy-log"><i class="fas fa-clipboard-check me-1"></i> Copy to clipboard</button>
</details>

<div class="bt-actions justify-content-between align-items-center">
  <span class="text-muted small">Keep this screen open until the operation finishes.</span>
  <span class="d-flex gap-2">
    <button id="cancel" type="button" class="btn btn-outline-danger"><i class="fas fa-times-circle me-1"></i> Cancel</button>
    <button id="again" type="button" class="btn btn-outline-secondary d-none"><i class="fas fa-redo me-1"></i> Start again</button>
    <button id="exit" type="button" class="btn btn-primary d-none"><i class="fas fa-check-circle me-1"></i> Exit</button>
  </span>
</div>
