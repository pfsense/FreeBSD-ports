<?php

require_once("guiconfig.inc");
require_once("functions.inc");

$livekit_conf = &$config['installedpackages']['livekit']['config'][0];
$token = "";
$cached_identity = "";

if ($_POST) {
	$token_room = $_POST['token_room'];
	$cached_identity = $_POST['token_identity'];
	$token_validity = $_POST['token_validity'];

	if (empty($cached_identity)) {
		$input_errors[] = gettext("Identity cannot be empty");
	}
	if (empty($token_room)) {
		$input_errors[] = gettext("Room cannot be empty");
	}
	if (empty($token_validity)) {
		$input_errors[] = gettext("Validity cannot be empty");
	}

	if (!$input_errors) {
		$outputarray = array();
		$retval = 0;
		$command = "livekit-cli create-token " .
			"--api-key {$livekit_conf['livekit_apikey']} " .
			"--api-secret {$livekit_conf['livekit_secret']} ".
			"--join --room {$token_room} " .
			"--identity {$cached_identity} " .
			"--valid-for {$token_validity} " .
			"2>&1";
		exec($command, $outputarray, $retval);

		foreach ($outputarray as $outline) {
			$out = explode("access token: ", $outline);
			if (count($out) == 2) {
				$token = ltrim($out[1], ' ');
			}
		}

		if (empty($token)) {
			$input_errors = $outputarray;
		} else {
			config_set_path('installedpackages/livekit/config/0/last_token_room', $token_room);
			config_set_path('installedpackages/livekit/config/0/last_token_validity', $token_validity);
			write_config("Livekit token settings saved");
		}
	}
}

$pgtitle = array(gettext('Package'), gettext('LiveKit'), gettext('Token generation'));

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array("Settings", false, "/pkg_edit.php?xml=livekit.xml&amp;id=0");
$tab_array[] = array("Token generation", true, "/livekit_token_gen.php");
display_top_tabs($tab_array);

$submit = new Form_Button(
	'generate',
	'Generate',
	null,
	'fa-play'
);
$submit->addClass('btn-primary');

$form = new Form($submit);

$section = new Form_Section('Token generation');

$section->addInput(new Form_Input(
	'token_room',
	'Room',
	'text',
	($livekit_conf['last_token_room'] ? $livekit_conf['last_token_room'] : "general")
))->setHelp('Room associated with the token.');

$section->addInput(new Form_Input(
	'token_identity',
	'Identity',
	'text',
	$cached_identity
))->setHelp('Room associated with the token.');

$section->addInput(new Form_Input(
	'token_validity',
	'Validity',
	'text',
	($livekit_conf['last_token_validity'] ? $livekit_conf['last_token_validity'] : "5m")
))->setHelp('Amount of time that the token is valid for. i.e. "5m", "1h10m" (s: seconds, m: minutes, h: hours)');

$section->addInput(new Form_Input(
	'token',
	'Token',
	'text',
	$token
))->setHelp('Make sure to save the token, it will be only be displayed once.');

$form->add($section);

print($form);

include("foot.inc");

?>
