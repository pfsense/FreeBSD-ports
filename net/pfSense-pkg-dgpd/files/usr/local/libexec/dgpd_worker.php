#!/usr/local/bin/php
<?php
/*
 * Copyright 2026 gebra
 * SPDX-License-Identifier: Apache-2.0
 *
 * Dynamic GUA-PD asynchronous native verification worker.
 * A dedicated process keeps package resync callbacks out of native DHCP locks.
 */
require_once('/usr/local/pkg/dgpd.inc');

$worker = lock('dgpd-worker', LOCK_EX | LOCK_NB);
if (!$worker) {
	exit(0);
}
$signal = fopen(DGPD_SIGNAL, 'c+');
if ($signal === false) {
	unlock($worker);
	dgpd_log('error', 'Worker cannot open signal; persistent pending intent retained');
	exit(1);
}
$processed = null;
$retry_delays = [0, 2, 3, 5, 10, 20, 30];
try {
	while (true) {
		if (!flock($signal, LOCK_EX)) {
			throw new RuntimeException('Cannot lock DGPD signal');
		}
		try {
			rewind($signal);
			$raw = trim(stream_get_contents($signal));
			if ($raw !== '' && !ctype_digit($raw)) {
				throw new RuntimeException('Invalid DGPD signal generation');
			}
			$requested = (int)$raw;
			if ($processed !== null && $processed === $requested) {
				/* Release ownership under the signal lock to avoid a lost wakeup. */
				unlock($worker);
				$worker = null;
				break;
			}
			$processed = $requested;
		} finally {
			flock($signal, LOCK_UN);
		}
		foreach ($retry_delays as $attempt => $delay) {
			if ($delay > 0) {
				sleep($delay);
			}
			try {
				dgpd_log('debug', 'Reconciliation attempt',
					['generation' => $processed, 'attempt' => $attempt + 1, 'delay' => $delay]);
				$result = dgpd_renumber();
				$level = str_starts_with($result, 'UNCHANGED:') ? 'debug' : 'notice';
				dgpd_log($level, 'Reconciliation completed',
					['generation' => $processed, 'result' => $result]);
				if (str_starts_with($result, 'RENUMBERED:') || str_starts_with($result, 'STALE:')) {
					send_event('service reload packages');
				}
				break;
			} catch (Throwable $error) {
				$last = $attempt === array_key_last($retry_delays);
				$suffix = $last ? '; retry window exhausted' : '; retry scheduled';
				dgpd_log($last ? 'error' : 'debug', 'Reconciliation failed' . $suffix,
					['generation' => $processed, 'attempt' => $attempt + 1,
					'message' => $error->getMessage()]);
				if ($last) {
					break;
				}
			}
		}
	}
} catch (Throwable $error) {
	dgpd_log('error', 'Worker failed', ['message' => $error->getMessage()]);
	exit(1);
} finally {
	fclose($signal);
	unlock($worker);
}
