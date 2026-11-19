<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Db;

/**
 * @method string getTitle()
 * @method void setTitle(string $v)
 * @method string getMode()
 * @method void setMode(string $v)
 * @method ?string getBodyText()
 * @method void setBodyText(?string $v)
 * @method bool getRequired()
 * @method void setRequired(bool $v)
 * @method ?int getFileId()
 * @method void setFileId(?int $v)
 * @method ?string getFileOwner()
 * @method void setFileOwner(?string $v)
 * @method ?string getFileName()
 * @method void setFileName(?string $v)
 * @method ?string getTargetRule()
 * @method void setTargetRule(?string $v)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class Document extends BaseEntity {
	public const MODE_TEXT = 'text';
	public const MODE_FILE = 'file';

	protected string $title = '';
	protected string $mode = self::MODE_TEXT;
	protected ?string $bodyText = null;
	protected bool $required = true;
	protected ?int $fileId = null;
	protected ?string $fileOwner = null;
	protected ?string $fileName = null;
	protected ?string $targetRule = null;
	protected string $createdBy = '';
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('required', 'boolean');
		$this->addType('fileId', 'integer');
		$this->addType('createdAt', 'integer');
	}

	/** Decoded auto-request rule: {all?: bool, statuses?: string[], nc_groups?: string[]} */
	public function targetSpec(): array {
		$spec = $this->targetRule !== null ? json_decode($this->targetRule, true) : null;
		return is_array($spec) ? $spec : [];
	}

	public function jsonSerialize(): array {
		$j = parent::jsonSerialize();
		$j['target'] = $this->targetSpec();
		return $j;
	}
}
