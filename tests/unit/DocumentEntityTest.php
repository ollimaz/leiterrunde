<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Tests\Unit;

use OCA\Leiterrunde\Db\Document;
use OCA\Leiterrunde\Db\DocumentRequest;
use PHPUnit\Framework\TestCase;

class DocumentEntityTest extends TestCase {
	public function testDocumentSerializesSnakeCase(): void {
		$d = new Document();
		$d->setTitle('Datenschutz');
		$d->setMode(Document::MODE_FILE);
		$d->setBodyText('text');
		$d->setFileId(7);
		$d->setFileOwner('alice');
		$d->setFileName('a.pdf');
		$d->setCreatedBy('alice');
		$d->setCreatedAt(123);
		$j = $d->jsonSerialize();
		$this->assertSame('Datenschutz', $j['title']);
		$this->assertSame('file', $j['mode']);
		$this->assertSame('text', $j['body_text']);
		$this->assertTrue($j['required']);
		$this->assertSame(7, $j['file_id']);
		$this->assertSame('alice', $j['file_owner']);
		$this->assertSame('a.pdf', $j['file_name']);
		$this->assertSame('alice', $j['created_by']);
		$this->assertSame(123, $j['created_at']);
	}

	public function testDocumentDefaults(): void {
		$d = new Document();
		$this->assertSame(Document::MODE_TEXT, $d->getMode());
		$this->assertTrue($d->getRequired());
		$this->assertNull($d->getBodyText());
		$this->assertNull($d->getFileId());
	}

	public function testRequestDefaultsAndSerialization(): void {
		$r = new DocumentRequest();
		$this->assertSame(DocumentRequest::STATUS_PENDING, $r->getStatus());
		$this->assertNull($r->getSignedAt());
		$r->setDocumentId(3);
		$r->setPersonId(9);
		$r->setStatus(DocumentRequest::STATUS_SIGNED);
		$r->setMethod(DocumentRequest::METHOD_ACCEPT);
		$r->setSignatureText('Max Muster');
		$r->setSignedBy('max');
		$r->setSignedAt(456);
		$j = $r->jsonSerialize();
		$this->assertSame(3, $j['document_id']);
		$this->assertSame(9, $j['person_id']);
		$this->assertSame('signed', $j['status']);
		$this->assertSame('accept', $j['method']);
		$this->assertSame('Max Muster', $j['signature_text']);
		$this->assertSame('max', $j['signed_by']);
		$this->assertSame(456, $j['signed_at']);
	}
}
