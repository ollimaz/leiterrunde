<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getSubjectType()
 * @method void setSubjectType(string $v)
 * @method int getSubjectId()
 * @method void setSubjectId(int $v)
 * @method string getCheckKey()
 * @method void setCheckKey(string $v)
 * @method string getState()
 * @method void setState(string $v)
 * @method int getChangedAt()
 * @method void setChangedAt(int $v)
 */
class State extends BaseEntity {
	protected string $subjectType = '';
	protected int $subjectId = 0;
	protected string $checkKey = '';
	protected string $state = '';
	protected int $changedAt = 0;

	public function __construct() {
		$this->addType('subjectId', 'integer');
		$this->addType('changedAt', 'integer');
	}
}
