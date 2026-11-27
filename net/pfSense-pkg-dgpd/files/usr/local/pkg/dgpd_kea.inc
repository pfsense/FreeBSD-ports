<?php
/*
 * Copyright 2026 gebra
 * SPDX-License-Identifier: Apache-2.0
 *
 * Optional read-only adapter for a stakeholder-owned services_dhcpv6.php include.
 * DGPD remains functional when the native page does not include this file.
 */
if (!function_exists('dgpd_state')) {
	require_once('/usr/local/pkg/dgpd.inc');
}

function dgpd_kea_assignment_models(): array {
	$models = [];
	foreach (dgpd_state()['assignments'] as $interface => $assignment) {
		if (($assignment['status'] ?? 'active') !== 'active') {
			continue;
		}
		$native = dgpd_native_fields($interface);
		$expected = dgpd_fields($assignment);
		$models[$interface] = [
			'interface' => $interface,
			'prefix' => $assignment['prefix'],
			'prefix_len' => (string)$assignment['prefix_len'],
			'delegated_len' => (string)$assignment['delegated_len'],
			'status' => $native === $expected ? 'ACTIVE' : 'CONFLICT',
			'native' => $native,
		];
	}
	return $models;
}

function dgpd_kea_assignments_html(?string $selected = null): string {
	$models = dgpd_kea_assignment_models();
	if (!$models) {
		return '<span>' . htmlspecialchars(gettext('No active Dynamic GUA-PD assignments.'), ENT_QUOTES, 'UTF-8') . '</span>';
	}
	$rows = '';
	foreach ($models as $interface => $model) {
		$label = strtoupper($interface) . ($selected === $interface ? ' (' . gettext('selected') . ')' : '');
		$cidr = $model['prefix'] . '/' . $model['prefix_len'] . ' -> /' . $model['delegated_len'];
		$native = $model['native'] ? ($model['native']['pdprefix'] ?? '') . '/' .
			($model['native']['pdprefixlen'] ?? '') . ' -> /' . ($model['native']['pddellen'] ?? '') : gettext('absent');
		$rows .= '<tr><td>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td><td><code>' .
			htmlspecialchars($cidr, ENT_QUOTES, 'UTF-8') . '</code></td><td><strong>' .
			htmlspecialchars($model['status'], ENT_QUOTES, 'UTF-8') . '</strong></td><td><code>' .
			htmlspecialchars($native, ENT_QUOTES, 'UTF-8') . '</code></td></tr>';
	}
	$link = '/pkg_edit.php?xml=dgpd.xml' . ($selected ? '&amp;interface=' . rawurlencode($selected) : '');
	return '<div class="table-responsive"><table class="table table-striped table-condensed"><thead><tr><th>' .
		htmlspecialchars(gettext('Interface'), ENT_QUOTES, 'UTF-8') . '</th><th>' .
		htmlspecialchars(gettext('DGPD Assignment'), ENT_QUOTES, 'UTF-8') . '</th><th>' .
		htmlspecialchars(gettext('Status'), ENT_QUOTES, 'UTF-8') . '</th><th>' .
		htmlspecialchars(gettext('Native DHCPv6'), ENT_QUOTES, 'UTF-8') . '</th></tr></thead><tbody>' .
		$rows . '</tbody></table></div><a href="' . $link . '">' .
		htmlspecialchars(gettext('Open Dynamic GUA-PD'), ENT_QUOTES, 'UTF-8') . '</a>';
}

/* The caller must guard the optional include with is_readable() and function_exists(). */
function dgpd_kea_append_assignment_section($form, ?string $selected = null): bool {
	if (!is_object($form) || !class_exists('Form_Section') || !class_exists('Form_StaticText')) {
		throw new RuntimeException(gettext('pfSense form framework is unavailable'));
	}
	if (!dgpd_kea_assignment_models()) {
		return false;
	}
	$section = new Form_Section(gettext('Dynamic GUA-PD Assignments'));
	$section->addInput(new Form_StaticText(gettext('Active assignments'),
		dgpd_kea_assignments_html($selected)));
	$form->add($section);
	return true;
}
