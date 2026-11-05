<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Tests\Unit;

use OCA\Leiterrunde\Service\Sync\Signer;
use PHPUnit\Framework\TestCase;

class SignerTest extends TestCase {
	private Signer $signer;

	protected function setUp(): void {
		$this->signer = new Signer('testsecret');
	}

	public function testValid(): void {
		$body = '{"a":1}';
		$ts = 1000000;
		$sig = $this->signer->sign($body, $ts);
		$this->assertSame(hash_hmac('sha256', $ts . "\n" . $body, 'testsecret'), $sig);
		$this->assertTrue($this->signer->verify($body, (string)$ts, $sig, $ts));
	}

	public function testWrongSecret(): void {
		$body = '{"a":1}';
		$ts = 1000;
		$sig = (new Signer('other'))->sign($body, $ts);
		$this->assertFalse($this->signer->verify($body, (string)$ts, $sig, $ts));
	}

	public function testTamperedBody(): void {
		$body = '{"a":1}';
		$ts = 1000;
		$sig = $this->signer->sign($body, $ts);
		$this->assertFalse($this->signer->verify('{"a":2}', (string)$ts, $sig, $ts));
	}

	public function testSkew(): void {
		$body = 'x';
		$ts = 10000;
		$sig = $this->signer->sign($body, $ts);
		$this->assertTrue($this->signer->verify($body, (string)$ts, $sig, $ts + 299));
		$this->assertFalse($this->signer->verify($body, (string)$ts, $sig, $ts + 301));
		$this->assertFalse($this->signer->verify($body, (string)$ts, $sig, $ts - 301));
	}

	public function testRawTimestampStringUsed(): void {
		// ts string wird unverändert signiert (keine int-Normalisierung)
		$body = 'b';
		$sig = $this->signer->sign($body, '0177');
		$this->assertSame(hash_hmac('sha256', "0177
" . $body, 'testsecret'), $sig);
	}

	public function testMissingHeaders(): void {
		$this->assertFalse($this->signer->verify('x', null, 'abc', 1000));
		$this->assertFalse($this->signer->verify('x', '1000', null, 1000));
		$this->assertFalse($this->signer->verify('x', 'abc', 'deadbeef', 1000));
	}
}
