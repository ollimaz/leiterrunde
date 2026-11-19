<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Db;

/**
 * @method int getDocumentId()
 * @method void setDocumentId(int $v)
 * @method int getPersonId()
 * @method void setPersonId(int $v)
 * @method string getStatus()
 * @method void setStatus(string $v)
 * @method ?string getMethod()
 * @method void setMethod(?string $v)
 * @method ?string getSignatureText()
 * @method void setSignatureText(?string $v)
 * @method ?string getSignedBy()
 * @method void setSignedBy(?string $v)
 * @method ?int getSignedAt()
 * @method void setSignedAt(?int $v)
 * @method ?int getFileId()
 * @method void setFileId(?int $v)
 * @method ?string getFileOwner()
 * @method void setFileOwner(?string $v)
 * @method ?string getFileName()
 * @method void setFileName(?string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class DocumentRequest extends BaseEntity {
	public const STATUS_PENDING = 'pending';
	public const STATUS_SIGNED = 'signed';
	public const METHOD_ACCEPT = 'accept';
	public const METHOD_UPLOAD = 'upload';
	public const METHOD_DRAW = 'draw';

	protected int $documentId = 0;
	protected int $personId = 0;
	protected string $status = self::STATUS_PENDING;
	protected ?string $method = null;
	protected ?string $signatureText = null;
	protected ?string $signedBy = null;
	protected ?int $signedAt = null;
	protected ?int $fileId = null;
	protected ?string $fileOwner = null;
	protected ?string $fileName = null;
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('documentId', 'integer');
		$this->addType('personId', 'integer');
		$this->addType('signedAt', 'integer');
		$this->addType('fileId', 'integer');
		$this->addType('createdAt', 'integer');
	}
}
