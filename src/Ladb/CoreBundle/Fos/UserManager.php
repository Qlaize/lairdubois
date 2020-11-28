<?php

namespace Ladb\CoreBundle\Fos;

use FOS\UserBundle\Model\UserInterface;
use Ladb\CoreBundle\Entity\Core\User;

class UserManager extends \FOS\UserBundle\Doctrine\UserManager {

	const NAME = 'ladb_core.fos.user_manager';

	public function updateUser(UserInterface $user, $andFlush = true) {

		// Populate displayname if null
		if ($user instanceof User && is_null($user->getDisplayname())) {
			$user->setDisplayname($user->getUsername());
		}

		parent::updateUser($user, $andFlush);
	}

}