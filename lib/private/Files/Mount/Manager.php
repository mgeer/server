<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Files\Mount;

use \OC\Files\Filesystem;
use OCP\Files\Mount\IMountManager;
use OCP\Files\Mount\IMountPoint;

class Manager implements IMountManager {
	/**
	 * @var MountPoint[]
	 */
	private $mounts = [];

	/**
	 * @param IMountPoint $mount
	 */
	public function addMount(IMountPoint $mount) {
		$this->mounts[$mount->getMountPoint()] = $mount;
	}

	/**
	 * @param string $mountPoint
	 */
	public function removeMount(string $mountPoint) {
		$mountPoint = Filesystem::normalizePath($mountPoint);
		if (\strlen($mountPoint) > 1) {
			$mountPoint .= '/';
		}
		unset($this->mounts[$mountPoint]);
	}

	/**
	 * @param string $mountPoint
	 * @param string $target
	 */
	public function moveMount(string $mountPoint, string $target){
		$this->mounts[$target] = $this->mounts[$mountPoint];
		unset($this->mounts[$mountPoint]);
	}

	/**
	 * Find the mount for $path
	 *
	 * @param string $path
	 * @return MountPoint
	 */
	public function find(string $path): IMountPoint {
		\OC_Util::setupFS();
		$path = $this->formatPath($path);
		if (isset($this->mounts[$path])) {
			return $this->mounts[$path];
		}

		\OC_Hook::emit('OC_Filesystem', 'get_mountpoint', ['path' => $path]);
		$foundMountPoint = '';
		$mountPoints = array_keys($this->mounts);
		foreach ($mountPoints as $mountpoint) {
			if (strpos($path, $mountpoint) === 0 && \strlen($mountpoint) > \strlen($foundMountPoint)) {
				$foundMountPoint = $mountpoint;
			}
		}

		if (isset($this->mounts[$foundMountPoint])) {
			return $this->mounts[$foundMountPoint];
		}

		return null;
	}

	/**
	 * Find all mounts in $path
	 *
	 * @param string $path
	 * @return MountPoint[]
	 */
	public function findIn(string $path): array {
		\OC_Util::setupFS();
		$path = $this->formatPath($path);
		$result = [];
		$pathLength = \strlen($path);
		$mountPoints = array_keys($this->mounts);
		foreach ($mountPoints as $mountPoint) {
			if (substr($mountPoint, 0, $pathLength) === $path && \strlen($mountPoint) > $pathLength) {
				$result[] = $this->mounts[$mountPoint];
			}
		}
		return $result;
	}

	public function clear() {
		$this->mounts = [];
	}

	/**
	 * Find mounts by storage id
	 *
	 * @param string $id
	 * @return MountPoint[]
	 */
	public function findByStorageId(string $id): array {
		\OC_Util::setupFS();
		if (\strlen($id) > 64) {
			$id = md5($id);
		}
		$result = [];
		foreach ($this->mounts as $mount) {
			if ($mount->getStorageId() === $id) {
				$result[] = $mount;
			}
		}
		return $result;
	}

	/**
	 * @return MountPoint[]
	 */
	public function getAll(): array {
		return $this->mounts;
	}

	/**
	 * Find mounts by numeric storage id
	 *
	 * @param int $id
	 * @return MountPoint[]
	 */
	public function findByNumericId(int $id): array {
		$storageId = \OC\Files\Cache\Storage::getStorageId($id);
		return $this->findByStorageId($storageId);
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function formatPath(string $path): string {
		$path = Filesystem::normalizePath($path);
		if (\strlen($path) > 1) {
			$path .= '/';
		}
		return $path;
	}
}
