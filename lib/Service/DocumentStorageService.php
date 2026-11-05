<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Service;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Exception\NotFoundException;
use OCA\Leiterrunde\Module\ModuleRegistry;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\IAppConfig;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class DocumentStorageService {
	public const MAX_BYTES = 20 * 1024 * 1024;
	private const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'heic'];

	public function __construct(
		private IRootFolder $rootFolder,
		private IAppConfig $appConfig,
		private IUserManager $userManager,
		private ModuleRegistry $modules,
		private LoggerInterface $logger,
	) {
	}

	public function docTypes(): array {
		return $this->modules->documentTypes();
	}

	public function pathTemplate(string $docTypeId): string {
		foreach ($this->docTypes() as $d) {
			if ($d->id === $docTypeId) {
				return $this->appConfig->getValueString(Application::APP_ID, 'doc_path_' . $docTypeId, $d->defaultPath);
			}
		}
		throw new ApiException('Unknown document type', 400);
	}

	public static function sanitizeSegment(string $v): string {
		$v = str_replace(['/', '\\', "\0"], '_', $v);
		$v = preg_replace('/[\x00-\x1F]+/', '', $v) ?? '';
		$v = trim($v);
		if ($v === '' || $v === '.' || $v === '..') {
			$v = '_';
		}
		return $v;
	}

	/** Standard path-template variables for a person's documents; callers merge their extras. */
	public function personVars(PersonMapper $personMapper, int $personId): array {
		$person = null;
		try { $person = $personMapper->find($personId); } catch (\Throwable) {}
		$now = new \DateTimeImmutable();
		return [
			'jahr' => $now->format('Y'), 'monat' => $now->format('m'), 'datum' => $now->format('Y-m-d'),
			'vorname' => $person?->getFirstName() ?? '', 'nachname' => $person?->getLastName() ?? '',
			'id' => (string)$personId,
		];
	}

	/** Platzhalter ersetzen + Template-Segmente validieren. */
	public function renderPath(string $template, array $vars): string {
		$path = preg_replace_callback('/\{(\w+)\}/', static function ($m) use ($vars) {
			return self::sanitizeSegment((string)($vars[$m[1]] ?? ''));
		}, $template);
		$segments = [];
		foreach (explode('/', (string)$path) as $seg) {
			$seg = trim($seg);
			if ($seg === '' || $seg === '..' || $seg === '.') {
				throw new ApiException('Invalid path template', 422);
			}
			$segments[] = $seg;
		}
		if (str_starts_with((string)$path, '/')) {
			throw new ApiException('Path must not be absolute', 422);
		}
		return implode('/', $segments);
	}

	/**
	 * @return array{file_id:int, file_owner:string, file_name:string}
	 */
	public function store(string $docTypeId, string $uploaderUid, array $upload, array $vars): array {
		$tmp = $upload['tmp_name'] ?? null;
		$origName = (string)($upload['name'] ?? 'datei');
		$size = (int)($upload['size'] ?? 0);
		if (!is_string($tmp) || $tmp === '' || !is_file($tmp)) {
			throw new ApiException('No file uploaded', 400);
		}
		if ($size > self::MAX_BYTES) {
			throw new ApiException('File is larger than 20 MB', 422);
		}
		$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
		if (!in_array($ext, self::ALLOWED_EXT, true)) {
			throw new ApiException('Only PDF, JPG, PNG and HEIC are allowed', 422);
		}
		$owner = $this->resolveOwner($uploaderUid);
		$relPath = $this->renderPath($this->pathTemplate($docTypeId), $vars);
		$folder = $this->ensureFolder($owner, $relPath);
		$safeName = date('Ymd') . '_' . bin2hex(random_bytes(4)) . '_' . $this->sanitizeFilename($origName);
		$content = file_get_contents($tmp);
		if ($content === false) {
			throw new ApiException('File could not be read', 400);
		}
		$file = $folder->newFile($safeName, $content);
		return ['file_id' => $file->getId(), 'file_owner' => $owner, 'file_name' => $safeName];
	}

	public function resolveOwner(string $uploaderUid): string {
		$storageUser = trim($this->appConfig->getValueString(Application::APP_ID, 'storage_user', ''));
		if ($storageUser !== '' && $this->userManager->get($storageUser) !== null) {
			return $storageUser;
		}
		if ($storageUser !== '') {
			$this->logger->warning('Leiterrunde: storage_user "' . $storageUser . '" existiert nicht – Fallback auf Hochladenden');
		}
		return $uploaderUid;
	}

	public function get(string $owner, int $fileId): File {
		try {
			$userFolder = $this->rootFolder->getUserFolder($owner);
		} catch (\Throwable) {
			throw new NotFoundException('Datei nicht gefunden');
		}
		$node = $userFolder->getFirstNodeById($fileId);
		if (!$node instanceof File) {
			throw new NotFoundException('Datei nicht gefunden');
		}
		return $node;
	}

	private function ensureFolder(string $uid, string $relPath): Folder {
		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
		} catch (\Throwable $e) {
			throw new ApiException('User folder not available: %s', 500, [$e->getMessage()]);
		}
		$current = $userFolder;
		foreach (explode('/', $relPath) as $seg) {
			try {
				$node = $current->get($seg);
				if ($node instanceof Folder) {
					$current = $node;
					continue;
				}
			} catch (FilesNotFoundException) {
			}
			$current = $current->newFolder($seg);
		}
		return $current;
	}

	private function sanitizeFilename(string $name): string {
		$name = basename($name);
		$name = preg_replace('/[^\w.\-äöüÄÖÜß ]+/u', '_', $name) ?? '';
		return $name === '' ? 'datei' : $name;
	}
}
