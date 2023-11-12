<?php
require("guiconfig.inc");
require("/usr/local/pkg/coredns/coredns.inc");


if (!is_array($config['installedpackages']['coredns']['config'])) {
	$config['installedpackages']['coredns']['config'] = array();
}
$a_coredns = &$config['installedpackages']['coredns']['config'][0];

$pconfig = $a_coredns;

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	
	if (!$input_errors) {
		$coredns = array();
		$coredns['enable'] = $pconfig['enable'];
		$coredns['corefile'] = str_replace("\r\n", "\n", $pconfig['corefile']);


		$a_coredns = $coredns;
		write_config("coredns update");

		coredns_sync_config();

		header("Location: coredns_settings.php");
		exit;
	}

}


$pgtitle = array(gettext("Services"), gettext("Coredns"));
include("head.inc");


if ($input_errors) {
	print_input_errors($input_errors);
}


$form = new Form;
$section = new Form_Section('General Settings');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable the Coredns daemon',
	$pconfig['enable']
));


$section->addInput(new Form_Textarea(
	'corefile',
	'Corefile',
	$pconfig['corefile']
))->setHelp('Full CoreDns CoreFile configuration')
->setRows(15);

$form->add($section);

print($form);
?>


<script type="text/javascript">
//<![CDATA[
events.push(function() {
	var showadvanced = false;

	function publishingChange() {
		var hide = !$('#publishing').prop('checked')
		hideClass('publishing', hide);
	}

	// Initial page load
	publishingChange();
	checkLastRow();
});
//]]>
</script>

<?php include("foot.inc");
