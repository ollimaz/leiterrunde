<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Module;

class OverviewTile {
	/**
	 * @param list<array{label:string, sublabel?:string, state?:string, url?:string}> $items
	 * @param array{value:string,label:string}|null $kpi
	 * @param array{type:string,unit?:string,series:list<array{label:string,value:float,state?:string,secondary?:float}>}|null $chart
	 */
	public function __construct(
		public string $id,
		public string $title,
		public string $severity,
		public ?int $count,
		public array $items,
		public ?string $url = null,
		public ?array $kpi = null,
		public ?array $chart = null,
	) {
	}
}
