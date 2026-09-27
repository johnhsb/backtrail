<?php
// Location tabs shared by backup, restore and verify.
// Expects: $options (local partitions), $local_tip, $local_empty
// The underscore keeps action.php from loading this file as a page.

function location_field($label, $tip, $input) {
	print "<div class='mb-3'><label class='form-label fw-medium'>".h($label)." <i class='fas fa-info-circle bt-field-help' data-bs-toggle='tooltip' title='".h($tip)."'></i></label>$input</div>";
}

function password_input($id) {
	return "<div class='input-group'><input type='password' class='form-control' id='$id' name='$id' placeholder='".h(t('Password'))."'>"
		."<button class='btn btn-outline-secondary' type='button' onClick='BT.togglePassword(this);' aria-label='".h(t('Show or hide the password'))."'><i class='fas fa-eye'></i></button></div>";
}
?>
<ul id="redo_tabs" class="nav nav-tabs" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#local" role="tab"><i class="fas fa-hdd me-1"></i> <?php print t('This computer'); ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#cifs" role="tab"><i class="fas fa-network-wired me-1"></i> <?php print t('Network drive'); ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#nfs" role="tab">NFS</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#ssh" role="tab">SSH</button></li>
</ul>
<div class="tab-content">

  <div class="tab-pane fade show active" id="local" role="tabpanel">
    <?php if (sizeof($options) > 0) {
      $select = "<select class='form-select' name='local_part' id='local_part'>";
      foreach ($options as $ov=>$od) $select .= "<option value='".h($ov)."'>".h($od)."</option>";
      location_field(t('Local disk'), $local_tip, $select."</select>");
    } else { ?>
      <p class="text-muted fst-italic"><?php print $local_empty; ?></p>
    <?php } ?>
  </div>

  <div class="tab-pane fade" id="cifs" role="tabpanel">
    <?php
    location_field(t('Location'), t('Location of the shared network folder (SMB/CIFS)'),
      "<div class='input-group'><input class='form-control' id='cifs_location' name='cifs_location' placeholder='\\\\host\\folder' type='text'>"
      ."<button class='btn btn-outline-secondary' type='button' onClick='BT.shareSearch();'><i class='fas fa-search me-1'></i> ".t('Search')."</button></div>");
    location_field(t('Domain'), t('Optional domain name to access this network share'),
      "<input class='form-control' id='cifs_domain' name='cifs_domain' placeholder='WORKGROUP' type='text'>");
    ?>
    <div class="row">
      <div class="col-sm-6"><?php location_field(t('Username'), t('Optional network share username'), "<input class='form-control' id='cifs_username' name='cifs_username' placeholder='user' type='text'>"); ?></div>
      <div class="col-sm-6"><?php location_field(t('Password'), t('Optional network share password'), password_input('cifs_password')); ?></div>
    </div>
  </div>

  <div class="tab-pane fade" id="nfs" role="tabpanel">
    <?php
    location_field(t('Host'), t('Hostname or IP address of the NFSv3 share'),
      "<input class='form-control' id='nfs_host' name='nfs_host' placeholder='192.168.0.100' type='text'>");
    location_field(t('Share'), t('Exported NFS directory path'),
      "<input class='form-control' id='nfs_share' name='nfs_share' placeholder='/home/user' type='text'>");
    ?>
  </div>

  <div class="tab-pane fade" id="ssh" role="tabpanel">
    <?php
    location_field(t('Host'), t('Hostname or IP address of the SSH server'),
      "<input class='form-control' id='ssh_host' name='ssh_host' placeholder='192.168.0.100' type='text'>");
    location_field(t('Folder'), t('Optional folder to change to after connecting'),
      "<input class='form-control' id='ssh_folder' name='ssh_folder' placeholder='/home/user' type='text'>");
    ?>
    <div class="row">
      <div class="col-sm-6"><?php location_field(t('Username'), t('SSH username'), "<input class='form-control' id='ssh_username' name='ssh_username' placeholder='user' type='text'>"); ?></div>
      <div class="col-sm-6"><?php location_field(t('Password'), t('SSH password'), password_input('ssh_password')); ?></div>
    </div>
  </div>

</div>
