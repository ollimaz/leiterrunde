<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Db;

/**
 * @method string getFirstName()
 * @method void setFirstName(string $v)
 * @method string getLastName()
 * @method void setLastName(string $v)
 * @method ?string getEmail()
 * @method void setEmail(?string $v)
 * @method ?string getPhone()
 * @method void setPhone(?string $v)
 * @method ?string getMobile()
 * @method void setMobile(?string $v)
 * @method ?string getStreet()
 * @method void setStreet(?string $v)
 * @method ?string getZip()
 * @method void setZip(?string $v)
 * @method ?string getCity()
 * @method void setCity(?string $v)
 * @method ?string getBirthdate()
 * @method void setBirthdate(?string $v)
 * @method ?int getBirthYear()
 * @method void setBirthYear(?int $v)
 * @method string getStatus()
 * @method void setStatus(string $v)
 * @method ?string getFunctionLabel()
 * @method void setFunctionLabel(?string $v)
 * @method ?string getNotes()
 * @method void setNotes(?string $v)
 * @method ?string getNcUserId()
 * @method void setNcUserId(?string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $v)
 */
class Person extends BaseEntity {
	protected string $firstName = '';
	protected string $lastName = '';
	protected ?string $email = null;
	protected ?string $phone = null;
	protected ?string $mobile = null;
	protected ?string $street = null;
	protected ?string $zip = null;
	protected ?string $city = null;
	protected ?string $birthdate = null;
	protected ?int $birthYear = null;
	protected string $status = 'aktiv';
	protected ?string $functionLabel = null;
	protected ?string $notes = null;
	protected ?string $ncUserId = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;
}
