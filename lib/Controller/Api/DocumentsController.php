<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Controller\Api;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Db\Document;
use OCA\Leiterrunde\Db\DocumentMapper;
use OCA\Leiterrunde\Db\DocumentRequest;
use OCA\Leiterrunde\Db\DocumentRequestMapper;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Exception\NotFoundException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\DocumentStorageService;
use OCA\Leiterrunde\Service\DocumentTargetService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\Leiterrunde\Service\SettingsService;
use OCA\Leiterrunde\Service\SignedPdfBuilder;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class DocumentsController extends ApiController {
	private const DOC_TYPE = 'dokument';
	private const MAX_BODY_LEN = 100000;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private DocumentMapper $docs,
		private DocumentRequestMapper $reqs,
		private PersonMapper $persons,
		private DocumentStorageService $files,
		private AuditService $audit,
		private DocumentTargetService $targets,
		private SettingsService $settings,
		private \OCP\IGroupManager $groupManager,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	private function findDoc(int $id): Document {
		try {
			return $this->docs->find($id);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Document not found');
		}
	}

	private function ownPersonId(): ?int {
		return $this->perm->currentPerson()?->getId();
	}

	private function ownRequest(int $docId): ?DocumentRequest {
		$pid = $this->ownPersonId();
		return $pid === null ? null : $this->reqs->findFor($docId, $pid);
	}

	private function canView(): bool {
		return $this->perm->has('documents.manage') || $this->perm->has('documents.view');
	}

	private function requireView(): void {
		if (!$this->canView()) {
			$this->perm->require('documents.view');
		}
	}

	/** View/manage access OR an own signature request for this document. @throws ForbiddenException via ApiException */
	private function assertCanSee(Document $doc): void {
		if ($this->canView() || $this->ownRequest($doc->getId()) !== null) {
			return;
		}
		$this->requireView();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): JSONResponse {
		return $this->wrap(function () {
			$this->requireView();
			$out = [];
			foreach ($this->docs->findAll() as $d) {
				$reqs = $this->reqs->findByDocument($d->getId());
				$signed = count(array_filter($reqs, static fn ($r) => $r->getStatus() === DocumentRequest::STATUS_SIGNED));
				$out[] = $d->jsonSerialize() + [
					'requests' => count($reqs),
					'signed' => $signed,
				];
			}
			return $out;
		});
	}

	/** Documents requested from the logged-in person, incl. text for reading before signing. */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function mine(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('app.access');
			$pid = $this->ownPersonId();
			if ($pid === null) {
				return [];
			}
			$out = [];
			foreach ($this->reqs->findByPerson($pid) as $r) {
				try {
					$d = $this->docs->find($r->getDocumentId());
				} catch (DoesNotExistException) {
					continue;
				}
				$out[] = $r->jsonSerialize() + [
					'title' => $d->getTitle(),
					'mode' => $d->getMode(),
					'required' => $d->getRequired(),
					'body_text' => $d->getBodyText(),
					'doc_has_file' => $d->getFileId() !== null,
				];
			}
			return $out;
		});
	}

	#[NoAdminRequired]
	public function create(string $title, ?string $mode = null, ?string $body_text = null, ?bool $required = null): JSONResponse {
		return $this->wrap(function () use ($title, $mode, $body_text, $required) {
			$this->perm->require('documents.manage');
			$d = new Document();
			$d->setTitle($this->validTitle($title));
			$d->setMode($mode === Document::MODE_FILE ? Document::MODE_FILE : Document::MODE_TEXT);
			$d->setBodyText($this->validBody($body_text));
			$d->setRequired($required ?? true);
			$d->setCreatedBy($this->perm->requireLoggedIn()->getUID());
			$d->setCreatedAt(time());
			$d = $this->docs->insert($d);
			$this->audit->diff('document', $d->getId(), null, ['_action' => 'created']);
			return new JSONResponse($d->jsonSerialize(), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$d = $this->findDoc($id);
			if ($this->canView()) {
				$reqs = array_map(function (DocumentRequest $r) {
					$name = null;
					try {
						$p = $this->persons->find($r->getPersonId());
						$name = $p->getLastName() . ', ' . $p->getFirstName();
					} catch (\Throwable) {
					}
					return $r->jsonSerialize() + ['person_name' => $name ?? '#' . $r->getPersonId()];
				}, $this->reqs->findByDocument($id));
				return $d->jsonSerialize() + ['requests' => $reqs];
			}
			$this->assertCanSee($d);
			$r = $this->ownRequest($id);
			return $d->jsonSerialize() + ['own_request' => $r?->jsonSerialize()];
		});
	}

	#[NoAdminRequired]
	public function update(int $id, ?string $title = null, ?string $body_text = null, ?bool $required = null): JSONResponse {
		return $this->wrap(function () use ($id, $title, $body_text, $required) {
			$this->perm->require('documents.manage');
			$d = $this->findDoc($id);
			$before = $d->jsonSerialize();
			$params = $this->request->getParams();
			if (array_key_exists('title', $params)) {
				$d->setTitle($this->validTitle((string)$title));
			}
			if (array_key_exists('body_text', $params)) {
				$d->setBodyText($this->validBody($body_text));
			}
			if ($required !== null) {
				$d->setRequired($required);
			}
			$d = $this->docs->update($d);
			$this->audit->diff('document', $id, $before, $d->jsonSerialize());
			return $d->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('documents.manage');
			$d = $this->findDoc($id);
			foreach ($this->reqs->findByDocument($id) as $r) {
				$this->removeFile($r);
			}
			$this->reqs->deleteByDocument($id);
			$this->removeFile($d);
			$this->docs->delete($d);
			$this->audit->diff('document', $id, null, ['_action' => 'deleted']);
			return ['ok' => true];
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function file(int $id): Response {
		try {
			$d = $this->findDoc($id);
			$this->assertCanSee($d);
			if ($d->getFileId() === null || $d->getFileOwner() === null) {
				throw new ApiException('No file present', 404);
			}
			return $this->stream($this->files->get($d->getFileOwner(), $d->getFileId()), $d->getFileName() ?? 'dokument');
		} catch (ApiException $e) {
			return new JSONResponse(['error' => $e->getMessage()], $e->getStatus());
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Not found'], 404);
		}
	}

	#[NoAdminRequired]
	public function uploadFile(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('documents.manage');
			$d = $this->findDoc($id);
			$stored = $this->files->store(self::DOC_TYPE, $this->perm->requireLoggedIn()->getUID(), $this->upload(), $this->docVars($id));
			$before = $d->jsonSerialize();
			$this->removeFile($d);
			$d->setFileId($stored['file_id']);
			$d->setFileOwner($stored['file_owner']);
			$d->setFileName($stored['file_name']);
			$d = $this->docs->update($d);
			$this->audit->diff('document', $id, $before, $d->jsonSerialize());
			return $d->jsonSerialize();
		});
	}

	/** Request signatures: {person_ids:[…]} one-off, and/or {target:{all,statuses,nc_groups}}
	 *  persisted as the document's auto-request rule (target: null clears it; existing
	 *  requests are always kept). */
	#[NoAdminRequired]
	public function requestSign(int $id, ?array $person_ids = null, mixed $target = null): JSONResponse {
		return $this->wrap(function () use ($id, $person_ids, $target) {
			$this->perm->require('documents.manage');
			$d = $this->findDoc($id);
			$params = $this->request->getParams();
			if (array_key_exists('target', $params)) {
				$spec = $target === null ? [] : $this->targets->validSpec($target);
				$before = $d->jsonSerialize();
				$d->setTargetRule($spec === [] ? null : json_encode($spec));
				$d = $this->docs->update($d);
				$this->audit->diff('document', $id, $before, $d->jsonSerialize());
			}
			$created = $skipped = 0;
			$errors = [];
			$auto = 0;
			$spec = $d->targetSpec();
			if ($spec !== []) {
				$auto = $this->targets->materialize($d);
			}
			if ($person_ids !== null && $person_ids !== []) {
				if (count($person_ids) > 500) {
					throw new ApiException('person_ids: max 500 entries', 422);
				}
				foreach ($person_ids as $pid) {
					try {
						$this->persons->find((int)$pid);
					} catch (\Throwable) {
						$errors[] = (int)$pid;
						continue;
					}
					if ($this->reqs->findFor($id, (int)$pid) !== null) {
						$skipped++;
						continue;
					}
					$r = new DocumentRequest();
					$r->setDocumentId($id);
					$r->setPersonId((int)$pid);
					$r->setStatus(DocumentRequest::STATUS_PENDING);
					$r->setCreatedAt(time());
					$this->reqs->insert($r);
					$created++;
				}
			} elseif ($spec === [] && !array_key_exists('target', $params)) {
				throw new ApiException('person_ids or target required', 422);
			}
			if ($created + $auto > 0) {
				$this->audit->diff('document', $id, null, ['_action' => 'sign_requested']);
			}
			return ['created' => $created + $auto, 'skipped' => $skipped, 'errors' => $errors, 'target' => $spec];
		});
	}

	/** Requests of one person, with doc metadata — for the person detail page (view/manage). */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function byPerson(int $pid): JSONResponse {
		return $this->wrap(function () use ($pid) {
			$this->requireView();
			try {
				$this->persons->find($pid);
			} catch (\Throwable) {
				throw new NotFoundException('Person not found');
			}
			$out = [];
			foreach ($this->reqs->findByPerson($pid) as $r) {
				try {
					$d = $this->docs->find($r->getDocumentId());
				} catch (DoesNotExistException) {
					continue;
				}
				$out[] = $r->jsonSerialize() + [
					'title' => $d->getTitle(),
					'mode' => $d->getMode(),
					'required' => $d->getRequired(),
					'doc_has_file' => $d->getFileId() !== null,
				];
			}
			return $out;
		});
	}

	/** Options for the request-target picker: person statuses and NC groups (manage only). */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function targets(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('documents.manage');
			$groups = [];
			foreach ($this->groupManager->search('') as $g) {
				$groups[] = ['gid' => $g->getGID(), 'display' => $g->getDisplayName()];
			}
			usort($groups, static fn ($a, $b) => strcasecmp($a['display'], $b['display']));
			return [
				'statuses' => $this->settings->personStatusDefs(),
				'nc_groups' => $groups,
			];
		});
	}

	/** Admin: mark a request signed on behalf of the person (recorded acceptance)
	 *  or reset it to pending (clears signature + file). signed_by records the acting admin. */
	#[NoAdminRequired]
	public function updateRequest(int $id, int $rid, ?string $status = null, ?string $signature_text = null): JSONResponse {
		return $this->wrap(function () use ($id, $rid, $status, $signature_text) {
			$this->perm->require('documents.manage');
			$r = $this->reqs->find($rid);
			if ($r->getDocumentId() !== $id) {
				throw new NotFoundException('Request not found');
			}
			$before = $r->jsonSerialize();
			if ($status === DocumentRequest::STATUS_PENDING) {
				$this->removeFile($r);
				$r->setStatus(DocumentRequest::STATUS_PENDING);
				$r->setMethod(null);
				$r->setSignatureText(null);
				$r->setSignedBy(null);
				$r->setSignedAt(null);
				$r->setFileId(null);
				$r->setFileOwner(null);
				$r->setFileName(null);
			} elseif ($status === DocumentRequest::STATUS_SIGNED) {
				$sig = trim((string)$signature_text);
				if ($sig === '' || mb_strlen($sig) > 255) {
					throw new ApiException('Signature text required (max 255 chars)', 422);
				}
				$uid = $this->perm->requireLoggedIn()->getUID();
				$synth = $this->signedPdfUpload($this->findDoc($id), $r, null, $sig);
				if ($synth !== null) {
					$stored = $this->files->store(self::DOC_TYPE, $uid, $synth,
						$this->files->personVars($this->persons, $r->getPersonId()));
					$this->cleanupTmp($synth);
					$this->removeFile($r);
					$r->setFileId($stored['file_id']);
					$r->setFileOwner($stored['file_owner']);
					$r->setFileName($stored['file_name']);
				}
				$r->setStatus(DocumentRequest::STATUS_SIGNED);
				$r->setMethod(DocumentRequest::METHOD_ACCEPT);
				$r->setSignatureText($sig);
				$r->setSignedBy($uid);
				$r->setSignedAt(time());
			} else {
				throw new ApiException('status must be pending or signed', 422);
			}
			$this->reqs->update($r);
			$this->audit->diff('document_request', $rid, $before, $r->jsonSerialize());
			return $r->jsonSerialize();
		});
	}

	/** Admin: upload a signed artifact for a request (e.g. a scanned paper form). */
	#[NoAdminRequired]
	public function uploadRequestFile(int $id, int $rid): JSONResponse {
		return $this->wrap(function () use ($id, $rid) {
			$this->perm->require('documents.manage');
			$r = $this->reqs->find($rid);
			if ($r->getDocumentId() !== $id) {
				throw new NotFoundException('Request not found');
			}
			$upload = $this->upload();
			$synth = $this->signedPdfUpload($this->findDoc($id), $r, $upload['tmp_name'], null);
			if ($synth !== null) {
				$upload = $synth;
			}
			$stored = $this->files->store(self::DOC_TYPE, $this->perm->requireLoggedIn()->getUID(), $upload,
				$this->files->personVars($this->persons, $r->getPersonId()));
			$this->cleanupTmp($upload);
			$before = $r->jsonSerialize();
			$this->removeFile($r);
			$r->setFileId($stored['file_id']);
			$r->setFileOwner($stored['file_owner']);
			$r->setFileName($stored['file_name']);
			$r->setStatus(DocumentRequest::STATUS_SIGNED);
			$r->setMethod(DocumentRequest::METHOD_UPLOAD);
			$r->setSignedBy($this->perm->requireLoggedIn()->getUID());
			$r->setSignedAt(time());
			$this->reqs->update($r);
			$this->audit->diff('document_request', $rid, $before, $r->jsonSerialize());
			return $r->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function destroyRequest(int $id, int $rid): JSONResponse {
		return $this->wrap(function () use ($id, $rid) {
			$this->perm->require('documents.manage');
			$r = $this->reqs->find($rid);
			if ($r->getDocumentId() !== $id) {
				throw new NotFoundException('Request not found');
			}
			$this->removeFile($r);
			$this->reqs->delete($r);
			return ['ok' => true];
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function requestFile(int $id, int $rid): Response {
		try {
			$r = $this->reqs->find($rid);
			if ($r->getDocumentId() !== $id) {
				throw new NotFoundException('Request not found');
			}
			if (!$this->canView() && $r->getPersonId() !== $this->ownPersonId()) {
				$this->requireView();
			}
			if ($r->getFileId() === null || $r->getFileOwner() === null) {
				throw new ApiException('No file present', 404);
			}
			return $this->stream($this->files->get($r->getFileOwner(), $r->getFileId()), $r->getFileName() ?? 'unterschrift');
		} catch (ApiException $e) {
			return new JSONResponse(['error' => $e->getMessage()], $e->getStatus());
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Not found'], 404);
		}
	}

	/** Sign by accepting with a typed name. */
	#[NoAdminRequired]
	public function sign(int $id, ?string $signature_text = null): JSONResponse {
		return $this->wrap(function () use ($id, $signature_text) {
			$this->perm->require('app.access');
			$d = $this->findDoc($id);
			$r = $this->ownRequest($id);
			if ($r === null) {
				throw new NotFoundException('No signature requested from you');
			}
			if ($r->getStatus() === DocumentRequest::STATUS_SIGNED) {
				return $r->jsonSerialize();
			}
			$sig = trim((string)$signature_text);
			if ($sig === '' || mb_strlen($sig) > 255) {
				throw new ApiException('Please type your name as signature', 422);
			}
			$synth = $this->signedPdfUpload($d, $r, null, $sig);
			if ($synth !== null) {
				$stored = $this->files->store(self::DOC_TYPE, $this->perm->requireLoggedIn()->getUID(), $synth,
					$this->files->personVars($this->persons, $r->getPersonId()));
				$this->cleanupTmp($synth);
				$this->removeFile($r);
				$r->setFileId($stored['file_id']);
				$r->setFileOwner($stored['file_owner']);
				$r->setFileName($stored['file_name']);
			}
			$r->setStatus(DocumentRequest::STATUS_SIGNED);
			$r->setMethod(DocumentRequest::METHOD_ACCEPT);
			$r->setSignatureText($sig);
			$r->setSignedBy($this->perm->requireLoggedIn()->getUID());
			$r->setSignedAt(time());
			$this->reqs->update($r);
			$this->audit->diff('document_request', $r->getId(), null, ['_action' => 'signed']);
			return $r->jsonSerialize();
		});
	}

	/** Sign by uploading a signed document (PDF/photo) or a drawn signature (via=draw). */
	#[NoAdminRequired]
	public function signFile(int $id, ?string $via = null): JSONResponse {
		return $this->wrap(function () use ($id, $via) {
			$this->perm->require('app.access');
			$d = $this->findDoc($id);
			$r = $this->ownRequest($id);
			if ($r === null) {
				throw new NotFoundException('No signature requested from you');
			}
			if ($r->getStatus() === DocumentRequest::STATUS_SIGNED) {
				return $r->jsonSerialize();
			}
			$upload = $this->upload();
			$synth = $this->signedPdfUpload($d, $r, $upload['tmp_name'], null, $via === DocumentRequest::METHOD_DRAW);
			if ($synth !== null) {
				$upload = $synth;
			}
			$stored = $this->files->store(self::DOC_TYPE, $this->perm->requireLoggedIn()->getUID(), $upload,
				$this->files->personVars($this->persons, $r->getPersonId()));
			$this->cleanupTmp($upload);
			$this->removeFile($r);
			$r->setFileId($stored['file_id']);
			$r->setFileOwner($stored['file_owner']);
			$r->setFileName($stored['file_name']);
			$r->setStatus(DocumentRequest::STATUS_SIGNED);
			$r->setMethod($via === DocumentRequest::METHOD_DRAW ? DocumentRequest::METHOD_DRAW : DocumentRequest::METHOD_UPLOAD);
			$r->setSignedBy($this->perm->requireLoggedIn()->getUID());
			$r->setSignedAt(time());
			$this->reqs->update($r);
			$this->audit->diff('document_request', $r->getId(), null, ['_action' => 'signed']);
			return $r->jsonSerialize();
		});
	}

	private function upload(): array {
		$upload = $this->request->getUploadedFile('file');
		if (!is_array($upload) || empty($upload['tmp_name']) || !is_file($upload['tmp_name'])) {
			throw new ApiException('No file uploaded', 422);
		}
		return $upload;
	}

	/**
	 * Build a synthetic upload containing the merged signed PDF for text-mode
	 * documents — the typed name or a decodable image embedded under the
	 * consent text. Returns null for file-mode documents and whenever no PDF
	 * can be produced, so callers fall back to the raw artifact.
	 * $strict rethrows decode errors (drawn canvas signatures must be readable).
	 */
	private function signedPdfUpload(Document $d, DocumentRequest $r, ?string $imgPath, ?string $typedText, bool $strict = false): ?array {
		if ($d->getMode() !== Document::MODE_TEXT) {
			return null;
		}
		try {
			$p = $this->persons->find($r->getPersonId());
			$line = $p->getFirstName() . ' ' . $p->getLastName() . ', ' . date('d.m.Y');
			$pdf = $typedText !== null
				? SignedPdfBuilder::buildTyped($d->getTitle(), (string)$d->getBodyText(), $typedText, $line)
				: ($strict
					? SignedPdfBuilder::build($d->getTitle(), (string)$d->getBodyText(), (string)$imgPath, $line)
					: SignedPdfBuilder::tryBuild($d->getTitle(), (string)$d->getBodyText(), (string)$imgPath, $line));
			if ($pdf === null) {
				return null;
			}
		} catch (ApiException $e) {
			if ($strict) {
				throw $e;
			}
			return null;
		} catch (\Throwable) {
			return null;
		}
		$tmp = tempnam(sys_get_temp_dir(), 'lr-sig');
		file_put_contents($tmp, $pdf);
		return ['tmp_name' => $tmp, 'name' => 'unterschrift-' . $d->getId() . '.pdf', 'size' => strlen($pdf)];
	}

	private function cleanupTmp(array $upload): void {
		if (str_starts_with((string)($upload['tmp_name'] ?? ''), sys_get_temp_dir() . '/lr-sig')) {
			@unlink($upload['tmp_name']);
		}
	}

	private function stream(\OCP\Files\File $file, string $name): StreamResponse {
		$res = new StreamResponse($file->fopen('rb'));
		$res->addHeader('Content-Type', $file->getMimeType());
		$res->addHeader('Content-Disposition', "inline; filename*=UTF-8''" . rawurlencode($name));
		$res->addHeader('X-Content-Type-Options', 'nosniff');
		return $res;
	}

	private function removeFile(Document|DocumentRequest $e): void {
		if ($e->getFileId() === null || $e->getFileOwner() === null) {
			return;
		}
		try {
			$this->files->get($e->getFileOwner(), $e->getFileId())->delete();
		} catch (\Throwable) {
			// file already gone — keep the record reference
		}
	}

	private function docVars(int $docId): array {
		$now = new \DateTimeImmutable();
		return ['jahr' => $now->format('Y'), 'monat' => $now->format('m'), 'datum' => $now->format('Y-m-d'), 'id' => (string)$docId];
	}

	private function validTitle(string $title): string {
		$t = trim($title);
		if ($t === '' || mb_strlen($t) > 255) {
			throw new ApiException('Title required (max 255 chars)', 422);
		}
		return $t;
	}

	private function validBody(?string $body): ?string {
		if ($body === null || $body === '') {
			return null;
		}
		if (mb_strlen($body) > self::MAX_BODY_LEN) {
			throw new ApiException('Text too long', 422);
		}
		return $body;
	}
}
