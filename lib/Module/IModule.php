<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Module;

use OCP\IUser;

interface IModule {
	public function getId(): string;

	public function getLabel(): string;

	public function getNavUrl(): string;

	/** @return CapabilityDefinition[] */
	public function getCapabilities(): array;

	/** @return DocumentType[] */
	public function getDocumentTypes(): array;

	/** @return OverviewTile[] */
	public function getOverviewTiles(IUser $user): array;
}
