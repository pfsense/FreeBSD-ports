/*
 * WireGuardHelpers.js
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/theonemcdonald)
 * Copyright (c) 2021 Vajonam (https://github.com/vajonam)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/*
 * fixed version of bump_input_id(newGroup) 
 * Ref: https://github.com/pfsense/pfsense/pull/4517
 * Ref: https://redmine.pfsense.org/issues/11880
 */

function bump_input_id(newGroup) {
	$(newGroup).find('input').each(function() {
		$(this).prop("id", bumpStringInt(this.id));
		$(this).prop("name", bumpStringInt(this.name));
		if (!$(this).is('[id^=delete]'))
			$(this).val('');
	});

	// Increment the suffix number for the deleterow button element in the new group
	$(newGroup).find('[id^=deleterow]').each(function() {
		$(this).prop("id", bumpStringInt(this.id));
		$(this).prop("name", bumpStringInt(this.name));
	});

	// Do the same for selectors
	$(newGroup).find('select').each(function() {
		$(this).prop("id", bumpStringInt(this.id));
		$(this).prop("name", bumpStringInt(this.name));
		// If this selector lists mask bits, we need it to be reset to all 128 options
		// and no items selected, so that automatic v4/v6 selection still works
		if ($(this).is('[id^=address_subnet]')) {
			$(this).empty();
			for (idx=128; idx>=0; idx--) {
				$(this).append($('<option>', {
					value: idx,
					text: idx
				}));
			}
		}
	});
}
