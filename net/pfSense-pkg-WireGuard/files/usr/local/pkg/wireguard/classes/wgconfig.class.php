<?php
/*
 * wgconfig.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/rcmcdonald91)
 * Copyright (c) 2020 Dirk Henrici (https://github.com/towalink)
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

class wgconfig {
	private const SECTION_FIRSTLINE		= '_index_firstline';
	private const SECTION_LASTLINE		= '_index_lastline';
	private const SECTION_RAW		= '_rawdata';

	private const LINE_ATTR			= '_attr';
	private const LINE_VALUE		= '_value';
	private const LINE_COMMENT		= '_comment';

	private const KEY_ATTR			= 'PublicKey';

	private const INTERFACE_ATTR		= 'Interface';
	private const PEER_ATTR			= 'Peer';

	private const VALID_SECTIONS		= array(
							'interface' => self::INTERFACE_ATTR,
							'peer' => self::PEER_ATTR);
	protected $tunnel_path;

	protected $lines			= array();
	protected $comment_lines		= array();

	protected $interface			= array();
	protected $peers			= array();

	function __construct($tunnel_path = null, $default_local = true) {
		$this->tunnel_path = self::tunnel_absolute_path($tunnel_path, $default_local);
		$this->initialize_file();
	}

	function initialize_file($leading_comment = null) {
		$this->lines = array();
		$this->_add_leading_comment($leading_comment);
		$this->lines[] = sprintf('[%s]', self::INTERFACE_ATTR);
	}

	function read_file() {
		// Flush lines cache before reading the file
		unset($this->lines);

		if (file_exists($this->tunnel_path)) {
			$this->lines = explode(PHP_EOL, file_get_contents($this->tunnel_path));
		}
	}

	function write_file() {
		if (!empty($this->lines)) {
			$old_mask = umask(077);
			file_put_contents($this->tunnel_path, implode(PHP_EOL, $this->lines), LOCK_EX);
			umask($old_mask);
		}
	}

	function get_conf_string() {
		if (!empty($this->lines)) {
			return implode(PHP_EOL, $this->lines);
		}
	}

	function set_conf_string($conf_string) {
		unset($this->lines);
		$this->lines = explode(PHP_EOL, $conf_string);
	}

	function get_path() {
		return $this->tunnel_path;
	}

	function set_path($tunnel_path = null, $default_local = true) {
		$this->tunnel_path = self::tunnel_absolute_path($tunnel_path, $default_local);
	}

	function get_peers() {
		$this->_parse_lines();
		return $this->peers;
	}

	function get_interface() {
		$this->_parse_lines();
		return $this->interface;
	}

	function get_peer($key) {
		$peers = $this->get_peers();
		return $peers[$key];
	}

	function add_peer($key, $leading_comment = null) {
		// Ensure each peer has a unique public key
		if (!array_key_exists($key, $this->get_peers())) {
			// Seperate each peer with a blank line
			$this->lines[] 	= null;
			$this->_add_leading_comment($leading_comment);
			$this->lines[] 	= sprintf('[%s]', self::PEER_ATTR);
			$this->lines[]	= sprintf('%s = %s', self::KEY_ATTR, $key);
		}
	}

	function get_interface_attr($attr, $as_line = false) {
		$interface = $this->get_interface();
		$value = $interface[$attr][self::LINE_VALUE];
		return $as_line ? implode(',', (array) $value) : $value;
	}

	function get_peer_attr($key, $attr, $as_line = false) {
		$peers = $this->get_peers();
		$value = $peers[$key][$attr][self::LINE_VALUE];
		return $as_line ? implode(',', (array) $value) : $value;
	}

	function set_interface_attr($attr, $value, $leading_comment = null) {
		$this->_set_attr(null, $attr, $value, $leading_comment);
	}

	function set_peer_attr($key, $attr, $value, $leading_comment = null) {
		$this->_set_attr($key, $attr, $value, $leading_comment);
	}

	function del_interface_attr($attr, $value = null, $remove_leading_comments = true) {
		$this->_del_attr(null, $attr, $value, $remove_leading_comments);
	}

	function del_peer_attr($key, $attr, $value = null, $remove_leading_comments = true) {
		$this->_del_attr($key, $attr, $value, $remove_leading_comments);
	}

	function add_interface_attr($attr, $value, $leading_comment = null, $append_as_line = false) {
		$this->_add_attr(null, $attr, $value, $leading_comment, $append_as_line);
	}

	function add_peer_attr($key, $attr, $value, $leading_comment = null, $append_as_line = false) {
		$this->_add_attr($key, $attr, $value, $leading_comment, $append_as_line);
	}

	/*
	 * Below are the private functions
	*/

	private function _parse_line($line) {
		[$attr, $value] = explode('=', $line, 2);

		$attr = trim($attr);
		$parts = explode('#', $value, 2);
		$value = explode(',', trim($parts[0]));
		$comment = trim($parts[1]);

		array_walk($value, function(&$x) {
			$x = trim($x);
		});

		return array(self::LINE_ATTR => $attr, self::LINE_VALUE => $value, self::LINE_COMMENT => $comment);
	}

	private function _parse_lines() {
		$section = null;

		$section_data = array();

		$last_empty_line_in_section = -1;

		foreach ((array) $this->lines as $i => $line) {
			$line = trim($line);

			if (strlen($line) == 0) {
				$last_empty_line_in_section = $i;
				continue;
			}

			if (self::str_starts_with($line, '[')) {
				if (!is_null($last_empty_line_in_section)) {
					$section_data[self::SECTION_LASTLINE] = $last_empty_line_in_section - 1;
				}

				$this->_close_section($section, $section_data);

				$section_data = array();

				$section = strtolower(explode(']', substr($line, 1))[0]);

				if (is_null($last_empty_line_in_section)) {
					$section_data[self::SECTION_FIRSTLINE] = $i;
				} else {
					$section_data[self::SECTION_FIRSTLINE] = $last_empty_line_in_section + 1;
					$last_empty_line_in_section = null;
				}

				$section_data[self::SECTION_LASTLINE] = $i;
				
				if (!in_array($section, array_keys(self::VALID_SECTIONS))) {
					continue;
				}

			} elseif (self::str_starts_with($line, '#')) {
				$this->comment_lines[] = $line;
				$section_data[self::SECTION_LASTLINE] = $i;
			} else {
				[self::LINE_ATTR => $attr, self::LINE_VALUE => $value, self::LINE_COMMENT => $comment] = $this->_parse_line($line);

				$section_data[$attr] = array(self::LINE_ATTR => $attr, self::LINE_VALUE => $value, self::LINE_COMMENT => $comment);

				$section_data[self::SECTION_LASTLINE] = $i;
			}
		}

		$this->_close_section($section, $section_data);
	}

	private function _set_attr($key, $attr, $value, $leading_comment = null) {
		$this->_del_attr($key, $attr, null, true);
		$this->_add_attr($key, $attr, $value, $leading_comment, false);
	}

	private function _del_attr($key, $attr, $value = null, $remove_leading_comments = true) {
		$lines_found = array();

		if ([self::SECTION_FIRSTLINE => $section_firstline, self::SECTION_LASTLINE => $section_lastline] = $this->_get_section_range($key)) {
			foreach (range($section_firstline + 1, $section_lastline + 1) as $i) {
				[self::LINE_ATTR => $line_attr, self::LINE_VALUE => $line_value, self::LINE_COMMENT => $line_comment] = $this->_parse_line($this->lines[$i]);

				if ($attr == $line_attr) {
					if (is_null($value) || in_array($value, $line_value)) {
						$lines_found[] = $i;
					}
				}
			}

			foreach (array_reverse($lines_found) as $i) {
				if (is_null($value)) {
					array_splice($this->lines, $i, 1);
				} else {
					[self::LINE_ATTR => $line_attr, self::LINE_VALUE => $line_value, self::LINE_COMMENT => $line_comment] = $this->_parse_line($this->lines[$i]);

					array_splice($this->lines, array_search($value, $line_value), 1);

					if (count($line_value) > 0) {
						$line_values = implode(', ', $line_value);
						$line_comment = (strlen($line_comment) > 0) ? " # {$line_comment}" : null;
						$this->lines[$i] = "{$line_attr} = {$line_values}{$line_comment}";
					} else {
						array_splice($this->lines, $i, 1);
					}
				}
			}
		}
	}

	private function _add_attr($key, $attr, $value, $leading_comment = null, $append_as_line = false) {
		$line_found = false;

		$value_is_valid = !empty($value);

		// Keepalive can have a valid value of "0" (disabled) which empty() above will mark as false.
		// So mark it as valid for this case
		if ($attr == "PersistentKeepalive" && $value == "0") {
			$value_is_valid = true;
		}

		// Check if the section is valid and if the desired attribute value is valid...
		if (([self::SECTION_FIRSTLINE => $section_firstline, self::SECTION_LASTLINE => $section_lastline] = $this->_get_section_range($key))
		    && ($value_is_valid)) {
			foreach (range($section_firstline + 1, $section_lastline + 1) as $i) {
				[self::LINE_ATTR => $line_attr, self::LINE_VALUE => $line_value, self::LINE_COMMENT => $line_comment] = $this->_parse_line($this->lines[$i]);

				if ($attr == $line_attr) {
					$line_found = $i;
				}
			}

			if (($line_found == false) || $append_as_line) {
				$line_found = ($line_found === false) ? $section_lastline : $line_found;
				$line_found++;
				$attr_value = sprintf('%s = %s', $attr, $value);

				array_splice($this->lines, $line_found, 0, $attr_value);

				$this->_add_leading_comment($leading_comment, $line_found);
			} else {
				[self::LINE_ATTR => $line_attr, self::LINE_VALUE => $line_value, self::LINE_COMMENT => $line_comment] = $this->_parse_line($this->lines[$line_found]);

				$line_value[] = $value;

				$line_values = implode(',', $line_value);

				$line_comment = (strlen($line_comment) > 0) ? " # {$line_comment}" : null;

				$this->lines[$line_found] = "{$line_attr} = {$line_values}{$line_comment}";
			}
		}
	}

	private function _add_leading_comment($leading_comment = null, $position = null) {
		if (!is_null($leading_comment)) {
			$leading_comment = trim($leading_comment);
			array_splice($this->lines, (is_null($position) ? count($this->lines) : $position), 0, "# {$leading_comment}");
		}
	}

	private function _close_section($section, $section_data) {
		if (!is_null($section)) {
			$section_data[self::SECTION_RAW] = array_slice($this->lines, $section_data[self::SECTION_FIRSTLINE], ($section_data[self::SECTION_LASTLINE] - $section_data[self::SECTION_FIRSTLINE] + 1));

			switch (strtolower($section)) {
				// Interface Section
				case (strtolower(self::INTERFACE_ATTR)):
					$this->interface = $section_data;
					break;

				// Peer Section
				case (strtolower(self::PEER_ATTR)):
					[self::LINE_VALUE => $value, self::LINE_COMMENT => $comment] = $section_data[self::KEY_ATTR];
					$this->peers[$value[0]] = $section_data;
					break;
			}
		}
	}

	private function _get_interface_section_range() {
		return $this->_get_section_range(null);
	}

	private function _get_peer_section_range($key) {
		return $this->_get_section_range($key);
	}

	private function _get_section_range($key = null) {
		// Assume it is not...
		$is_range_valid = false;

		if (is_null($key)) {
			$interface = $this->get_interface();
			$section_firstline = $interface[self::SECTION_FIRSTLINE];
			$section_lastline = $interface[self::SECTION_LASTLINE];
			$is_range_valid = true;
		} else {
			$peers = $this->get_peers();

			if (array_key_exists($key, $peers)) {
				$section_firstline = $peers[$key][self::SECTION_FIRSTLINE];
				$section_lastline = $peers[$key][self::SECTION_LASTLINE];
				$is_range_valid = true;
			}
		}

		return $is_range_valid ? array(self::SECTION_FIRSTLINE => $section_firstline, self::SECTION_LASTLINE => $section_lastline) : $is_range_valid;
	}

	/*
	 * Below are static helper functions
	*/

	static function tunnel_absolute_path($tunnel_path = null, $default_local = true) {
		if (!is_null($tunnel_path)) {
			$path = pathinfo($tunnel_path);

			if ($path['basename'] == $tunnel_path) {
				if (empty($path['extension'])) {
					$tunnel_path .= '.conf';
				}

				$root = $default_local ? '/usr/local' : null;

				$tunnel_path = "{$root}/etc/wireguard/{$tunnel_path}";
			}
		}

		return $tunnel_path;
	}

	static function str_starts_with($haystack, $needle) {
		return (((string) $needle !== '') && (strncmp($haystack, $needle, strlen($needle)) === 0));
	}
}

?>
