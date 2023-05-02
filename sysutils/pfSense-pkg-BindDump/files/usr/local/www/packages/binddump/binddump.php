<?php
/*
 * binddump.php
 */
require_once("guiconfig.inc");
require_once("config.inc");
require_once("/usr/local/pkg/binddump.inc");

function exists_record_by_Name($entries, $name, $types = ['A', 'AAAA', 'PTR'])
{
    foreach ($entries as $entry) {
        if (trim($entry['name'], '.') == trim($name, '.') && in_array($entry['type'], $types)) {
            return true;
        }
    }
    return false;
}
if ($_REQUEST['loadData']) {
    $message = "";
    $entries = null;

    try {
        $entries = binddump_get_rndc_zone_dump_parsed();
    } catch (Exception $e) {
        http_response_code(422);
        header('Content-Type: application/json; charset=UTF-8');
        die(htmlspecialchars('[EXCEPTION] ' . $e->getMessage()));
    }

    if (empty($entries)) {
        ?>
        <tr>
            <td colspan="5">
                <?= htmlspecialchars(gettext("No entries to display")) ?>
            </td>
        </tr>
    <?

    } else {
        foreach ($entries as $entry) { ?>
            <tr data-item="<?= base64_encode(json_encode($entry)) ?>" data-zone="<?= htmlspecialchars($entry['zone']) ?>"
                data-name="<?= htmlspecialchars($entry['name']) ?>" data-rdata="<?= htmlspecialchars($entry['rdata']) ?>"
                data-type="<?= htmlspecialchars($entry['type']) ?>">
                <td>
                    <?= insert_word_breaks_in_domain_name(htmlspecialchars($entry["name_part1"])) ?><span class="text-muted">
                        <?= htmlspecialchars($entry["name_part2"]) ?>
                    </span>
                </td>
                <td>
                    <?= htmlspecialchars($entry["type"]) ?>
                </td>
                <td>
                    <?
                    $skip = ['name_part1', 'name_part2', 'index', 'class', 'name', 'type', '_extended'];
                    foreach ($entry as $key => $val) {
                        if (!in_array($key, $skip) && !empty($val) && is_string($val)) {
                            $icon = '';
                            $textclass = 'text-success';

                            if (!empty($entry['_extended']) && in_array($key, $entry['_extended'])) {
                                $textclass = 'text-warning';
                            }

                            if (exists_record_by_Name($entries, $val)) {
                                $icon = '<i class="fa fa-check"></i>';
                            }

                            print("<span class=\"text-uppercase {$textclass}\">" . htmlspecialchars(gettext($key)) . ': </span>' . htmlspecialchars($val) . $icon . '<br />');
                        }
                    }
                    ?>
                </td>
                <td>
                    <a class="fa fa-trash" title="<?= gettext('Delete host') ?>"
                        onclick="deleteHost($(this).closest('tr').attr('data-item'));"></a>
                </td>
            </tr>
        <?php }
    }
    exit;
}
if ($_REQUEST['download']) {
    $file = null;

    switch ($_REQUEST['download']) {
        case 'zoneDump':
            $rndc_conf_path = BIND_LOCALBASE . "/etc/rndc.conf";
            $rndc = "/usr/local/sbin/rndc -q -c " . $rndc_conf_path;
            $output = null;
            $retval = null;

            exec("{$rndc} dumpdb -zones", $output, $retval);
            if ($retval !== 0) {
                die('Exception during zone compiling. Code:' . $retval . " \n Message: " . $output);
            }
            $file = CHROOT_LOCALBASE . '/etc/namedb/named_dump.db';

            if (!binddump_waitfor_string_in_file($file, "; Dump complete", 30)) {
                die('Timeout during zone dump');
            }
            break;

        default:
            die('Invalid Request');
    }

    if ($file && file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    } else {
        die('File not found');
    }
}
if ($_REQUEST['action'] == "delete_host") {
    $item = json_decode(base64_decode($_REQUEST['item']), true);
    $result = false;

    if ($item['type'] == 'SOA') {
        print("[Exception] ");
        print("Delete of SOA not allowed.");
        return;
    }

    try {
        $result = binddump_addremove_items_to_zone($item['zone'], $item['view'], [], [$item]);
    } catch (Exception $e) {
        print("[Exception] ");
        print($e->getMessage());
    }

    if ($result) {
        print("[OK]");
    } else {
        print(gettext("Removal was not successfull.") . "<br />");
    }
    return;
}
if ($_REQUEST['action'] == "add_host") {
    $item = [];
    $item['name'] = $_REQUEST['name'];
    $item['ttl'] = $_REQUEST['ttl'];
    $item['rdata'] = $_REQUEST['rdata'];
    $item['view'] = $_REQUEST['view'];
    $item['zone'] = $_REQUEST['zone'];
    $item['type'] = strtoupper($_REQUEST['type']);

    $result = false;

    if ($item['type'] == 'SOA') {
        print("[Exception] ");
        print("SOA not allowed.");
        return;
    }
    try {
        $result = binddump_addremove_items_to_zone($item['zone'], $item['view'], [$item], []);
    } catch (Exception $e) {
        print("[Exception] ");
        print($e->getMessage());
    }

    if ($result) {
        print("[OK]");
    } else {
        print(gettext("Adding was not successfull.") . "<br />");
    }
    return;
}



$pgtitle = array(gettext("Status"), gettext("BIND DNS Dump"));
$shortcut_section = "bind";

include("head.inc");
if ($input_errors) {
    print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Database"), true, "/packages/binddump/binddump.php");
$tab_array[] = array(gettext("Edit RAW Zone File"), false, "/packages/binddump/zoneEdit.php");
display_top_tabs($tab_array);
?>

<div class="panel panel-default" id="search-panel">
    <div class="panel-heading">
        <h2 class="panel-title">
            <?= gettext('Search') ?>
            <span class="widget-heading-icon pull-right">
                <a data-toggle="collapse" href="#search-panel_panel-body">
                    <i class="fa fa-plus-circle"></i>
                </a>
            </span>
        </h2>
    </div>
    <div id="search-panel_panel-body" class="panel-body collapse in">
        <div class="form-group">
            <label class="col-sm-2 control-label">
                <?= gettext("Search term") ?>
            </label>
            <div class="col-sm-5"><input class="form-control" name="searchstr" id="searchstr" type="text" /></div>
            <div class="col-sm-2">
                <select id="where" class="form-control">
                    <option value="1">
                        <?= gettext("Zone") ?>
                    </option>
                    <option value="2">
                        <?= gettext("Name") ?>
                    </option>
                    <option value="3">
                        <?= gettext("RData") ?>
                    </option>
                    <option value="0" selected>
                        <?= gettext("All") ?>
                    </option>
                </select>
            </div>
            <div class="col-sm-4">
                <a id="btnsearch" title="<?= gettext("Search") ?>" class="btn btn-primary btn-sm"><i
                        class="fa fa-search icon-embed-btn"></i>
                    <?= gettext("Search") ?>
                </a>
                <a id="btnclear" title="<?= gettext("Clear") ?>" class="btn btn-info btn-sm"><i
                        class="fa fa-undo icon-embed-btn"></i>
                    <?= gettext("Clear") ?>
                </a>
                <a id="btnreload" title="<?= gettext("Reload") ?>" class="btn btn-info btn-sm"><i
                        class="fa fa-undo icon-embed-btn"></i>
                    <?= gettext("Reload") ?>
                </a>
            </div>
            <div class="col-sm-10 col-sm-offset-2">
                <span class="help-block">
                    <?= gettext('Enter a search string or *nix regular expression to filter entries.') ?>
                </span>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-2 control-label">
                <?= gettext("Zone") ?>
            </label>
            <div class="col-sm-4">
                <select id="zoneselect" class="form-control">
                    <option value="all" selected>
                        <?= gettext("All Zones") ?>
                    </option>
                    <?php foreach (binddump_get_zonelist() as $zone): ?>
                        <option value="<?= htmlspecialchars(binddump_reverse_zonename($zone) . '.') ?>"><?= $zone['type'] . ': ' . htmlspecialchars(binddump_reverse_zonename($zone) . '.') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-2 control-label">
                <?= gettext("Type") ?>
            </label>
            <div class="col-sm-2">
                <select id="typeselect" class="form-control">
                    <option value="all" selected>
                        <?= gettext("All Types") ?>
                    </option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title">
            <?= gettext('Database') ?>
        </h2>
    </div>
    <div class="panel-body">
        <div class="form-group">
            <div class="col-sm-5">
                <a id="btndownloadFullZone" title="<?= gettext("Download Full Zone Dump") ?>"
                    class="btn btn-info btn-sm"><i class="fa fa-download icon-embed-btn"></i>
                    <?= gettext("Full Zone Dump") ?>
                </a>
            </div>
        </div>
    </div>

    <div class="panel-body table-responsive">
        <table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
            <thead>
                <tr>
                    <th>
                        <?= gettext("Name") ?>
                    </th>
                    <th>
                        <?= gettext("Type") ?>
                    </th>
                    <th>
                        <?= gettext("Data") ?>
                    </th>
                    <th data-sortable="false">
                        <?= gettext("Actions") ?>
                    </th>
                </tr>
            </thead>
            <tbody id="leaselist">
                <tr>
                    <td colspan=5>
                        <?= htmlspecialchars(gettext('Loading...')) ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>


<?php

$form = new Form(false);
// Create a Modal Dialog
$modal = new Modal('Aktion', 'dlg_updatestatus', 'large', 'Close');
$modal->addInput(
    new Form_Textarea(
        'dlg_updatestatus_text',
        '',
        '...Loading...'
    )
)->removeClass('form-control')->addClass('row-fluid col-sm-10')->setAttribute('rows', '10')->setAttribute('wrap', 'off');
$form->add($modal);

$modal = new Modal(gettext('Please wait'), 'dlg_wait', false, 'Close');
$modal->addInput(
    new Form_StaticText(
        'dlg_wait_text',
        'Please wait for the process to complete.<br/><br/>This dialog will auto-close when the update is finished.<br/><br/>' .
        '<i class="content fa fa-spinner fa-pulse fa-lg text-center text-info"></i>'
    )
);
$form->add($modal);

print $form;

include('foot.inc');

print '<script type="text/javascript">';
print (file_get_contents('binddump.js', true));
print '</script>';
?>
