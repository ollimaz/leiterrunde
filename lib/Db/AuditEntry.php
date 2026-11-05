<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getEntityType()
 * @method void setEntityType(string $v)
 * @method int getEntityId()
 * @method void setEntityId(int $v)
 * @method string getField()
 * @method void setField(string $v)
 * @method ?string getOldValue()
 * @method void setOldValue(?string $v)
 * @method ?string getNewValue()
 * @method void setNewValue(?string $v)
 * @method string getUserId()
 * @method void setUserId(string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class AuditEntry extends BaseEntity {
	protected string $entityType = '';
	protected int $entityId = 0;
	protected string $field = '';
	protected ?string $oldValue = null;
	protected ?string $newValue = null;
	protected string $userId = '';
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('entityId', 'integer');
		$this->addType('createdAt', 'integer');
	}
}
